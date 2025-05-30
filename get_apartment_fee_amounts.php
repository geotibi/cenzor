<?php
// get_apartment_fee_amounts.php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['apartment_id'])) {
    echo json_encode(['error' => 'Missing apartment_id parameter']);
    exit;
}

$apartment_id = (int)$_GET['apartment_id'];

try {
    // Get fees with their payment amounts
    $query = "SELECT 
                af.id, 
                af.month_key, 
                af.total_amount,
                COALESCE(SUM(pl.amount), 0) as paid_amount
              FROM 
                apartment_fees af
              LEFT JOIN 
                payment_links pl ON af.id = pl.apartment_fee_id
              WHERE 
                af.apartment_id = ?
              GROUP BY 
                af.id
              ORDER BY 
                af.month_key DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $apartment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fees = [];
    while ($row = $result->fetch_assoc()) {
        $fees[] = [
            'id' => (int)$row['id'],
            'month_key' => $row['month_key'],
            'total_amount' => (float)$row['total_amount'],
            'paid_amount' => (float)$row['paid_amount']
        ];
    }
    
    echo json_encode($fees);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>