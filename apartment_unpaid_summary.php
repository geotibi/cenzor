<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        COALESCE(SUM(af.total_amount), 0) - COALESCE(SUM(IFNULL(pl.amount, 0)), 0) AS unpaid_amount,
        MAX(af.month_key) AS latest_month
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
    $filter_scara = $conn->real_escape_string($filter_scara);
    $query .= " AND a.scara = '$filter_scara'";
}

if (!empty($filter_month) && !empty($filter_year)) {
    $month_key = sprintf("%04d-%02d", $filter_year, $filter_month);
    $month_key = $conn->real_escape_string($month_key);
    $query .= " AND af.month_key = '$month_key'";
} else if (!empty($filter_year)) {
    $filter_year = $conn->real_escape_string($filter_year);
    $query .= " AND af.month_key LIKE '$filter_year%'";
}

// Complete the query with GROUP BY
$query .= " GROUP BY a.id, a.number, a.scara, a.owner_name";

// Add HAVING clause for payment status filtering
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

// Get unique scara values for the filter dropdown
$scaras = $conn->query("SELECT DISTINCT scara FROM apartments ORDER BY scara");

// Get years and months for filter dropdowns
$years = [];
$current_year = date('Y');
for ($i = $current_year - 2; $i <= $current_year + 1; $i++) {
    $years[] = $i;
}

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

require 'header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Apartment Unpaid Summary</h1>
            <p class="lead">Overview of all apartments and their unpaid amounts</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="scara" class="form-label">Building Section</label>
                    <select name="scara" id="scara" class="form-select">
                        <option value="">All Sections</option>
                        <?php while ($scara = $scaras->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($scara['scara']) ?>" <?= $filter_scara == $scara['scara'] ? 'selected' : '' ?>>
                                Section <?= htmlspecialchars($scara['scara']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Payment Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="partial" <?= $filter_status === 'partial' ? 'selected' : '' ?>>Partially Paid
                        </option>
                        <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select name="month" id="month" class="form-select">
                        <option value="">All Months</option>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $filter_month == $num ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select name="year" id="year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= $filter_year == $year ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="apartment_unpaid_summary.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <?php
        // Calculate summary statistics
        $total_unpaid = 0;
        $total_apartments = 0;
        $apartments_with_debt = 0;

        if ($result && $result->num_rows > 0) {
            $total_apartments = $result->num_rows;

            while ($row = $result->fetch_assoc()) {
                if ($row['unpaid_amount'] > 0) {
                    $total_unpaid += $row['unpaid_amount'];
                    $apartments_with_debt++;
                }
            }

            // Reset the result pointer
            $result->data_seek(0);
        }
        ?>

        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Unpaid Amount</h5>
                    <h2 class="display-6"><?= number_format($total_unpaid, 2) ?> RON</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Apartments</h5>
                    <h2 class="display-6"><?= $total_apartments ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Apartments With Debt</h5>
                    <h2 class="display-6"><?= $apartments_with_debt ?>
                        (<?= $total_apartments > 0 ? round(($apartments_with_debt / $total_apartments) * 100) : 0 ?>%)
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Apartments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Apartments and Unpaid Amounts</h5>
            <div>
                <a href="export_unpaid_summary.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"
                    class="btn btn-sm btn-success">
                    <i class="bi bi-file-excel"></i> Export
                </a>
                <a href="print_unpaid_summary.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"
                    class="btn btn-sm btn-secondary" target="_blank">
                    <i class="bi bi-printer"></i> Print
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="apartmentsTable">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Apartment</th>
                            <th>Owner</th>
                            <th>Total Fees</th>
                            <th>Total Paid</th>
                            <th>Unpaid Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>Section <?= htmlspecialchars($row['scara']) ?></td>
                                    <td><?= htmlspecialchars($row['number']) ?></td>
                                    <td><?= htmlspecialchars($row['owner_name']) ?></td>
                                    <td class="text-end"><?= number_format($row['total_fees'], 2) ?> RON</td>
                                    <td class="text-end"><?= number_format($row['total_paid'], 2) ?> RON</td>
                                    <td
                                        class="text-end fw-bold <?= $row['unpaid_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= number_format($row['unpaid_amount'], 2) ?> RON
                                    </td>
                                    <td>
                                        <?php if ($row['unpaid_amount'] <= 0): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($row['total_paid'] > 0): ?>
                                            <span class="badge bg-warning">Partial</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="apartment_fees.php?apartment_id=<?= $row['apartment_id'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-list-ul"></i> View Fees
                                            </a>
                                            <a href="add_payment.php?apartment_id=<?= $row['apartment_id'] ?>&link_type=fee"
                                                class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-cash"></i> Add Payment
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No apartments found matching the criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Initialize Select2 for better dropdown experience
        $('#scara, #status, #month, #year').select2({
            theme: 'bootstrap-5'
        });

        // Initialize DataTables for better table functionality
        $('#apartmentsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc'], [1, 'asc']], // Sort by section, then apartment number
            columnDefs: [
                { orderable: false, targets: [7] } // Disable sorting on actions column
            ]
        });
    });
</script>

<?php require 'footer.php'; ?>