<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['contract_id']) || !is_numeric($_GET['contract_id'])) {
    echo "Invalid contract ID.";
    exit;
}

$contract_id = (int) $_GET['contract_id'];

// Fetch contract details
$sql_contract = "SELECT tc.*, t.name AS tenant_name, s.name AS space_name, a.name AS account_name
                FROM tenant_contracts tc
                JOIN tenants t ON tc.tenant_id = t.id
                JOIN spaces s ON tc.space_id = s.id
                JOIN accounts a ON tc.account_id = a.id
                WHERE tc.id = ?";

if ($stmt = $conn->prepare($sql_contract)) {
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result_contract = $stmt->get_result();
    
    if ($result_contract->num_rows === 0) {
        echo "Contract not found.";
        exit;
    }
    
    $contract = $result_contract->fetch_assoc();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Fetch attachments for this contract
$sql_attachments = "SELECT * FROM tenant_contract_attachments WHERE contract_id = ? ORDER BY upload_date DESC";
if ($stmt = $conn->prepare($sql_attachments)) {
    $stmt->bind_param("i", $contract_id);
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
    <h1 class="mb-4">Contract Attachments</h1>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Contract Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Tenant:</strong> <?php echo htmlspecialchars($contract['tenant_name']); ?></p>
                    <p><strong>Space:</strong> <?php echo htmlspecialchars($contract['space_name']); ?></p>
                    <p><strong>Purpose:</strong> <?php echo htmlspecialchars($contract['purpose']); ?></p>
                    <p><strong>Amount:</strong> <?php echo htmlspecialchars($contract['amount']) . ' ' . htmlspecialchars($contract['currency']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Start Date:</strong> <?php echo htmlspecialchars($contract['start_date']); ?></p>
                    <p><strong>End Date:</strong> <?php echo htmlspecialchars($contract['end_date']); ?></p>
                    <p><strong>Payment Frequency:</strong> <?php echo ucfirst(htmlspecialchars($contract['payment_frequency'])); ?></p>
                    <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($contract['status'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attachments</h5>
            <a href="edit_tenant_contract.php?id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm">Add New Attachment</a>
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
                                        <a href="edit_tenant_contract.php?id=<?php echo $contract_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No attachments found for this contract.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="tenant_contracts.php?tenant_id=<?php echo $contract['tenant_id']; ?>" class="btn btn-secondary">Back to Contracts</a>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>