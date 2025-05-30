<?php
require 'auth.php';
require 'db.php';

$credit_id = (int) ($_GET['credit_id'] ?? 0);

if (!$credit_id) {
    echo '<div class="alert alert-danger">Invalid credit ID.</div>';
    exit;
}

// Get credit details
$credit_query = "
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
WHERE pc.id = ?
";

$stmt = $conn->prepare($credit_query);
$stmt->bind_param('i', $credit_id);
$stmt->execute();
$credit = $stmt->get_result()->fetch_assoc();

if (!$credit) {
    echo '<div class="alert alert-danger">Credit not found.</div>';
    exit;
}

// Get usage history
$usage_query = "
SELECT 
    cu.*,
    p.payment_date,
    p.amount as payment_amount
FROM credit_usage cu
JOIN payments p ON cu.used_payment_id = p.id
WHERE cu.credit_id = ?
ORDER BY cu.used_date DESC
";

$stmt = $conn->prepare($usage_query);
$stmt->bind_param('i', $credit_id);
$stmt->execute();
$usage_history = $stmt->get_result();
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold">Credit Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Credit ID:</strong></td>
                <td>#<?php echo $credit['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Apartment:</strong></td>
                <td>Scara <?php echo $credit['scara']; ?> - Apt <?php echo $credit['apartment_number']; ?></td>
            </tr>
            <tr>
                <td><strong>Owner:</strong></td>
                <td><?php echo htmlspecialchars($credit['owner_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Original Payment:</strong></td>
                <td>#<?php echo $credit['payment_id']; ?> (<?php echo date('d-m-Y', strtotime($credit['payment_date'])); ?>)</td>
            </tr>
            <tr>
                <td><strong>Fund Type:</strong></td>
                <td>
                    <span class="badge bg-primary">
                        <?php echo ucfirst(str_replace('_', ' ', $credit['fund_type'])); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Original Credit:</strong></td>
                <td><strong><?php echo number_format($credit['credit_amount'], 2); ?> RON</strong></td>
            </tr>
            <tr>
                <td><strong>Remaining:</strong></td>
                <td><strong class="text-success"><?php echo number_format($credit['remaining_amount'], 2); ?> RON</strong></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="badge bg-<?php echo $credit['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($credit['status']); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="fw-bold">Usage History</h6>
        <?php if ($usage_history->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Used Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($usage = $usage_history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($usage['used_date'])); ?></td>
                                        <td>#<?php echo $usage['used_payment_id']; ?></td>
                                        <td class="text-end"><?php echo number_format($usage['used_amount'], 2); ?> RON</td>
                                    </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
        <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    This credit has not been used yet.
                </div>
        <?php endif; ?>
    </div>
</div>
