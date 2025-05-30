<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create payment_credits table if it doesn't exist
$create_credits_table = "
CREATE TABLE IF NOT EXISTS payment_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    payment_id INT NOT NULL,
    credit_amount DECIMAL(10,2) NOT NULL,
    remaining_amount DECIMAL(10,2) NOT NULL,
    fund_type ENUM('ball_fund', 'utilities', 'special_fund', 'penalties', 'previous_unpaid', 'other') NOT NULL,
    created_date DATE NOT NULL,
    notes TEXT,
    status ENUM('active', 'fully_used', 'expired') DEFAULT 'active',
    FOREIGN KEY (apartment_id) REFERENCES apartments(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_apartment_status (apartment_id, status),
    INDEX idx_fund_type (fund_type)
)";

$conn->query($create_credits_table);

// Create credit_usage table to track how credits are used
$create_usage_table = "
CREATE TABLE IF NOT EXISTS credit_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_id INT NOT NULL,
    used_payment_id INT NOT NULL,
    used_amount DECIMAL(10,2) NOT NULL,
    used_date DATE NOT NULL,
    notes TEXT,
    FOREIGN KEY (credit_id) REFERENCES payment_credits(id),
    FOREIGN KEY (used_payment_id) REFERENCES payments(id)
)";

$conn->query($create_usage_table);

require 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                <i class="bi bi-credit-card text-success"></i>
                Payment Credits Management
            </h1>

            <!-- Summary Cards -->
            <?php
            $summary_query = "
            SELECT 
                COUNT(*) as total_credits,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_credits,
                SUM(CASE WHEN status = 'active' THEN remaining_amount ELSE 0 END) as total_available_credit,
                SUM(credit_amount) as total_credit_generated
            FROM payment_credits
            ";
            $summary = $conn->query($summary_query)->fetch_assoc();
            ?>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total Credits</h5>
                            <h3><?php echo number_format($summary['total_credits']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Active Credits</h5>
                            <h3><?php echo number_format($summary['active_credits']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Available Amount</h5>
                            <h3><?php echo number_format($summary['total_available_credit'], 2); ?> RON</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total Generated</h5>
                            <h3><?php echo number_format($summary['total_credit_generated'], 2); ?> RON</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Credits Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check"></i>
                        Active Payment Credits
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $credits_query = "
                    SELECT 
                        pc.*,
                        a.number as apartment_number,
                        a.scara,
                        a.owner_name,
                        p.payment_date,
                        p.amount as payment_amount
                    FROM payment_credits pc
                    JOIN apartments a ON pc.apartment_id = a.id
                    JOIN payments p ON pc.payment_id = p.id
                    WHERE pc.status = 'active' AND pc.remaining_amount > 0
                    ORDER BY pc.created_date ASC
                    ";
                    $credits = $conn->query($credits_query);
                    ?>

                    <?php if ($credits->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Credit ID</th>
                                            <th>Apartment</th>
                                            <th>Original Payment</th>
                                            <th>Fund Type</th>
                                            <th>Credit Amount</th>
                                            <th>Remaining</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($credit = $credits->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?php echo $credit['id']; ?></strong></td>
                                                    <td>
                                                        Scara <?php echo $credit['scara']; ?> - Apt <?php echo $credit['apartment_number']; ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($credit['owner_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        Payment #<?php echo $credit['payment_id']; ?>
                                                        <br><small class="text-muted"><?php echo date('d-m-Y', strtotime($credit['payment_date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo ucfirst(str_replace('_', ' ', $credit['fund_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end"><?php echo number_format($credit['credit_amount'], 2); ?> RON</td>
                                                    <td class="text-end">
                                                        <strong class="text-success"><?php echo number_format($credit['remaining_amount'], 2); ?> RON</strong>
                                                    </td>
                                                    <td><?php echo date('d-m-Y', strtotime($credit['created_date'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewCreditHistory(<?php echo $credit['id']; ?>)">
                                                            <i class="bi bi-eye"></i> History
                                                        </button>
                                                    </td>
                                                </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                    <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                No active payment credits found.
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Credit History Modal -->
<div class="modal fade" id="creditHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Credit Usage History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="creditHistoryContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function viewCreditHistory(creditId) {
    $('#creditHistoryContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
    $('#creditHistoryModal').modal('show');
    
    fetch(`get_credit_history.php?credit_id=${creditId}`)
        .then(response => response.text())
        .then(data => {
            $('#creditHistoryContent').html(data);
        })
        .catch(error => {
            $('#creditHistoryContent').html('<div class="alert alert-danger">Error loading credit history.</div>');
        });
}
</script>

<?php require 'footer.php'; ?>
