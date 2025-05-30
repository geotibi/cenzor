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
$filter_status = $_GET['filter_status'] ?? 'all';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$filter_apartment = $_GET['filter_apartment'] ?? '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_date_from) {
    $where_conditions[] = "p.payment_date >= ?";
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if ($filter_date_to) {
    $where_conditions[] = "p.payment_date <= ?";
    $params[] = $filter_date_to;
    $param_types .= 's';
}

if ($filter_apartment) {
    $where_conditions[] = "p.apartment_id = ?";
    $params[] = (int) $filter_apartment;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Main query to check for discrepancies
$query = "
SELECT 
    p.id as payment_id,
    p.apartment_id,
    p.amount as payment_amount,
    p.fund_type as payment_fund_type,
    p.payment_date,
    p.notes,
    a.number as apartment_number,
    a.scara,
    a.owner_name,
    acc.name as account_name,
    COALESCE(SUM(pl.amount), 0) as total_links_amount,
    COUNT(pl.id) as links_count,
    CASE 
        WHEN COALESCE(SUM(pl.amount), 0) = p.amount THEN 'MATCH'
        WHEN COALESCE(SUM(pl.amount), 0) > p.amount THEN 'OVERPAID'
        WHEN COALESCE(SUM(pl.amount), 0) < p.amount THEN 'UNDERPAID'
        ELSE 'NO_LINKS'
    END as status,
    ABS(p.amount - COALESCE(SUM(pl.amount), 0)) as discrepancy_amount
FROM payments p
LEFT JOIN apartments a ON p.apartment_id = a.id
LEFT JOIN accounts acc ON p.account_id = acc.id
LEFT JOIN payment_links pl ON p.id = pl.payment_id
$where_clause
GROUP BY p.id, p.apartment_id, p.amount, p.fund_type, p.payment_date, p.notes, 
         a.number, a.scara, a.owner_name, acc.name
";

// Add status filter to HAVING clause
if ($filter_status !== 'all') {
    $status_condition = '';
    switch ($filter_status) {
        case 'match':
            $status_condition = 'HAVING COALESCE(SUM(pl.amount), 0) = p.amount';
            break;
        case 'discrepancy':
            $status_condition = 'HAVING COALESCE(SUM(pl.amount), 0) != p.amount';
            break;
        case 'overpaid':
            $status_condition = 'HAVING COALESCE(SUM(pl.amount), 0) > p.amount';
            break;
        case 'underpaid':
            $status_condition = 'HAVING COALESCE(SUM(pl.amount), 0) < p.amount';
            break;
        case 'no_links':
            $status_condition = 'HAVING COUNT(pl.id) = 0';
            break;
    }
    $query .= " $status_condition";
}

$query .= " ORDER BY p.payment_date DESC, p.id DESC";

// Prepare and execute the query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Get summary statistics
$summary_query = "
SELECT 
    COUNT(*) AS total_payments,
    SUM(CASE WHEN COALESCE(pl_sum, 0) = amount THEN 1 ELSE 0 END) AS matching_payments,
    SUM(CASE WHEN COALESCE(pl_sum, 0) != amount THEN 1 ELSE 0 END) AS discrepant_payments,
    SUM(CASE WHEN COALESCE(pl_sum, 0) > amount THEN 1 ELSE 0 END) AS overpaid_payments,
    SUM(CASE WHEN COALESCE(pl_sum, 0) < amount THEN 1 ELSE 0 END) AS underpaid_payments,
    SUM(CASE WHEN pl_count = 0 THEN 1 ELSE 0 END) AS payments_without_links,
    SUM(ABS(amount - COALESCE(pl_sum, 0))) AS total_discrepancy_amount
FROM (
    SELECT 
        p.id,
        p.amount,
        SUM(pl.amount) AS pl_sum,
        COUNT(pl.id) AS pl_count
    FROM payments p
    LEFT JOIN payment_links pl ON p.id = pl.payment_id
    $where_clause
    GROUP BY p.id, p.amount
) AS sub
";


if (!empty($params)) {
    $summary_stmt = $conn->prepare($summary_query);
    $summary_stmt->bind_param($param_types, ...$params);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
} else {
    $summary_result = $conn->query($summary_query);
}

// Calculate summary totals
$total_payments = 0;
$matching_payments = 0;
$discrepant_payments = 0;
$overpaid_payments = 0;
$underpaid_payments = 0;
$payments_without_links = 0;
$total_discrepancy_amount = 0;

while ($row = $summary_result->fetch_assoc()) {
    $total_payments++;
    $matching_payments += $row['matching_payments'];
    $discrepant_payments += $row['discrepant_payments'];
    $overpaid_payments += $row['overpaid_payments'];
    $underpaid_payments += $row['underpaid_payments'];
    $payments_without_links += $row['payments_without_links'];
    $total_discrepancy_amount += $row['total_discrepancy_amount'];
}

// Get apartments for filter dropdown
$apartments = $conn->query("SELECT id, number, scara, owner_name FROM apartments ORDER BY scara, number");

require 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                Payment Discrepancy Monitor
            </h1>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total Payments</h5>
                            <h3><?php echo number_format($total_payments); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Matching</h5>
                            <h3><?php echo number_format($matching_payments); ?></h3>
                            <small><?php echo $total_payments > 0 ? round(($matching_payments / $total_payments) * 100, 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Discrepancies</h5>
                            <h3><?php echo number_format($discrepant_payments); ?></h3>
                            <small><?php echo $total_payments > 0 ? round(($discrepant_payments / $total_payments) * 100, 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h5 class="card-title">Overpaid</h5>
                            <h3><?php echo number_format($overpaid_payments); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Underpaid</h5>
                            <h3><?php echo number_format($underpaid_payments); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">No Links</h5>
                            <h3><?php echo number_format($payments_without_links); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($total_discrepancy_amount > 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Total Discrepancy Amount: <?php echo number_format($total_discrepancy_amount, 2); ?>
                        RON</strong>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel"></i>
                        Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="filter_status" class="form-label">Status</label>
                            <select id="filter_status" name="filter_status" class="form-select">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="match" <?php echo $filter_status === 'match' ? 'selected' : ''; ?>>Matching
                                </option>
                                <option value="discrepancy" <?php echo $filter_status === 'discrepancy' ? 'selected' : ''; ?>>Discrepancies</option>
                                <option value="overpaid" <?php echo $filter_status === 'overpaid' ? 'selected' : ''; ?>>
                                    Overpaid</option>
                                <option value="underpaid" <?php echo $filter_status === 'underpaid' ? 'selected' : ''; ?>>
                                    Underpaid</option>
                                <option value="no_links" <?php echo $filter_status === 'no_links' ? 'selected' : ''; ?>>No
                                    Links</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter_date_from" class="form-label">Date From</label>
                            <input type="date" id="filter_date_from" name="filter_date_from" class="form-control"
                                value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter_date_to" class="form-label">Date To</label>
                            <input type="date" id="filter_date_to" name="filter_date_to" class="form-control"
                                value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_apartment" class="form-label">Apartment</label>
                            <select id="filter_apartment" name="filter_apartment" class="form-select">
                                <option value="">All Apartments</option>
                                <?php
                                $apartments->data_seek(0);
                                while ($row = $apartments->fetch_assoc()) {
                                    $selected = $filter_apartment == $row['id'] ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>Scara {$row['scara']} - Apt {$row['number']} - {$row['owner_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-table"></i>
                        Payment Discrepancy Details
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="discrepancyTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Date</th>
                                        <th>Apartment</th>
                                        <th>Account</th>
                                        <th>Fund Type</th>
                                        <th>Payment Amount</th>
                                        <th>Links Amount</th>
                                        <th>Links Count</th>
                                        <th>Discrepancy</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($row['status']) {
                                            case 'MATCH':
                                                $status_class = 'bg-success text-white';
                                                $status_icon = 'bi-check-circle';
                                                break;
                                            case 'OVERPAID':
                                                $status_class = 'bg-warning text-dark';
                                                $status_icon = 'bi-arrow-up-circle';
                                                break;
                                            case 'UNDERPAID':
                                                $status_class = 'bg-info text-white';
                                                $status_icon = 'bi-arrow-down-circle';
                                                break;
                                            case 'NO_LINKS':
                                                $status_class = 'bg-secondary text-white';
                                                $status_icon = 'bi-x-circle';
                                                break;
                                        }
                                        ?>
                                        <tr class="<?php echo $row['status'] !== 'MATCH' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong>#<?php echo $row['payment_id']; ?></strong>
                                            </td>
                                            <td><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                                            <td>
                                                <?php if ($row['apartment_number']): ?>
                                                    Scara <?php echo $row['scara']; ?> - Apt <?php echo $row['apartment_number']; ?>
                                                    <br><small
                                                        class="text-muted"><?php echo htmlspecialchars($row['owner_name']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">No apartment</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['payment_fund_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong><?php echo number_format($row['payment_amount'], 2); ?> RON</strong>
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($row['total_links_amount'], 2); ?> RON
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $row['links_count']; ?></span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($row['discrepancy_amount'] > 0): ?>
                                                    <span class="text-danger fw-bold">
                                                        <?php echo number_format($row['discrepancy_amount'], 2); ?> RON
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-success">0.00 RON</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <i class="bi <?php echo $status_icon; ?>"></i>
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                        onclick="viewPaymentDetails(<?php echo $row['payment_id']; ?>)"
                                                        title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($row['status'] !== 'MATCH'): ?>
                                                        <button type="button" class="btn btn-outline-warning"
                                                            onclick="fixDiscrepancy(<?php echo $row['payment_id']; ?>)"
                                                            title="Fix Discrepancy">
                                                            <i class="bi bi-tools"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No payment records found matching the selected criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTable
    $(document).ready(function () {
        $('#discrepancyTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [10] } // Actions column
            ],
            responsive: true,
            language: {
                search: "Search payments:",
                lengthMenu: "Show _MENU_ payments per page",
                info: "Showing _START_ to _END_ of _TOTAL_ payments",
                infoEmpty: "No payments found",
                infoFiltered: "(filtered from _MAX_ total payments)"
            }
        });
    });

    function viewPaymentDetails(paymentId) {
        // Show loading
        $('#paymentDetailsContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
        $('#paymentDetailsModal').modal('show');

        // Load payment details via AJAX
        fetch(`get_payment_details.php?payment_id=${paymentId}`)
            .then(response => response.text())
            .then(data => {
                $('#paymentDetailsContent').html(data);
            })
            .catch(error => {
                $('#paymentDetailsContent').html('<div class="alert alert-danger">Error loading payment details.</div>');
            });
    }

    function fixDiscrepancy(paymentId) {
        if (confirm('This will redirect you to edit the payment. Continue?')) {
            window.location.href = `edit_payment.php?id=${paymentId}`;
        }
    }

    // Auto-refresh every 5 minutes
    setTimeout(function () {
        location.reload();
    }, 300000);
</script>

<style>
    .table-warning {
        --bs-table-bg: #fff3cd;
    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .badge {
        font-size: 0.75em;
    }

    .btn-group-sm>.btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
</style>

<?php require 'footer.php'; ?>