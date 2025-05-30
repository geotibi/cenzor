<?php
require 'auth.php';
require 'db.php';

// Check if tenant_id is provided
$tenant_filter = "";
$tenant_name = "All Tenants";

if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
    $tenant_id = (int) $_GET['tenant_id'];
    $tenant_filter = "WHERE tc.tenant_id = $tenant_id";
    
    // Get tenant name
    $sql_tenant = "SELECT name FROM tenants WHERE id = $tenant_id";
    $result_tenant = $conn->query($sql_tenant);
    if ($result_tenant && $result_tenant->num_rows > 0) {
        $tenant = $result_tenant->fetch_assoc();
        $tenant_name = $tenant['name'];
    }
}

// Fetch contracts
$sql = "SELECT tc.id, tc.tenant_id, t.name AS tenant_name, s.name AS space_name, 
               tc.purpose, tc.start_date, tc.end_date, tc.amount, tc.currency, 
               tc.payment_frequency, a.name AS account_name, tc.status, tc.utilities_fee,
               (SELECT COUNT(*) FROM tenant_contract_attachments WHERE contract_id = tc.id) AS attachment_count
        FROM tenant_contracts tc
        JOIN tenants t ON tc.tenant_id = t.id
        JOIN spaces s ON tc.space_id = s.id
        JOIN accounts a ON tc.account_id = a.id
        $tenant_filter
        ORDER BY tc.status, tc.end_date DESC";

$result = $conn->query($sql);

require 'header.php'; // Include the header
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Contracts for <?php echo htmlspecialchars($tenant_name); ?></h1>
        <?php if (isset($tenant_id)): ?>
            <a href="tenants.php" class="btn btn-secondary">Back to Tenants</a>
        <?php endif; ?>
    </div>
    
    <!-- Button to Add a New Contract -->
    <div class="mb-3">
        <?php if (isset($tenant_id)): ?>
            <a href="add_tenant_contract.php?tenant_id=<?php echo $tenant_id; ?>" class="btn btn-success">Add New Contract</a>
        <?php else: ?>
            <a href="add_tenant_contract.php" class="btn btn-success">Add New Contract</a>
        <?php endif; ?>
    </div>
    
    <!-- Filters -->
    <div class="mb-3">
        <button id="filter-active" class="btn btn-success btn-sm">Active Contracts</button>
        <button id="filter-expired" class="btn btn-warning btn-sm">Expired Contracts</button>
        <button id="filter-terminated" class="btn btn-danger btn-sm">Terminated Contracts</button>
        <button id="filter-attachments" class="btn btn-info btn-sm">Has Attachments</button>
        <button id="clear-filters" class="btn btn-secondary btn-sm">Clear Filters</button>
    </div>

    <!-- Contracts Table -->
    <table id="contracts_table" class="table table-bordered table-striped table-hover w-100">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tenant</th>
                <th>Space</th>
                <th>Purpose</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Rent Amount</th>
                <th>Utilities Fee</th>
                <th>Total</th>
                <th>Frequency</th>
                <th>Account</th>
                <th>Status</th>
                <th>Attachments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['space_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['amount']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['utilities_fee']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['amount'] + $row['utilities_fee']) . ' ' . htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($row['payment_frequency'])); ?></td>
                        <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                        <td data-search="<?php echo ucfirst($row['status']); ?>">
                            <?php if ($row['status'] == 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($row['status'] == 'expired'): ?>
                                <span class="badge bg-warning">Expired</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Terminated</span>
                            <?php endif; ?>
                        </td>
                        <td data-search="<?php echo $row['attachment_count'] > 0 ? 'Yes' : 'No'; ?>">
                            <?php if ($row['attachment_count'] > 0): ?>
                                <span class="text-success">&#10004; <?php echo $row['attachment_count']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">&#10006; None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_tenant_contract.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="tenant_invoices.php?contract_id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Invoices</a>
                                <?php if ($row['attachment_count'] > 0): ?>
                                    <a href="view_contract_attachments.php?contract_id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">Files</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">No contracts found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#contracts_table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25
    });

    // Filter buttons
    $('#filter-active').click(function() {
        table.column(9).search('Active', true, false).draw();
    });

    $('#filter-expired').click(function() {
        table.column(9).search('Expired', true, false).draw();
    });

    $('#filter-terminated').click(function() {
        table.column(9).search('Terminated', true, false).draw();
    });
    
    $('#filter-attachments').click(function() {
        table.column(10).search('Yes', true, false).draw();
    });

    $('#clear-filters').click(function() {
        table.columns().search('').draw(); // Clear all filters
    });
});
</script>

<?php require 'footer.php'; // Include the footer ?>