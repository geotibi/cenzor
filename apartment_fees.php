<?php
require 'auth.php';
require 'db.php';

// Initialize filter variables
$filter_apartment = isset($_GET['apartment_id']) ? (int)$_GET['apartment_id'] : 0;
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Get all apartments for the filter dropdown
$apartments_query = "SELECT id, number, owner_name FROM apartments ORDER BY number";
$apartments_result = $conn->query($apartments_query);

// Build the query based on filters
$query = "SELECT af.*, a.number as apartment_number, a.owner_name,
          (SELECT COALESCE(SUM(pl.amount), 0) 
           FROM payment_links pl 
           JOIN payments p ON pl.payment_id = p.id 
           WHERE pl.apartment_fee_id = af.id) as paid_amount
          FROM apartment_fees af
          JOIN apartments a ON af.apartment_id = a.id
          WHERE 1=1";

$params = [];
$types = "";

if ($filter_apartment > 0) {
    $query .= " AND af.apartment_id = ?";
    $params[] = $filter_apartment;
    $types .= "i";
}

if ($filter_month) {
    $query .= " AND af.month_key = ?";
    $params[] = $filter_month;
    $types .= "s";
}

if ($filter_status) {
    if ($filter_status == 'paid') {
        $query .= " AND (SELECT COALESCE(SUM(pl.amount), 0) 
                         FROM payment_links pl 
                         JOIN payments p ON pl.payment_id = p.id 
                         WHERE pl.apartment_fee_id = af.id) >= af.total_amount";
    } elseif ($filter_status == 'partial') {
        $query .= " AND (SELECT COALESCE(SUM(pl.amount), 0) 
                         FROM payment_links pl 
                         JOIN payments p ON pl.payment_id = p.id 
                         WHERE pl.apartment_fee_id = af.id) > 0 
                    AND (SELECT COALESCE(SUM(pl.amount), 0) 
                         FROM payment_links pl 
                         JOIN payments p ON pl.payment_id = p.id 
                         WHERE pl.apartment_fee_id = af.id) < af.total_amount";
    } elseif ($filter_status == 'unpaid') {
        $query .= " AND (SELECT COALESCE(SUM(pl.amount), 0) 
                         FROM payment_links pl 
                         JOIN payments p ON pl.payment_id = p.id 
                         WHERE pl.apartment_fee_id = af.id) = 0";
    }
}

$query .= " ORDER BY af.month_key DESC, a.number ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Function to determine payment status
function getPaymentStatus($paid_amount, $total_amount) {
    if ($paid_amount >= $total_amount) {
        return 'paid';
    } elseif ($paid_amount > 0) {
        return 'partial';
    } else {
        return 'unpaid';
    }
}

// Function to get status badge HTML
function getStatusBadge($status) {
    switch ($status) {
        case 'paid':
            return '<span class="badge bg-success">Paid</span>';
        case 'partial':
            return '<span class="badge bg-warning">Partially Paid</span>';
        case 'unpaid':
            return '<span class="badge bg-danger">Unpaid</span>';
        default:
            return '';
    }
}

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',') . ' RON';
}

// Get available months for filter
$months_query = "SELECT DISTINCT month_key FROM apartment_fees ORDER BY month_key DESC";
$months_result = $conn->query($months_query);

