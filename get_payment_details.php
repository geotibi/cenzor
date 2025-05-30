<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$payment_id = (int) ($_GET['payment_id'] ?? 0);

if (!$payment_id) {
    echo '<div class="alert alert-danger">Invalid payment ID.</div>';
    exit;
}

// Get payment details
$payment_query = "
SELECT 
    p.*,
    a.number as apartment_number,
    a.scara,
    a.owner_name,
    acc.name as account_name
FROM payments p
LEFT JOIN apartments a ON p.apartment_id = a.id
LEFT JOIN accounts acc ON p.account_id = acc.id
WHERE p.id = ?
";

$stmt = $conn->prepare($payment_query);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    echo '<div class="alert alert-danger">Payment not found.</div>';
    exit;
}

// Get payment links
$links_query = "
SELECT 
    pl.*,
    e.description as expense_description,
    af.month_key as fee_month,
    ti.invoice_number,
    t.name as tenant_name,
    bb.bill_type,
    bb.bill_no
FROM payment_links pl
LEFT JOIN expenses e ON pl.expense_id = e.id
LEFT JOIN apartment_fees af ON pl.apartment_fee_id = af.id
LEFT JOIN tenant_invoices ti ON pl.tenant_invoice_id = ti.id
LEFT JOIN tenants t ON ti.tenant_id = t.id
LEFT JOIN building_bills bb ON pl.building_bills_id = bb.id
WHERE pl.payment_id = ?
ORDER BY pl.id
";

$stmt = $conn->prepare($links_query);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$links = $stmt->get_result();

$total_links_amount = 0;
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold">Payment Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Payment ID:</strong></td>
                <td>#<?php echo $payment['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td><strong><?php echo number_format($payment['amount'], 2); ?> RON</strong></td>
            </tr>
            <tr>
                <td><strong>Fund Type:</strong></td>
                <td>
                    <span class="badge bg-primary">
                        <?php echo ucfirst(str_replace('_', ' ', $payment['fund_type'])); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Account:</strong></td>
                <td><?php echo htmlspecialchars($payment['account_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Apartment:</strong></td>
                <td>
                    <?php if ($payment['apartment_number']): ?>
                        Scara <?php echo $payment['scara']; ?> - Apt <?php echo $payment['apartment_number']; ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($payment['owner_name']); ?></small>
                    <?php else: ?>
                        <span class="text-muted">No apartment</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($payment['notes']): ?>
                <tr>
                    <td><strong>Notes:</strong></td>
                    <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="fw-bold">Payment Links</h6>
        <?php if ($links->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Fund Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($link = $links->fetch_assoc()): ?>
                            <?php
                            $total_links_amount += $link['amount'];
                            $type = '';
                            $description = '';

                            if ($link['expense_id']) {
                                $type = 'Expense';
                                $description = $link['expense_description'];
                            } elseif ($link['apartment_fee_id']) {
                                $type = 'Fee';
                                $description = 'Month: ' . $link['fee_month'];
                            } elseif ($link['tenant_invoice_id']) {
                                $type = 'Invoice';
                                $description = $link['tenant_name'] . ' - ' . $link['invoice_number'];
                            } elseif ($link['building_bills_id']) {
                                $type = 'Bill';
                                $description = $link['bill_type'] . ' - ' . $link['bill_no'];
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $type; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($description); ?></td>
                                <td class="text-end"><?php echo number_format($link['amount'], 2); ?> RON</td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo ucfirst(str_replace('_', ' ', $link['fund_type'])); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-dark">
                            <th colspan="2">Total Links Amount:</th>
                            <th class="text-end"><?php echo number_format($total_links_amount, 2); ?> RON</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No payment links found for this payment.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold">Discrepancy Analysis</h6>
                <?php
                $discrepancy = $payment['amount'] - $total_links_amount;
                if ($discrepancy == 0) {
                    echo '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Payment amount matches total links amount perfectly.</div>';
                } elseif ($discrepancy > 0) {
                    echo '<div class="alert alert-info"><i class="bi bi-arrow-down-circle"></i> Payment is <strong>' . number_format($discrepancy, 2) . ' RON</strong> higher than total links amount (UNDERPAID links).</div>';
                } else {
                    echo '<div class="alert alert-warning"><i class="bi bi-arrow-up-circle"></i> Payment is <strong>' . number_format(abs($discrepancy), 2) . ' RON</strong> lower than total links amount (OVERPAID links).</div>';
                }
                ?>

                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="border p-3 rounded">
                            <h5 class="text-primary"><?php echo number_format($payment['amount'], 2); ?> RON</h5>
                            <small class="text-muted">Payment Amount</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border p-3 rounded">
                            <h5 class="text-info"><?php echo number_format($total_links_amount, 2); ?> RON</h5>
                            <small class="text-muted">Links Total</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border p-3 rounded">
                            <h5 class="<?php echo $discrepancy == 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo number_format(abs($discrepancy), 2); ?> RON
                            </h5>
                            <small class="text-muted">Discrepancy</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>