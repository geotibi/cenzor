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

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="apartment_unpaid_summary.xls"');
header('Pragma: no-cache');
header('Expires: 0');

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

// Output the Excel content
echo '
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        td {
            mso-number-format:\@;
        }
        .number {
            mso-number-format:"0.00";
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <th colspan="8">' . $title . '</th>
        </tr>
        <tr>
            <th>Section</th>
            <th>Apartment</th>
            <th>Owner</th>
            <th>Total Fees</th>
            <th>Total Paid</th>
            <th>Unpaid Amount</th>
            <th>Status</th>
        </tr>';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = '';
        if ($row['unpaid_amount'] <= 0) {
            $status = 'Paid';
        } elseif ($row['total_paid'] > 0) {
            $status = 'Partial';
        } else {
            $status = 'Unpaid';
        }
        
        echo '<tr>
            <td>Section ' . $row['scara'] . '</td>
            <td>' . $row['number'] . '</td>
            <td>' . $row['owner_name'] . '</td>
            <td class="number">' . number_format($row['total_fees'], 2) . '</td>
            <td class="number">' . number_format($row['total_paid'], 2) . '</td>
            <td class="number">' . number_format($row['unpaid_amount'], 2) . '</td>
            <td>' . $status . '</td>
        </tr>';
    }
    
    // Add a summary row
    $result->data_seek(0);
    $total_fees = 0;
    $total_paid = 0;
    $total_unpaid = 0;
    
    while ($row = $result->fetch_assoc()) {
        $total_fees += $row['total_fees'];
        $total_paid += $row['total_paid'];
        $total_unpaid += $row['unpaid_amount'];
    }
    
    echo '<tr>
        <td colspan="5"><strong>TOTAL</strong></td>
        <td class="number"><strong>' . number_format($total_fees, 2) . '</strong></td>
        <td class="number"><strong>' . number_format($total_paid, 2) . '</strong></td>
        <td class="number"><strong>' . number_format($total_unpaid, 2) . '</strong></td>
        <td></td>
    </tr>';
} else {
    echo '<tr><td colspan="9">No data found</td></tr>';
}

echo '
    </table>
</body>
</html>';
?>