require 'header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">Apartment Fees Management</h1>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="apartment_id" class="form-label">Apartment</label>
                    <select name="apartment_id" id="apartment_id" class="form-select">
                        <option value="0">All Apartments</option>
                        <?php while ($apartment = $apartments_result->fetch_assoc()): ?>
                            <option value="<?php echo $apartment['id']; ?>" <?php echo ($filter_apartment == $apartment['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($apartment['number'] . ' - ' . $apartment['owner_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select name="month" id="month" class="form-select">
                        <option value="">All Months</option>
                        <?php while ($month = $months_result->fetch_assoc()): ?>
                            <option value="<?php echo $month['month_key']; ?>" <?php echo ($filter_month == $month['month_key']) ? 'selected' : ''; ?>>
                                <?php 
                                    $year = substr($month['month_key'], 0, 4);
                                    $m = substr($month['month_key'], 5, 2);
                                    $month_name = date('F', mktime(0, 0, 0, $m, 1, $year));
                                    echo $month_name . ' ' . $year; 
                                ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Payment Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="paid" <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo ($filter_status == 'partial') ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="unpaid" <?php echo ($filter_status == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="apartment_fees.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add New Fee Button -->
    <div class="mb-4">
        <a href="add_apartment_fee.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Fee
        </a>
    </div>
    
    <!-- Fees Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Apartment Fees</h5>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Apartment</th>
                                <th>Owner</th>
                                <th>Month</th>
                                <th>Issued Date</th>
                                <th>Deadline</th>
                                <th>Utilities</th>
                                <th>Special Fund</th>
                                <th>Previous Unpaid</th>
                                <th>Penalties</th>
                                <th>Total</th>
                                <th>Paid Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                $payment_status = getPaymentStatus($row['paid_amount'], $row['total_amount']);
                                $status_badge = getStatusBadge($payment_status);
                            ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['apartment_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                                    <td>
                                        <?php 
                                            $year = substr($row['month_key'], 0, 4);
                                            $month = substr($row['month_key'], 5, 2);
                                            echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); 
                                        ?>
                                    </td>
                                    <td><?php echo $row['issued_date']; ?></td>
                                    <td><?php echo $row['payment_deadline']; ?></td>
                                    <td><?php echo formatCurrency($row['utilities']); ?></td>
                                    <td><?php echo formatCurrency($row['fond_special']); ?></td>
                                    <td><?php echo formatCurrency($row['previous_unpaid']); ?></td>
                                    <td><?php echo formatCurrency($row['penalties']); ?></td>
                                    <td class="fw-bold"><?php echo formatCurrency($row['total_amount']); ?></td>
                                    <td class="fw-bold"><?php echo formatCurrency($row['paid_amount']); ?></td>
                                    <td><?php echo $status_badge; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_apartment_fee.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="view_fee_payments.php?fee_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-credit-card"></i> Payments
                                            </a>
                                            <?php if ($payment_status != 'paid'): ?>
                                                <a href="add_payment.php?link_type=fee&link_ids[]=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-cash"></i> Add Payment
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No apartment fees found matching the selected criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <?php
    // Reset result pointer
    $result->data_seek(0);
    
    $total_fees = 0;
    $total_paid = 0;
    $total_unpaid = 0;
    $count_paid = 0;
    $count_partial = 0;
    $count_unpaid = 0;
    
    while ($row = $result->fetch_assoc()) {
        $total_fees += $row['total_amount'];
        $total_paid += $row['paid_amount'];
        
        $status = getPaymentStatus($row['paid_amount'], $row['total_amount']);
        if ($status == 'paid') {
            $count_paid++;
        } elseif ($status == 'partial') {
            $count_partial++;
        } else {
            $count_unpaid++;
        }
    }
    
    $total_unpaid = $total_fees - $total_paid;
    ?>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">Total Fees</h5>
                    <p class="card-text fs-4"><?php echo formatCurrency($total_fees); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Paid</h5>
                    <p class="card-text fs-4"><?php echo formatCurrency($total_paid); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Unpaid</h5>
                    <p class="card-text fs-4"><?php echo formatCurrency($total_unpaid); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Payment Status</h5>
                    <p class="card-text">
                        <span class="badge bg-success"><?php echo $count_paid; ?> Paid</span>
                        <span class="badge bg-warning"><?php echo $count_partial; ?> Partial</span>
                        <span class="badge bg-danger"><?php echo $count_unpaid; ?> Unpaid</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this apartment fee? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    document.getElementById('confirmDeleteBtn').href = 'delete_apartment_fee.php?id=' + id;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>

<?php require 'footer.php'; ?>