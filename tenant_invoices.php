<?php
require 'auth.php';
require 'db.php';

// Check if tenant_id or contract_id is provided
$filter = "";
$title = "All Invoices";

if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
    $tenant_id = (int) $_GET['tenant_id'];
    $filter = "WHERE ti.tenant_id = $tenant_id";
    
    // Get tenant name
    $sql_tenant = "SELECT name FROM tenants WHERE id = $tenant_id";
    $result_tenant = $conn->query($sql_tenant);
    if ($result_tenant && $result_tenant->num_rows > 0) {
        $tenant = $result_tenant->fetch_assoc();
        $title = "Invoices for " . $tenant['name'];
    }
} elseif (isset($_GET['contract_id']) && is_numeric($_GET['contract_id'])) {
    $contract_id = (int) $_GET['contract_id'];
    $filter = "WHERE ti.contract_id = $contract_id";
    
    // Get contract details
    $sql_contract = "SELECT tc.id, t.id AS tenant_id, t.name AS tenant_name, s.name AS space_name 
                     FROM tenant_contracts tc
                     JOIN tenants t ON tc.tenant_id = t.id
                     JOIN spaces s ON tc.space_id = s.id
                     WHERE tc.id = $contract_id";
    $result_contract = $conn->query($sql_contract);
    if ($result_contract && $result_contract->num_rows > 0) {
        $contract = $result_contract->fetch_assoc();
        $tenant_id = $contract['tenant_id'];
        $title = "Invoices for " . $contract['tenant_name'] . " - " . $contract['space_name'];
    }
}

// Fetch invoices
$sql = "SELECT ti.id, ti.tenant_id, t.name AS tenant_name, ti.contract_id, 
               s.name AS space_name, ti.invoice_number, ti.amount, ti.currency, 
               ti.issue_date, ti.due_date, ti.period_start, ti.period_end, 
               ti.status, ti.payment_date, ti.payment_amount, a.name AS account_name, ti.rent_amount, ti.utilities_amount,
               (SELECT COUNT(*) FROM tenant_invoice_attachments WHERE invoice_id = ti.id) AS attachment_count
        FROM tenant_invoices ti
        JOIN tenants t ON ti.tenant_id = t.id
        JOIN tenant_contracts tc ON ti.contract_id = tc.id
        JOIN spaces s ON tc.space_id = s.id
        JOIN accounts a ON ti.account_id = a.id
        $filter
        ORDER BY ti.due_date DESC";

$result = $conn->query($sql);

require 'header.php'; // Include the header
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <?php if (isset($tenant_id)): ?>
            <a href="tenants.php" class="btn btn-secondary">Back to Tenants</a>
        <?php elseif (isset($contract_id)): ?>
            <a href="tenant_contracts.php?tenant_id=<?php echo $tenant_id; ?>" class="btn btn-secondary">Back to Contracts</a>
        <?php endif; ?>
    </div>
    
    <!-- Button to Add a New Invoice -->
    <div class="mb-3">
        <?php if (isset($tenant_id)): ?>
            <a href="add_tenant_invoice.php?tenant_id=<?php echo $tenant_id; ?>" class="btn btn-success">Add New Invoice</a>
        <?php elseif (isset($contract_id)): ?>
            <a href="add_tenant_invoice.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success">Add New Invoice</a>
        <?php else: ?>
            <a href="add_tenant_invoice.php" class="btn btn-success">Add New Invoice</a>
        <?php endif; ?>
    </div>
    
    <!-- Filters -->
    <div class="mb-3">
        <button id="filter-paid" class="btn btn-success btn-sm">Paid Invoices</button>
        <button id="filter-unpaid" class="btn btn-danger btn-sm">Unpaid Invoices</button>
        <button id="filter-partial" class="btn btn-warning btn-sm">Partially Paid</button>
        <button id="filter-overdue" class="btn btn-dark btn-sm">Overdue</button>
        <button id="filter-attachments" class="btn btn-info btn-sm">Has Attachments</button>
        <button id="clear-filters" class="btn btn-secondary btn-sm">Clear Filters</button>
    </div>

    <!-- Invoices Table -->
    <table id="invoices_table" class="table table-bordered table-striped table-hover w-100">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tenant</th>
                <th>Space</th>
                <th>Invoice #</th>
                <th>Rent Amount</th>
                <th>Utilities Amount</th>
                <th>Total Amount</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Period</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Account</th>
                <th>Attachments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $is_overdue = ($row['status'] != 'paid' && strtotime($row['due_date']) < time());
                ?>
                    <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['space_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['rent_amount']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['utilities_amount']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['amount']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['issue_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['period_start']) . ' to ' . htmlspecialchars($row['period_end']); ?></td>
                        <td data-search="<?php echo ucfirst($row['status']) . ($is_overdue ? ' Overdue' : ''); ?>">
                            <?php if ($row['status'] == 'paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif ($row['status'] == 'partial'): ?>
                                <span class="badge bg-warning">Partial</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?php echo $is_overdue ? 'Overdue' : 'Unpaid'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['payment_date']): ?>
                                <?php echo htmlspecialchars($row['payment_date']); ?>
                                <?php if ($row['payment_amount']): ?>
                                    <br><?php echo htmlspecialchars($row['payment_amount']) . ' ' . htmlspecialchars($row['currency']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                        <td data-search="<?php echo $row['attachment_count'] > 0 ? 'Yes' : 'No'; ?>">
                            <?php if ($row['attachment_count'] > 0): ?>
                                <span class="text-success">&#10004; <?php echo $row['attachment_count']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">&#10006; None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_tenant_invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <?php if ($row['attachment_count'] > 0): ?>
                                    <a href="view_invoice_attachments.php?invoice_id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">Files</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13">No invoices found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#invoices_table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25
    });

    // Filter buttons
    $('#filter-paid').click(function() {
        table.column(8).search('Paid', true, false).draw();
    });

    $('#filter-unpaid').click(function() {
        table.column(8).search('Unpaid', true, false).draw();
    });

    $('#filter-partial').click(function() {
        table.column(8).search('Partial', true, false).draw();
    });

    $('#filter-overdue').click(function() {
        table.column(8).search('Overdue', true, false).draw();
    });
    
    $('#filter-attachments').click(function() {
        table.column(11).search('Yes', true, false).draw();
    });

    $('#clear-filters').click(function() {
        table.columns().search('').draw(); // Clear all filters
    });
});
</script>

<?php require 'footer.php'; // Include the footer ?>