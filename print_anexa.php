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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Building Bills - <?php echo $month_name . ' ' . $year; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .bill-group {
            margin-bottom: 30px;
        }
        .bill-group-title {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-end {
            text-align: right;
        }
        .grand-total {
            background-color: #343a40;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: right;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 15px;
            }
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
    
    <div class="print-header">
        <h1>Building Bills</h1>
        <h3><?php echo $month_name . ' ' . $year; ?></h3>
    </div>
    
    <?php if ($result->num_rows > 0): ?>
            <?php foreach ($grouped_bills as $group_id => $group): ?>
                    <?php if (!empty($group['bills'])): ?>
                            <div class="bill-group">
                                <div class="bill-group-title">
                                    <?php echo $group['name']; ?> 
                                    <span class="float-end">
                                        Total: <?php echo number_format($group['total'], 2); ?> RON
                                    </span>
                                </div>
                    
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Bill Type</th>
                                            <th>Bill No</th>
                                            <th>Bill Date</th>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                            <th>Distribution Month</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['bills'] as $bill): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($bill['bill_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($bill['bill_no']); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($bill['bill_date'])); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($bill['bill_deadline'])); ?></td>
                                                    <td class="text-end"><?php echo number_format($bill['amount'], 2); ?> RON</td>
                                                    <td><?php echo htmlspecialchars($bill['description']); ?></td>
                                                    <td><?php echo date('F Y', strtotime($bill['repartizare_luna'])); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Group Total:</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($group['total'], 2); ?> RON</strong></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                    <?php endif; ?>
            <?php endforeach; ?>
        
            <!-- Grand Total -->
            <div class="grand-total">
                <h4>Grand Total: <?php echo number_format($total_amount, 2); ?> RON</h4>
            </div>
        
    <?php else: ?>
            <div class="alert alert-info">
                No bills found for <?php echo $month_name . ' ' . $year; ?>.
            </div>
    <?php endif; ?>
    
    <script>
        // Auto-print when the page loads
        window.onload = function() {
            // Uncomment the line below to automatically print when the page loads
            // window.print();
        };
    </script>
</body>
</html>