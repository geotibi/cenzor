<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Fetch tenants from the database
$sql = "SELECT t.id, t.name, t.contact_person, t.phone, t.email, 
               (SELECT COUNT(*) FROM tenant_contracts WHERE tenant_id = t.id AND status = 'active') AS active_contracts,
               (SELECT COUNT(*) FROM tenant_invoices WHERE tenant_id = t.id AND status = 'unpaid') AS unpaid_invoices
        FROM tenants t
        ORDER BY t.name";
$result = $conn->query($sql);

require 'header.php'; // Include the header
?>

<div class="container-fluid">
    <h1 class="mb-4">Tenants</h1>
    
    <!-- Button to Add a New Tenant -->
    <a href="add_tenant.php" class="btn btn-success mb-3">Add New Tenant</a>
    
    <!-- Filters -->
    <div class="mb-3">
        <button id="filter-active" class="btn btn-success btn-sm">With Active Contracts</button>
        <button id="filter-unpaid" class="btn btn-danger btn-sm">With Unpaid Invoices</button>
        <button id="clear-filters" class="btn btn-secondary btn-sm">Clear Filters</button>
    </div>

    <!-- Tenants Table -->
    <table id="tenants_table" class="table table-bordered table-striped table-hover w-100">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Contact Person</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Active Contracts</th>
                <th>Unpaid Invoices</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td data-search="<?php echo $row['active_contracts'] > 0 ? 'Yes' : 'No'; ?>">
                            <?php if ($row['active_contracts'] > 0): ?>
                                <span class="badge bg-success"><?php echo $row['active_contracts']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">0</span>
                            <?php endif; ?>
                        </td>
                        <td data-search="<?php echo $row['unpaid_invoices'] > 0 ? 'Yes' : 'No'; ?>">
                            <?php if ($row['unpaid_invoices'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $row['unpaid_invoices']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_tenant.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="tenant_contracts.php?tenant_id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">Contracts</a>
                                <a href="tenant_invoices.php?tenant_id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Invoices</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No tenants found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#tenants_table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25
    });

    // Filter buttons
    $('#filter-active').click(function() {
        table.column(5).search('Yes', true, false).draw();  // Match tenants with active contracts
    });

    $('#filter-unpaid').click(function() {
        table.column(6).search('Yes', true, false).draw();  // Match tenants with unpaid invoices
    });

    $('#clear-filters').click(function() {
        table.columns().search('').draw(); // Clear all filters
    });
});
</script>

<?php require 'footer.php'; // Include the footer ?>