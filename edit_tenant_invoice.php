<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/invoices/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid invoice ID.";
    exit;
}

$invoice_id = (int) $_GET['id'];

// Fetch invoice details
$sql = "SELECT * FROM tenant_invoices WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "Invoice not found.";
        exit;
    }
    
    $invoice = $result->fetch_assoc();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Fetch tenants for dropdown
$sql_tenants = "SELECT id, name FROM tenants ORDER BY name";
$result_tenants = $conn->query($sql_tenants);

// Fetch contracts for dropdown
$sql_contracts = "SELECT tc.id, tc.tenant_id, tc.purpose, s.name AS space_name, tc.amount, tc.currency 
                 FROM tenant_contracts tc
                 JOIN spaces s ON tc.space_id = s.id
                 ORDER BY tc.end_date DESC";
$result_contracts = $conn->query($sql_contracts);

// Fetch accounts for dropdown
$sql_accounts = "SELECT id, name FROM accounts ORDER BY name";
$result_accounts = $conn->query($sql_accounts);

// Fetch attachments for this invoice
$sql_attachments = "SELECT * FROM tenant_invoice_attachments WHERE invoice_id = ?";
if ($stmt_attachments = $conn->prepare($sql_attachments)) {
    $stmt_attachments->bind_param("i", $invoice_id);
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
    $sql_get_attachment = "SELECT file_path FROM tenant_invoice_attachments WHERE id = ? AND invoice_id = ?";
    if ($stmt_get = $conn->prepare($sql_get_attachment)) {
        $stmt_get->bind_param("ii", $attachment_id, $invoice_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        if ($result_get->num_rows > 0) {
            $attachment = $result_get->fetch_assoc();
            $file_path = $attachment['file_path'];
            
            // Delete the record from the database
            $sql_delete = "DELETE FROM tenant_invoice_attachments WHERE id = ? AND invoice_id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("ii", $attachment_id, $invoice_id);
                
                if ($stmt_delete->execute()) {
                    // Delete the file from the server
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    // Redirect to refresh the page
                    header("Location: edit_tenant_invoice.php?id=$invoice_id");
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
    $contract_id = (int) $_POST['contract_id'];
    $invoice_number = $conn->real_escape_string($_POST['invoice_number']);
    $rent_amount = (float) $_POST['rent_amount'];
    $utilities_amount = (float) $_POST['utilities_amount'];
    $amount = $rent_amount + $utilities_amount;
    $currency = $conn->real_escape_string($_POST['currency']);
    $issue_date = $conn->real_escape_string($_POST['issue_date']);
    $due_date = $conn->real_escape_string($_POST['due_date']);
    $period_start = $conn->real_escape_string($_POST['period_start']);
    $period_end = $conn->real_escape_string($_POST['period_end']);
    $status = $conn->real_escape_string($_POST['status']);
    $payment_date = !empty($_POST['payment_date']) ? "'".$conn->real_escape_string($_POST['payment_date'])."'" : "NULL";
    $payment_amount = !empty($_POST['payment_amount']) ? (float) $_POST['payment_amount'] : "NULL";
    $account_id = (int) $_POST['account_id'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Update invoice
    $sql = "UPDATE tenant_invoices SET 
        tenant_id = ?, 
        contract_id = ?, 
        invoice_number = ?, 
        amount = ?, 
        rent_amount = ?, 
        utilities_amount = ?, 
        currency = ?, 
        issue_date = ?, 
        due_date = ?, 
        period_start = ?, 
        period_end = ?, 
        status = ?, 
        payment_date = $payment_date, 
        payment_amount = $payment_amount, 
        account_id = ?, 
        notes = ? 
        WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iisdddssssssisi", $tenant_id, $contract_id, $invoice_number, $amount, $rent_amount, $utilities_amount, $currency, $issue_date, $due_date, $period_start, $period_end, $status, $account_id, $notes, $invoice_id);
        
        if ($stmt->execute()) {
            // Handle file upload if a file was selected
            if (isset($_FILES['invoice_attachment']) && $_FILES['invoice_attachment']['error'] == 0) {
                $file_name = $_FILES['invoice_attachment']['name'];
                $file_tmp = $_FILES['invoice_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Create a unique filename
                    $new_file_name = 'invoice_' . $invoice_id . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert file information into the database
                        $sql_attachment = "INSERT INTO tenant_invoice_attachments (invoice_id, file_name, file_path) VALUES (?, ?, ?)";
                        if ($stmt_attachment = $conn->prepare($sql_attachment)) {
                            $stmt_attachment->bind_param("iss", $invoice_id, $file_name, $file_path);
                            $stmt_attachment->execute();
                            $stmt_attachment->close();
                        }
                    }
                }
            }
            
            // Redirect to invoices list
            header('Location: tenant_invoices.php?tenant_id=' . $tenant_id);
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
    <h1 class="mb-4">Edit Invoice</h1>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id" required>
                        <option value="">Select Tenant</option>
                        <?php while ($row = $result_tenants->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($invoice['tenant_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="contract_id" class="form-label">Contract</label>
                    <select class="form-select" id="contract_id" name="contract_id" required>
                        <option value="">Select Contract</option>
                        <?php while ($row = $result_contracts->fetch_assoc()): 
                            $contract_display = $row['space_name'] . ' - ' . $row['purpose'] . ' (' . $row['amount'] . ' ' . $row['currency'] . ')';
                        ?>
                            <option value="<?php echo $row['id']; ?>" 
                                    data-tenant="<?php echo $row['tenant_id']; ?>"
                                    data-amount="<?php echo $row['amount']; ?>"
                                    data-currency="<?php echo $row['currency']; ?>"
                                    <?php echo ($invoice['contract_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($contract_display); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="invoice_number" class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="rent_amount" class="form-label">Rent Amount</label>
                    <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" value="<?php echo htmlspecialchars($invoice['rent_amount']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="utilities_amount" class="form-label">Utilities Amount</label>
                    <input type="number" step="0.01" class="form-control" id="utilities_amount" name="utilities_amount" value="<?php echo htmlspecialchars($invoice['utilities_amount']); ?>">
                </div>

                <div class="mb-3">
                    <label for="amount" class="form-label">Total Amount (Auto-calculated)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($invoice['amount']); ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label for="currency" class="form-label">Currency</label>
                    <select class="form-select" id="currency" name="currency">
                        <option value="RON" <?php echo ($invoice['currency'] == 'RON') ? 'selected' : ''; ?>>RON</option>
                        <option value="EUR" <?php echo ($invoice['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                        <option value="USD" <?php echo ($invoice['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">Select Account</option>
                        <?php while ($row = $result_accounts->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($invoice['account_id'] == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="issue_date" class="form-label">Issue Date</label>
                    <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo htmlspecialchars($invoice['issue_date']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="due_date" class="form-label">Due Date</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="period_start" class="form-label">Period Start</label>
                    <input type="date" class="form-control" id="period_start" name="period_start" value="<?php echo htmlspecialchars($invoice['period_start']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="period_end" class="form-label">Period End</label>
                    <input type="date" class="form-control" id="period_end" name="period_end" value="<?php echo htmlspecialchars($invoice['period_end']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="unpaid" <?php echo ($invoice['status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo ($invoice['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo ($invoice['status'] == 'partial') ? 'selected' : ''; ?>>Partially Paid</option>
                    </select>
                </div>
                
                <div id="payment_details" style="display: <?php echo ($invoice['status'] == 'paid' || $invoice['status'] == 'partial') ? 'block' : 'none'; ?>;">
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo htmlspecialchars($invoice['payment_date']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Payment Amount</label>
                        <input type="number" step="0.01" class="form-control" id="payment_amount" name="payment_amount" value="<?php echo htmlspecialchars($invoice['payment_amount']); ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
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
                                <a href="edit_tenant_invoice.php?id=<?php echo $invoice_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No attachments found for this invoice.</div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label for="invoice_attachment" class="form-label">Add New Attachment (PDF, DOC, JPG)</label>
            <input type="file" class="form-control" id="invoice_attachment" name="invoice_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>
        
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-lg">Save Changes</button>
            <a href="tenant_invoices.php?tenant_id=<?php echo $invoice['tenant_id']; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Filter contracts based on tenant selection
    $('#tenant_id').change(function() {
        var selectedTenant = $(this).val();
        $('#contract_id option').each(function() {
            if ($(this).val() === '' || $(this).data('tenant') == selectedTenant) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Don't reset contract if it belongs to the selected tenant
        var currentContract = $('#contract_id').val();
        var belongsToTenant = false;
        
        $('#contract_id option').each(function() {
            if ($(this).val() === currentContract && $(this).data('tenant') == selectedTenant) {
                belongsToTenant = true;
            }
        });
        
        if (!belongsToTenant) {
            $('#contract_id').val('');
        }
    });
    
    // Show/hide payment details based on status
    $('#status').change(function() {
        if ($(this).val() === 'paid' || $(this).val() === 'partial') {
            $('#payment_details').show();
        } else {
            $('#payment_details').hide();
        }
    });
    
    // Trigger initial filtering
    $('#tenant_id').trigger('change');
});
</script>

<script>
$(document).ready(function() {
    function calculateTotal() {
        var rentAmount = parseFloat($('#rent_amount').val()) || 0;
        var utilitiesAmount = parseFloat($('#utilities_amount').val()) || 0;
        $('#amount').val((rentAmount + utilitiesAmount).toFixed(2));
    }
    
    $('#rent_amount, #utilities_amount').on('input', calculateTotal);
});
</script>


<?php require 'footer.php'; // Include the footer ?>