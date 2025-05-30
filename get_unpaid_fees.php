<?php
// get_unpaid_fees.php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['apartment_id']) || !isset($_GET['month_key'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$apartment_id = (int)$_GET['apartment_id'];
$month_key = $_GET['month_key'];

try {
    // Get total unpaid amount
    $unpaid_query = "
        SELECT 
            SUM(af.total_amount) - COALESCE(SUM(
                (SELECT COALESCE(SUM(pl.amount), 0) 
                 FROM payment_links pl 
                 JOIN payments p ON pl.payment_id = p.id 
                 WHERE pl.apartment_fee_id = af.id)
            ), 0) as unpaid_amount
        FROM apartment_fees af
        WHERE af.apartment_id = ? 
        AND af.month_key < ?
        HAVING unpaid_amount > 0
    ";
    
    $stmt = $conn->prepare($unpaid_query);
    $stmt->bind_param("is", $apartment_id, $month_key);
    $stmt->execute();
    $unpaid_result = $stmt->get_result();
    
    $unpaid_amount = 0;
    if ($row = $unpaid_result->fetch_assoc()) {
        $unpaid_amount = (float)$row['unpaid_amount'];
    }
    
    // Calculate penalties
    $penalties = 0;
    if ($unpaid_amount > 0) {
        // Get the most recent unpaid fee
        $last_fee_query = "
            SELECT af.*, 
                (SELECT COALESCE(SUM(pl.amount), 0) 
                 FROM payment_links pl 
                 JOIN payments p ON pl.payment_id = p.id 
                 WHERE pl.apartment_fee_id = af.id) as paid_amount,
                af.total_amount - (SELECT COALESCE(SUM(pl.amount), 0) 
                                  FROM payment_links pl 
                                  JOIN payments p ON pl.payment_id = p.id 
                                  WHERE pl.apartment_fee_id = af.id) as unpaid
            FROM apartment_fees af
            WHERE af.apartment_id = ? 
            AND af.month_key < ?
            HAVING unpaid > 0
            ORDER BY af.month_key DESC
        ";
        
        $stmt = $conn->prepare($last_fee_query);
        $stmt->bind_param("is", $apartment_id, $month_key);
        $stmt->execute();
        $unpaid_fees_result = $stmt->get_result();
        
        $unpaid_fees = [];
        $last_fee = null;
        
        while ($fee = $unpaid_fees_result->fetch_assoc()) {
            if (!$last_fee) {
                $last_fee = $fee;
            }
            
            $unpaid_fees[] = [
                'id' => $fee['id'],
                'month_key' => $fee['month_key'],
                'unpaid' => (float)$fee['unpaid']
            ];
        }
        
        if ($last_fee) {
            $deadline = new DateTime($last_fee['payment_deadline']);
            $now = new DateTime();
            $days_late = $deadline->diff($now)->days;
            
            if ($days_late > 0) {
                // 0.1% per day
                $penalties = $unpaid_amount * 0.001 * $days_late;
            }
        }
        
        echo json_encode([
            'has_unpaid' => true,
            'unpaid_amount' => $unpaid_amount,
            'penalties' => $penalties,
            'unpaid_fees' => $unpaid_fees
        ]);
    } else {
        echo json_encode([
            'has_unpaid' => false,
            'unpaid_amount' => 0,
            'penalties' => 0,
            'unpaid_fees' => []
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>