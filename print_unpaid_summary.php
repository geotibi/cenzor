<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Get filter parameters
$filter_scara = isset($_GET['scara']) ? $_GET['scara'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : '';

// Build the base query
$query = "
    SELECT 
        a.id AS apartment_id,
        a.number,
        a.scara,
        a.owner_name,
        COALESCE(SUM(af.total_amount), 0) AS total_fees,
        COALESCE(SUM(IFNULL(pl.amount, 0)), 0) AS total_paid,
        COALESCE(SUM(af.total_amount), 0) - COALESCE(SUM(IFNULL(pl.amount, 0)), 0) AS unpaid_amount
    FROM 
        apartments a
    LEFT JOIN 
        apartment_fees af ON a.id = af.apartment_id
    LEFT JOIN 
        payment_links pl ON af.id = pl.apartment_fee_id
    WHERE 
        1=1
";

// Add filters
if (!empty($filter_scara)) {
    $query .= " AND a.scara = '$filter_scara'";
}

if (!empty($filter_month) && !empty($filter_year)) {
    $month_key = sprintf("%04d-%02d", $filter_year, $filter_month);
    $query .= " AND af.month_key = '$month_key'";
} else if (!empty($filter_year)) {
    $query .= " AND af.month_key LIKE '$filter_year%'";
}

// Group by apartment
$query .= " GROUP BY a.id, a.number, a.scara, a.owner_name";

if ($filter_status === 'paid') {
    $query .= " HAVING unpaid_amount <= 0";
} else if ($filter_status === 'unpaid') {
    $query .= " HAVING unpaid_amount > 0";
} else if ($filter_status === 'partial') {
    $query .= " HAVING total_paid > 0 AND unpaid_amount > 0";
}

// Order by scara and apartment number
$query .= " ORDER BY a.scara, a.number";

// Execute the query
$result = $conn->query($query);

// Map month numbers to names
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

// Create title with filter information
$title = "Apartment Unpaid Summary";
if (!empty($filter_scara)) {
    $title .= " - Section " . $filter_scara;
}
if (!empty($filter_month) && !empty($filter_year)) {
    $title .= " - " . $months[(int)$filter_month] . " " . $filter_year;
} else if (!empty($filter_year)) {
    $title .= " - " . $filter_year;
}
if (!empty($filter_status)) {
    $title .= " - " . ucfirst($filter_status);
}

// Calculate summary statistics
$total_fees = 0;
$total_paid = 0;
$total_unpaid = 0;
$total_apartments = 0;
$apartments_with_debt = 0;

if ($result->num_rows > 0) {
    $total_apartments = $result->num_rows;
    
    while ($row = $result->fetch_assoc()) {
        $total_fees += $row['total_fees'];
        $total_paid += $row['total_paid'];
        $total_unpaid += $row['unpaid_amount'];
        
        if ($row['unpaid_amount'] > 0) {
            $apartments_with_debt++;
        }
    }
    
    // Reset the result pointer
    $result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .report-date {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .summary-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .summary-item {
            padding: 10px;
            border-radius: 5px;
        }
        .summary-item.total-unpaid {
            background-color: #e3f2fd;
        }
        .summary-item.total-apartments {
            background-color: #e8f5e9;
        }
        .summary-item.apartments-with-debt {
            background-color: #fff8e1;
        }
        .summary-item h3 {
            margin-top: 0;
            font-size: 16px;
        }
        .summary-item p {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0 0 0;
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
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-danger {
            color: #dc3545;
        }
        .text-success {
            color: #28a745;
        }
        .text-warning {
            color: #ffc107;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Report</button>
        <button onclick="window.close()">Close</button>
    </div>

    <h1><?= $title ?></h1>
    <div class="report-date">Generated on: <?= date('F j, Y, g:i a') ?></div>

    <div class="summary-box">
        <div class="summary-grid">
            <div class="summary-item total-unpaid">
                <h3>Total Unpaid Amount</h3>
                <p><?= number_format($total_unpaid, 2) ?> RON</p>
            </div>
            <div class="summary-item total-apartments">
                <h3>Total Apartments</h3>
                <p><?= $total_apartments ?></p>
            </div>
            <div class="summary-item apartments-with-debt">
                <h3>Apartments With Debt</h3>
                <p><?= $apartments_with_debt ?> (<?= $total_apartments > 0 ? round(($apartments_with_debt / $total_apartments) * 100) : 0 ?>%)</p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Section</th>
                <th>Apartment</th>
                <th>Owner</th>
                <th>Total Fees</th>
                <th>Total Paid</th>
                <th>Unpaid Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>Section <?= htmlspecialchars($row['scara']) ?></td>
                        <td><?= htmlspecialchars($row['number']) ?></td>
                        <td><?= htmlspecialchars($row['owner_name']) ?></td>
                        <td><?= number_format($row['total_fees'], 2) ?> RON</td>
                        <td><?= number_format($row['total_paid'], 2) ?> RON</td>
                        <td class="<?= $row['unpaid_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <strong><?= number_format($row['unpaid_amount'], 2) ?> RON</strong>
                        </td>
                        <td>
                            <?php if ($row['unpaid_amount'] <= 0): ?>
                                <span class="badge badge-success">Paid</span>
                            <?php elseif ($row['total_paid'] > 0): ?>
                                <span class="badge badge-warning">Partial</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Unpaid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="4"><strong>TOTAL</strong></td>
                    <td><strong><?= number_format($total_fees, 2) ?> RON</strong></td>
                    <td><strong><?= number_format($total_paid, 2) ?> RON</strong></td>
                    <td class="<?= $total_unpaid > 0 ? 'text-danger' : 'text-success' ?>">
                        <strong><?= number_format($total_unpaid, 2) ?> RON</strong>
                    </td>
                    <td></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No apartments found matching the criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated from the property management system.</p>
    </div>
</body>
</html>