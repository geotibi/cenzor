<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get current month and year if not specified
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// Generate month/year options for the dropdown
$start_year = 2025;
$end_year = date('Y') + 1; // Current year plus one
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Define the bill type groups with their display names
$bill_groups = [
    1 => ['name' => '1. Gunoi menajer - URBAN', 'pattern' => '%Urban%'],
    2 => ['name' => '2. Gaze naturale - ENGIE', 'pattern' => '%Engie%'],
    3 => ['name' => '3. Energie electrică - PPC', 'pattern' => '%PPC%'],
    4 => ['name' => '4. Apă rece - Apa Nova', 'pattern' => '%Apa Nova%'],
    5 => ['name' => '5. Apă caldă - TEB', 'patterns' => ['TEB A', 'TEB B', 'TEB C']], // Special case with multiple exact patterns
    6 => ['name' => '6. Comision bancar', 'pattern' => '%Comision%'],
    7 => ['name' => '7. Salarii - Indemnizatie Membrii Comitet', 'pattern' => '%INDEMNIZATIE MEMBRU COMITET%'],
    8 => ['name' => '7. Salarii - Impozite - Bugetul de Stat', 'pattern' => '%BUGETUL DE STAT%'],
    9 => ['name' => '8. Servicii de curățenie - Viomar', 'pattern' => '%VIOMAR%'],
    10 => ['name' => '9. Administrare - Scomet', 'pattern' => '%SCOMET%'],
    11 => ['name' => '10. Service ascensor - Elevator', 'pattern' => '%Elevator%'],
    12 => ['name' => '11. Service interfon - Interada', 'pattern' => '%Interada%'],
    13 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    14 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    15 => ['name' => '14. Comision plăți online', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    16 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    17 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    18 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    19 => ['name' => '12. Cenzorat', 'pattern' => '%INDEMNIZATIE CENZOR%'],
    20 => ['name' => 'Other', 'pattern' => '%']
];

// Query to get the data
$query = "
SELECT 
  id,
  bill_type,
  bill_no,
  amount,
  bill_date,
  bill_deadline,
  account_id,
  description,
  repartizare_luna,
  NULL AS furnizor_id,
  13 AS sort_order -- default value, will be overridden by CASE
FROM building_bills
WHERE DATE_FORMAT(repartizare_luna, '%Y-%c') = ? 

UNION ALL

SELECT
  id,
  'Comision' AS bill_type,
  NULL AS bill_no,
  amount,
  payment_date AS bill_date,
  payment_date AS bill_deadline,
  account_id,
  notes AS description,
  payment_date AS repartizare_luna,
  NULL AS furnizor_id,
  14 AS sort_order
FROM payments
WHERE fund_type = 'comision' AND DATE_FORMAT(payment_date, '%Y-%c') = ?

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
    WHEN bill_type LIKE '%Comision%' THEN 13
    ELSE 14
  END,
  bill_type
";

$stmt = $conn->prepare($query);
$date_param = "$current_year-$current_month";
$stmt->bind_param("ss", $date_param, $date_param); // Bind twice
$stmt->execute();
$result = $stmt->get_result();

// Group the results by bill type group
$grouped_bills = [];
while ($row = $result->fetch_assoc()) {
    $group_id = 19; // Default to "Other"
    $bill_type = $row['bill_type'];
    
    // Special handling for TEB pattern - must match exactly TEB A, TEB B, or TEB C
    if ($bill_groups[5]['patterns']) {
        foreach ($bill_groups[5]['patterns'] as $teb_pattern) {
            // Check if bill_type contains the exact pattern (case insensitive)
            if (preg_match('/\b' . preg_quote($teb_pattern, '/') . '\b/i', $bill_type)) {
                $group_id = 5;
                break;
            }
        }
    }
    
    // If not already matched as TEB, check other patterns
    if ($group_id == 19) {
        foreach ($bill_groups as $id => $group) {
            // Skip the special TEB case which we already handled
            if ($id == 5) continue;
            
            if (isset($group['pattern']) && stripos($bill_type, str_replace('%', '', $group['pattern'])) !== false) {
                $group_id = $id;
                break;
            }
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

require 'header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-4">Anexă - Listă întreținere</h1>
            
            <!-- Month/Year Selector Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="month-year-select" class="form-label">Selectează luna și anul:</label>
                            <select id="month-year-select" class="form-select" name="month_year" onchange="updateMonthYear(this.value)">
                                <?php
                                for ($year = $end_year; $year >= $start_year; $year--) {
                                    for ($month = 12; $month >= 1; $month--) {
                                        $selected = ($year == $current_year && $month == $current_month) ? 'selected' : '';
                                        echo "<option value=\"$year-$month\" $selected>{$months[$month]} $year</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">View</button>
                        </div>
                        
                        <!-- Hidden fields for form submission -->
                        <input type="hidden" id="year-input" name="year" value="<?php echo $current_year; ?>">
                        <input type="hidden" id="month-input" name="month" value="<?php echo $current_month; ?>">
                    </form>
                </div>
            </div>
            
            <!-- Bills Display -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Anexă listă de plată pentru <?php echo $months[$current_month] . ' ' . $current_year; ?></h5>
                    <div>
                        <a href="export_anexa.php?year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>" class="btn btn-sm btn-light">
                            <i class="bi bi-file-excel"></i> Export to Excel
                        </a>
                        <a href="print_anexa.php?year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>" class="btn btn-sm btn-light ms-2" target="_blank">
                            <i class="bi bi-printer"></i> Print
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                        <?php foreach ($grouped_bills as $group_id => $group): ?>
                            <?php if (!empty($group['bills'])): ?>
                                <div class="bill-group mb-4">
                                    <h4 class="bill-group-title bg-light p-2 rounded">
                                        <?php echo $group['name']; ?> 
                                        <span class="badge bg-primary float-end">
                                            Total: <?php echo number_format($group['total'], 2); ?> RON
                                        </span>
                                    </h4>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Bill Type</th>
                                                    <th>Bill No</th>
                                                    <th>Bill Date</th>
                                                    <th>Bill Deadline</th>
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
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Grand Total -->
                        <div class="card bg-dark text-white mt-4">
                            <div class="card-body">
                                <h4 class="mb-0">Grand Total: <?php echo number_format($total_amount, 2); ?> RON</h4>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            No bills found for <?php echo $months[$current_month] . ' ' . $current_year; ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to update hidden form fields when the dropdown changes
    function updateMonthYear(value) {
        const [year, month] = value.split('-');
        document.getElementById('year-input').value = year;
        document.getElementById('month-input').value = month;
    }
    
    // Initialize DataTables for better table functionality
    $(document).ready(function() {
        $('.table').DataTable({
            paging: false,
            searching: false,
            info: false,
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [5] } // Disable sorting on description column
            ]
        });
    });
</script>

<?php require 'footer.php'; ?>