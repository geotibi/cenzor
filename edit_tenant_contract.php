<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/contracts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid contract ID.";
    exit;
}

$contract_id = (int) $_GET['id'];

// Fetch contract details
$sql = "SELECT * FROM tenant_contracts WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "Contract not found.";
        exit;
    }
    
    $contract = $result->fetch_assoc();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Fetch tenants for dropdown
$sql_tenants = "SELECT id, name FROM tenants ORDER BY name";
$result_tenants = $conn->query($sql_tenants);

// Fetch spaces for dropdown
$sql_spaces = "SELECT id, name FROM spaces ORDER BY name";
$result_spaces = $conn->query($sql_spaces);

// Fetch accounts for dropdown
$sql_accounts = "SELECT id, name FROM accounts ORDER BY name";
$result_accounts = $conn->query($sql_accounts);

// Fetch attachments for this contract
$sql_attachments = "SELECT * FROM tenant_contract_attachments WHERE contract_id = ?";
if ($stmt_attachments = $conn->prepare($sql_attachments)) {
    $stmt_attachments->bind_param("i", $contract_id);
    $stmt_attachments->execute();
    $result_attachments = $stmt_attachments->get_result();
    $stmt_attachments->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Handle attachment deletion
if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment'])) {
    $attachment_id = (int) $_GET['delete_attachment'];
    
    // Get the file path before deleting the record
    $sql_get_attachment = "SELECT file_path FROM tenant_contract_attachments WHERE id = ? AND contract_id = ?";
    if ($stmt_get = $conn->prepare($sql_get_attachment)) {
        $stmt_get->bind_param("ii", $attachment_id, $contract_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        if ($result_get->num_rows > 0) {
            $attachment = $result_get->fetch_assoc();
            $file_path = $attachment['file_path'];
            
            // Delete the record from the database
            $sql_delete = "DELETE FROM tenant_contract_attachments WHERE id = ? AND contract_id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("ii", $attachment_id, $contract_id);
                
                if ($stmt_delete->execute()) {
                    // Delete the file from the server
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    // Redirect to refresh the page
                    header("Location: edit_tenant_contract.php?id=$contract_id");
                    exit;
                }
                
                $stmt_delete->close();
            }
        }
        
        $stmt_get->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $tenant_id = (int) $_POST['tenant_id'];
    $space_id = (int) $_POST['space_id'];
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $end_date = $conn->real_escape_string($_POST['end_date']);
    $amount = (float) $_POST['amount'];
    $utilities_fee = (float) $_POST['utilities_fee'];
    $currency = $conn->real_escape_string($_POST['currency']);
    $payment_deadline = (int) $_POST['payment_deadline'];
    
    // Sanitize and validate payment_frequency
    $payment_frequency = $conn->real_escape_string($_POST['payment_frequency']);
    if ($payment_frequency !== 'monthly' && $payment_frequency !== 'yearly') {
        $payment_frequency = 'monthly'; // Default to monthly if invalid
    }
    
    $account_id = (int) $_POST['account_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Update the SQL query
    $sql = "UPDATE tenant_contracts SET 
            tenant_id = ?, 
            space_id = ?, 
            purpose = ?, 
            start_date = ?, 
            end_date = ?, 
            amount = ?, 
            utilities_fee = ?, 
            currency = ?, 
            payment_deadline = ?, 
            payment_frequency = ?, 
            account_id = ?, 
            status = ?, 
            notes = ? 
            WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Fixed: Correct type definition string with 13 parameters
        $stmt->bind_param("iisssddsissssi", $tenant_id, $space_id, $purpose, $start_date, $end_date, $amount, $utilities_fee, $currency, $payment_deadline, $payment_frequency, $account_id, $status, $notes, $contract_id);
        
        if ($stmt->execute()) {
            // Handle file upload if a file was selected
            if (isset($_FILES['contract_attachment']) && $_FILES['contract_attachment']['error'] == 0) {
                $file_name = $_FILES['contract_attachment']['name'];
                $file_tmp = $_FILES['contract_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Create a unique filename
                    $new_file_name = 'contract_' . $contract_id . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert file information into the database
                        $sql_attachment = "INSERT INTO tenant_contract_attachments (contract_id, file_name, file_path) VALUES (?, ?, ?)";
                        if ($stmt_attachment = $conn->prepare($sql_attachment)) {
                            $stmt_attachment->bind_param("iss", $contract_id, $file_name, $file_path);
                            $stmt_attachment->execute();
                            $stmt_attachment->close();
                        }
                    }
                }
            }
            
            // Redirect to contracts list
            header('Location: tenant_contracts.php?tenant_id=' . $tenant_id);
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Edit Contract</h1>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id" required>
                        <option value="">Select Tenant</option>
                        <?php while ($row = $result_tenants->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($contract['tenant_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="space_id" class="form-label">Space</label>
                    <select class="form-select" id="space_id" name="space_id" required>
                        <option value="">Select Space</option>
                        <?php while ($row = $result_spaces->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($contract['space_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="purpose" class="form-label">Purpose</label>
                    <input type="text" class="form-control" id="purpose" name="purpose" value="<?php echo htmlspecialchars($contract['purpose']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($contract['start_date']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($contract['end_date']); ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($contract['amount']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Rent Amount</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($contract['amount']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="utilities_fee" class="form-label">Utilities Fee (Optional)</label>
                    <input type="number" step="0.01" class="form-control" id="utilities_fee" name="utilities_fee" value="<?php echo htmlspecialchars($contract['utilities_fee']); ?>">
                </div>
                <div class="mb-3">
                    <label for="currency" class="form-label">Currency</label>
                    <select class="form-select" id="currency" name="currency">
                        <option value="RON" <?php echo ($contract['currency'] == 'RON') ? 'selected' : ''; ?>>RON</option>
                        <option value="EUR" <?php echo ($contract['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                        <option value="USD" <?php echo ($contract['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="payment_deadline" class="form-label">Payment Deadline (Day of Month)</label>
                    <input type="number" min="1" max="31" class="form-control" id="payment_deadline" name="payment_deadline" value="<?php echo htmlspecialchars($contract['payment_deadline']); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="payment_frequency" class="form-label">Payment Frequency</label>
                    <select class="form-select" id="payment_frequency" name="payment_frequency">
                        <option value="monthly" <?php echo ($contract['payment_frequency'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo ($contract['payment_frequency'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">Select Account</option>
                        <?php while ($row = $result_accounts->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($contract['account_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?php echo ($contract['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo ($contract['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="terminated" <?php echo ($contract['status'] == 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($contract['notes']); ?></textarea>
        </div>
        
        <!-- Current Attachments -->
        <?php if ($result_attachments && $result_attachments->num_rows > 0): ?>
            <div class="mb-3">
                <label class="form-label">Current Attachments</label>
                <div class="list-group">
                    <?php while ($attachment = $result_attachments->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark"></i> 
                                <?php echo htmlspecialchars($attachment['file_name']); ?>
                            </div>
                            <div>
                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" class="btn btn-sm btn-primary" target="_blank">View</a>
                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" class="btn btn-sm btn-success" download>Download</a>
                                <a href="edit_tenant_contract.php?id=<?php echo $contract_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No attachments found for this contract.</div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label for="contract_attachment" class="form-label">Add New Attachment (PDF, DOC, JPG)</label>
            <input type="file" class="form-control" id="contract_attachment" name="contract_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>
        
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-lg">Save Changes</button>
            <a href="tenant_contracts.php?tenant_id=<?php echo $contract['tenant_id']; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require 'footer.php'; // Include the footer ?>