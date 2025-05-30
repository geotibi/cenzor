<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get parameters
$year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : date('n');

// Get month name
$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];
$month_name = $months[$month];

// Define the bill type groups with their display names
$bill_groups = [
    1 => ['name' => 'Urban', 'pattern' => '%Urban%'],
    2 => ['name' => 'Engie', 'pattern' => '%Engie%'],
    3 => ['name' => 'PPC', 'pattern' => '%PPC%'],
    4 => ['name' => 'Apa Nova', 'pattern' => '%Apa Nova%'],
    5 => ['name' => 'TEB', 'pattern' => '%TEB%'],
    6 => ['name' => 'Indemnizatie Membru Comitet', 'pattern' => '%INDEMNIZATIE MEMBRU COMITET%'],
    7 => ['name' => 'Bugetul de Stat', 'pattern' => '%BUGETUL DE STAT%'],
    8 => ['name' => 'Viomar', 'pattern' => '%VIOMAR%'],
    9 => ['name' => 'Scomet', 'pattern' => '%SCOMET%'],
    10 => ['name' => 'Elevator', 'pattern' => '%Elevator%'],
    11 => ['name' => 'Interada', 'pattern' => '%Interada%'],
    12 => ['name' => 'Indemnizatie Cenzor', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    13 => ['name' => 'Other', 'pattern' => '%']
];

// Query to get the data
$query = "
SELECT *
FROM building_bills
WHERE DATE_FORMAT(repartizare_luna, '%Y-%c') = ?
ORDER BY
  CASE
    WHEN bill_type LIKE '%Urban%' THEN 1
    WHEN bill_type LIKE '%Engie A%' THEN 2
    WHEN bill_type LIKE '%Engie B%' THEN 2
    WHEN bill_type LIKE '%Engie C%' THEN 2
    WHEN bill_type LIKE '%PPC A%' THEN 3
    WHEN bill_type LIKE '%PPC B%' THEN 3
    WHEN bill_type LIKE '%PPC C%' THEN 3
    WHEN bill_type LIKE '%Apa Nova%' THEN 4
    WHEN bill_type LIKE '%TEB A%' THEN 5
    WHEN bill_type LIKE '%TEB B%' THEN 5
    WHEN bill_type LIKE '%TEB C%' THEN 5
    WHEN bill_type LIKE '%INDEMNIZATIE MEMBRU COMITET%' THEN 6
    WHEN bill_type LIKE '%BUGETUL DE STAT%' THEN 7
    WHEN bill_type LIKE '%VIOMAR%' THEN 8
    WHEN bill_type LIKE '%SCOMET%' THEN 9
    WHEN bill_type LIKE '%Elevator%' THEN 10
    WHEN bill_type LIKE '%Interada%' THEN 11
    WHEN bill_type LIKE '%INDEMNIZATIE CENZOR%' THEN 12
    ELSE 13
  END,
  bill_type
";

$stmt = $conn->prepare($query);
$date_param = "$year-$month";
$stmt->bind_param("s", $date_param);
$stmt->execute();
$result = $stmt->get_result();

// Group the results by bill type group
$grouped_bills = [];
while ($row = $result->fetch_assoc()) {
    $group_id = 13; // Default to "Other"

    foreach ($bill_groups as $id => $group) {
        if (stripos($row['bill_type'], str_replace('%', '', $group['pattern'])) !== false) {
            $group_id = $id;
            break;
        }
    }

    if (!isset($grouped_bills[$group_id])) {
        $grouped_bills[$group_id] = [
            'name' => $bill_groups[$group_id]['name'],
            'bills' => []
        ];
    }

    $grouped_bills[$group_id]['bills'][] = $row;
}

// Calculate totals
$total_amount = 0;
foreach ($grouped_bills as $group_id => $group) {
    $group_total = 0;
    foreach ($group['bills'] as $bill) {
        $group_total += $bill['amount'];
    }
    $grouped_bills[$group_id]['total'] = $group_total;
    $total_amount += $group_total;
}

// Sort the groups by their ID
ksort($grouped_bills);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Building_Bills_' . $month_name . '_' . $year . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel content
echo '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Building Bills - ' . $month_name . ' ' . $year . '</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f2f2f2; }
        .group-header { background-color: #e6e6e6; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f2f2f2; }
        .grand-total { font-weight: bold; background-color: #d9d9d9; }
    </style>
</head>
<body>
    <h1>Building Bills - ' . $month_name . ' ' . $year . '</h1>
';

foreach ($grouped_bills as $group_id => $group) {
    if (!empty($group['bills'])) {
        echo '<h2>' . $group['name'] . ' - Total: ' . number_format($group['total'], 2) . ' RON</h2>';

        echo '
        <table>
            <thead>
                <tr>
                    <th>Bill Type</th>
                    <th>Bill No</th>
                    <th>Bill Date</th>
                    <th>Due Date</th>
                    <th>Amount (RON)</th>
                    <th>Description</th>
                    <th>Distribution Month</th>
                </tr>
            </thead>
            <tbody>
        ';

        foreach ($group['bills'] as $bill) {
            echo '
                <tr>
                    <td>' . $bill['bill_type'] . '</td>
                    <td>' . $bill['bill_no'] . '</td>
                    <td>' . date('d-m-Y', strtotime($bill['bill_date'])) . '</td>
                    <td>' . date('d-m-Y', strtotime($bill['bill_deadline'])) . '</td>
                    <td align="right">' . number_format($bill['amount'], 2) . '</td>
                    <td>' . $bill['description'] . '</td>
                    <td>' . date('F Y', strtotime($bill['repartizare_luna'])) . '</td>
                </tr>
            ';
        }

        echo '
                <tr class="total-row">
                    <td colspan="4" align="right">Group Total:</td>
                    <td align="right">' . number_format($group['total'], 2) . '</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        <br>
        ';
    }
}

echo '
    <table>
        <tr class="grand-total">
            <td colspan="4" align="right">Grand Total:</td>
            <td align="right">' . number_format($total_amount, 2) . ' RON</td>
            <td colspan="2"></td>
        </tr>
    </table>
</body>
</html>
';