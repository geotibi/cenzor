<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$apartment_id = (int) ($_GET['apartment_id'] ?? 0);

if (!$apartment_id) {
    echo json_encode(['error' => 'Invalid apartment ID']);
    exit;
}

$credits_query = "
SELECT 
    fund_type,
    SUM(remaining_amount) as remaining_amount
FROM payment_credits 
WHERE apartment_id = ? AND status = 'active' AND remaining_amount > 0
GROUP BY fund_type
ORDER BY fund_type
";

$stmt = $conn->prepare($credits_query);
$stmt->bind_param('i', $apartment_id);
$stmt->execute();
$result = $stmt->get_result();

$credits = [];
while ($row = $result->fetch_assoc()) {
    $credits[] = [
        'fund_type' => ucfirst(str_replace('_', ' ', $row['fund_type'])),
        'remaining_amount' => number_format($row['remaining_amount'], 2)
    ];
}

echo json_encode(['credits' => $credits]);
?>
