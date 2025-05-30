<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    echo "Invalid invoice ID.";
    exit;
}

$invoice_id = (int) $_GET['invoice_id'];

// Fetch invoice details
$sql_invoice = "SELECT ti.*, t.name AS tenant_name, tc.purpose, s.name AS space_name, a.name AS account_name
                FROM tenant_invoices ti
                JOIN tenants t ON ti.tenant_id = t.id
                JOIN tenant_contracts tc ON ti.contract_id = tc.id
                JOIN spaces s ON tc.space_id = s.id
                JOIN accounts a ON ti.account_id = a.id
                WHERE ti.id = ?";

if ($stmt = $conn->prepare($sql_invoice)) {
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result_invoice = $stmt->get_result();
    
    if ($result_invoice->num_rows === 0) {
        echo "Invoice not found.";
        exit;
    }
    
    $invoice = $result_invoice->fetch_assoc();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Fetch attachments for this invoice
$sql_attachments = "SELECT * FROM tenant_invoice_attachments WHERE invoice_id = ? ORDER BY upload_date DESC";
if ($stmt = $conn->prepare($sql_attachments)) {
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result_attachments = $stmt->get_result();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Invoice Attachments</h1>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Invoice Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Tenant:</strong> <?php echo htmlspecialchars($invoice['tenant_name']); ?></p>
                    <p><strong>Space:</strong> <?php echo htmlspecialchars($invoice['space_name']); ?></p>
                    <p><strong>Purpose:</strong> <?php echo htmlspecialchars($invoice['purpose']); ?></p>
                    <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                    <p><strong>Amount:</strong> <?php echo htmlspecialchars($invoice['amount']) . ' ' . htmlspecialchars($invoice['currency']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Issue Date:</strong> <?php echo htmlspecialchars($invoice['issue_date']); ?></p>
                    <p><strong>Due Date:</strong> <?php echo htmlspecialchars($invoice['due_date']); ?></p>
                    <p><strong>Period:</strong> <?php echo htmlspecialchars($invoice['period_start']) . ' to ' . htmlspecialchars($invoice['period_end']); ?></p>
                    <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($invoice['status'])); ?></p>
                    <p><strong>Account:</strong> <?php echo htmlspecialchars($invoice['account_name']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attachments</h5>
            <a href="edit_tenant_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary btn-sm">Add New Attachment</a>
        </div>
        <div class="card-body">
            <?php if ($result_attachments && $result_attachments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($attachment = $result_attachments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attachment['file_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($attachment['upload_date'])); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" class="btn btn-sm btn-primary" target="_blank">View</a>
                                        <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" class="btn btn-sm btn-success" download>Download</a>
                                        <a href="edit_tenant_invoice.php?id=<?php echo $invoice_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No attachments found for this invoice.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="tenant_invoices.php?tenant_id=<?php echo $invoice['tenant_id']; ?>" class="btn btn-secondary">Back to Invoices</a>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>