<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Define months for the dashboard (e.g., 2025-03 for March 2025)
$months = [
    '2025-04' => 'Aprilie 2025',
    '2025-05' => 'Mai 2025',
    '2025-06' => 'Iunie 2025'
];

// Get the list of suppliers (furnizori)
$furnizori_sql = "SELECT id, nume FROM furnizori ORDER BY nume";
$furnizori_result = $conn->query($furnizori_sql);

// Prepare data structure to hold the status for each month and furnizor
$furnizori_data = [];
while ($furnizor = $furnizori_result->fetch_assoc()) {
    $furnizori_data[$furnizor['id']] = $furnizor['nume'];
}

// Get the status of each invoice for each furnizor per month
$invoice_status_sql = "
    SELECT 
        DATE_FORMAT(BB.bill_date, '%Y-%m') AS bill_month,
        BB.furnizor_id,
        BB.id AS bill_id,
        BB.amount AS bill_amount,
        IFNULL(PL.amount, 0) AS paid_amount
    FROM 
        building_bills BB
    LEFT JOIN 
        payment_links PL ON BB.id = PL.building_bills_id
    WHERE
        BB.bill_date >= '2025-03-01' AND BB.bill_date <= '2025-06-30'  -- Define the months range here
    ORDER BY 
        BB.bill_date DESC
";

$invoice_result = $conn->query($invoice_status_sql);

// Prepare data structure for monthly statuses
$monthly_statuses = [];
$current_month = date('Y-m'); // Get the current month (e.g., 2025-04)

while ($row = $invoice_result->fetch_assoc()) {
    $month = $row['bill_month'];
    $furnizor_id = $row['furnizor_id'];
    $status = 'Emisa'; // Default

    // Determine the status based on payment comparison for ALL months
    if ($row['paid_amount'] >= $row['bill_amount']) {
        $status = 'Platita'; // Paid
    } elseif ($row['paid_amount'] > 0 && $row['paid_amount'] < $row['bill_amount']) {
        $status = 'Platita partial'; // Partially paid
    } elseif ($row['paid_amount'] == 0) {
        $status = 'Pending'; // No payment
    }

    // Store the status for each furnizor and month
    $monthly_statuses[$month][$furnizor_id] = $status;
}

// Assuming you have arrays: $all_furnizors and $expected_months
$expected_months = [];
foreach ($expected_months as $month) {
    if ($month < $current_month) { // Only for past months
        foreach ($all_furnizors as $furnizor_id) {
            if (!isset($monthly_statuses[$month][$furnizor_id])) {
                $monthly_statuses[$month][$furnizor_id] = 'Lipsa'; // Missing invoice
            }
        }
    }
}

$page_title = "Dashboard facturi";
require 'header.php'; // Include the header
?>

<style>
    .status-platita {
        background-color: #28a745 !important; /* Green */
        color: white !important;
    }
    .status-pending {
        background-color: #fd7e14 !important; /* Orange */
        color: white !important;
    }
    .status-emisa {
        background-color: #ffc107 !important; /* Yellow */
        color: black !important;
    }
    .status-lipsa {
        background-color: #dc3545 !important; /* Red */
        color: white !important;
    }

    /* Add some additional styles for debugging purposes */
    .debug-cell {
        border: 1px solid #000;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Dashboard Status Facturi</h1>
            <p class="lead">Statusul facturilor de la furnizori împărțite pe luni.</p>
            
            <!-- Invoice Status Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            <?php foreach ($furnizori_data as $furnizor_id => $furnizor_name): ?>
                                <th><?php echo $furnizor_name; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($months as $month_key => $month_label): ?>
                            <tr>
                                <td><?php echo $month_label; ?></td>
                                <?php foreach ($furnizori_data as $furnizor_id => $furnizor_name): ?>
                                    <td class="
                                        <?php
                                            // Assign CSS class based on the status
                                            if (isset($monthly_statuses[$month_key][$furnizor_id])) {
                                                $status = $monthly_statuses[$month_key][$furnizor_id];
                                                if ($status == 'Platita') {
                                                    echo 'status-platita debug-cell';
                                                } elseif ($status == 'Platita partial') {
                                                    echo 'status-platita debug-cell';
                                                } elseif ($status == 'Emisa') {
                                                    echo 'status-emisa debug-cell';
                                                } elseif ($status == 'Lipsa') {
                                                    echo 'status-lipsa debug-cell';
                                                } else {
                                                    echo 'status-pending debug-cell';
                                                }
                                            } else {
                                                echo 'status-lipsa debug-cell'; // No invoice found -> "Facturi lipsa"
                                            }
                                        ?>
                                    ">
                                        <?php
                                        // Display the status for each furnizor in the respective month
                                        echo isset($monthly_statuses[$month_key][$furnizor_id]) ? $monthly_statuses[$month_key][$furnizor_id] : 'Lipsa'; // Default "Facturi lipsa" if no invoice
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>
