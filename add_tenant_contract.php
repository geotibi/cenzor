<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/contracts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get tenant_id from URL if provided
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

// Fetch tenants for dropdown
$sql_tenants = "SELECT id, name FROM tenants ORDER BY name";
$result_tenants = $conn->query($sql_tenants);

// Fetch spaces for dropdown
$sql_spaces = "SELECT id, name FROM spaces ORDER BY name";
$result_spaces = $conn->query($sql_spaces);

// Fetch accounts for dropdown
$sql_accounts = "SELECT id, name FROM accounts ORDER BY name";
$result_accounts = $conn->query($sql_accounts);

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
    
    // Insert new contract
    $sql = "INSERT INTO tenant_contracts (tenant_id, space_id, purpose, start_date, end_date, amount, utilities_fee, currency, payment_deadline, payment_frequency, account_id, status, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        // Fixed: Correct type definition string with 12 parameters
        $stmt->bind_param("iisssddsisiss", $tenant_id, $space_id, $purpose, $start_date, $end_date, $amount, $utilities_fee, $currency, $payment_deadline, $payment_frequency, $account_id, $status, $notes);
        
        if ($stmt->execute()) {
            $contract_id = $conn->insert_id;
            
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
            header('Location: tenant_contracts.php' . ($tenant_id ? '?tenant_id=' . $tenant_id : ''));
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
    <h1 class="mb-4">Add New Contract</h1>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id" required>
                        <option value="">Select Tenant</option>
                        <?php while ($row = $result_tenants->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($tenant_id == $row['id']) ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $row['id']; ?>">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="purpose" class="form-label">Purpose</label>
                    <input type="text" class="form-control" id="purpose" name="purpose" required>
                </div>
                
                <div class="mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>
                
                <div class="mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Rent Amount</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>

                <div class="mb-3">
                    <label for="utilities_fee" class="form-label">Utilities Fee (Optional)</label>
                    <input type="number" step="0.01" class="form-control" id="utilities_fee" name="utilities_fee" value="0.00">
                </div>
                <div class="mb-3">
                    <label for="currency" class="form-label">Currency</label>
                    <select class="form-select" id="currency" name="currency">
                        <option value="RON">RON</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="payment_deadline" class="form-label">Payment Deadline (Day of Month)</label>
                    <input type="number" min="1" max="31" class="form-control" id="payment_deadline" name="payment_deadline" value="15">
                </div>
                
                <div class="mb-3">
                    <label for="payment_frequency" class="form-label">Payment Frequency</label>
                    <select class="form-select" id="payment_frequency" name="payment_frequency">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">Select Account</option>
                        <?php while ($row = $result_accounts->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
        </div>
        
        <div class="mb-3">
            <label for="contract_attachment" class="form-label">Contract Document (PDF, DOC, JPG)</label>
            <input type="file" class="form-control" id="contract_attachment" name="contract_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Contract</button>
            <a href="tenant_contracts.php<?php echo $tenant_id ? '?tenant_id=' . $tenant_id : ''; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Set default dates
    var today = new Date();
    var startDate = new Date(today);
    var endDate = new Date(today);
    endDate.setFullYear(endDate.getFullYear() + 1); // Default to 1 year contract
    
    $('#start_date').val(startDate.toISOString().split('T')[0]);
    $('#end_date').val(endDate.toISOString().split('T')[0]);
});
</script>

<?php require 'footer.php'; // Include the footer ?>