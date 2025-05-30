<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<h1>Apartment Fee Payment System Fix Analysis</h1>";

// Analyze the current structure
echo "<h2>Database Structure Analysis</h2>";

// Check apartment_fees table structure
$result = $conn->query("DESCRIBE apartment_fees");
echo "<h3>apartment_fees table structure:</h3>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Show sample data to understand the issue
echo "<h2>Sample Data Analysis</h2>";
$result = $conn->query("
    SELECT 
        af.id,
        af.apartment_id,
        af.month_key,
        af.total,
        af.restante_cote,
        af.restante_fond_rulment,
        af.restante_fond_penalizari,
        af.total_amount,
        af.utilities,
        af.previous_unpaid,
        af.penalties,
        af.fond_special,
        a.number as apartment_number
    FROM apartment_fees af
    JOIN apartments a ON af.apartment_id = a.id
    ORDER BY af.apartment_id, af.month_key
    LIMIT 10
");

echo "<h3>Sample apartment fees data (first 10 records):</h3>";
echo "<table border='1' style='font-size: 12px;'>";
echo "<tr>
    <th>ID</th>
    <th>Apt</th>
    <th>Month</th>
    <th>Total (current)</th>
    <th>Restante Cote</th>
    <th>Restante Fond</th>
    <th>Restante Penalizari</th>
    <th>Total Amount (calc)</th>
    <th>Utilities</th>
    <th>Previous Unpaid</th>
    <th>Penalties</th>
    <th>Fond Special</th>
</tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['apartment_number'] . "</td>";
    echo "<td>" . $row['month_key'] . "</td>";
    echo "<td style='background-color: #e6ffe6;'>" . number_format($row['total'], 2) . "</td>";
    echo "<td>" . number_format($row['restante_cote'], 2) . "</td>";
    echo "<td>" . number_format($row['restante_fond_rulment'], 2) . "</td>";
    echo "<td>" . number_format($row['restante_fond_penalizari'], 2) . "</td>";
    echo "<td style='background-color: #ffe6e6;'>" . number_format($row['total_amount'], 2) . "</td>";
    echo "<td>" . number_format($row['utilities'], 2) . "</td>";
    echo "<td>" . number_format($row['previous_unpaid'], 2) . "</td>";
    echo "<td>" . number_format($row['penalties'], 2) . "</td>";
    echo "<td>" . number_format($row['fond_special'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Issue Explanation</h2>";
echo "<div style='background-color: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h4>The Problem:</h4>";
echo "<ul>";
echo "<li><strong>total</strong> (green column) = Current month's fee only</li>";
echo "<li><strong>total_amount</strong> (red column) = Current month fee + ALL unpaid previous amounts</li>";
echo "<li>When selecting multiple months for payment, using total_amount causes double-counting of unpaid amounts</li>";
echo "<li>Each month's total_amount already includes previous unpaid amounts, so selecting multiple months adds them multiple times</li>";
echo "</ul>";
echo "<h4>The Solution:</h4>";
echo "<ul>";
echo "<li>Use <strong>total</strong> field for current month fees when selecting individual months</li>";
echo "<li>Show unpaid amounts separately for transparency</li>";
echo "<li>Calculate outstanding balance correctly without double-counting</li>";
echo "</ul>";
echo "</div>";

// Check for existing payments to understand the current payment structure
echo "<h2>Payment System Analysis</h2>";
$result = $conn->query("
    SELECT 
        p.id as payment_id,
        p.amount,
        p.fund_type,
        p.payment_date,
        pl.apartment_fee_id,
        af.month_key,
        af.total,
        af.total_amount
    FROM payments p
    JOIN payment_links pl ON p.id = pl.payment_id
    LEFT JOIN apartment_fees af ON pl.apartment_fee_id = af.id
    ORDER BY p.payment_date DESC
    LIMIT 5
");

echo "<h3>Recent payments analysis:</h3>";
echo "<table border='1'>";
echo "<tr><th>Payment ID</th><th>Amount</th><th>Fund Type</th><th>Date</th><th>Fee ID</th><th>Month</th><th>Fee Total</th><th>Fee Total Amount</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $key => $value) {
        if (in_array($key, ['amount', 'total', 'total_amount'])) {
            echo "<td>" . number_format($value, 2) . "</td>";
        } else {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
    }
    echo "</tr>";
}
echo "</table>";

echo "<h2>Recommended Fix Implementation</h2>";
echo "<div style='background-color: #e6f3ff; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
echo "<h4>Changes needed in add_payment.php:</h4>";
echo "<ol>";
echo "<li>Modify apartment fees query to show individual fee components</li>";
echo "<li>Display current month fee (total) separately from unpaid amounts</li>";
echo "<li>Update payment calculation to avoid double-counting</li>";
echo "<li>Improve user interface to clearly show what is being paid</li>";
echo "</ol>";
echo "</div>";
?>
