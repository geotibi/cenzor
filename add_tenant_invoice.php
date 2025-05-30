<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/invoices/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get tenant_id or contract_id from URL if provided
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : null;

// If contract_id is provided, get the tenant_id
if ($contract_id && !$tenant_id) {
    $sql_get_tenant = "SELECT tenant_id FROM tenant_contracts WHERE id = ?";
    if ($stmt = $conn->prepare($sql_get_tenant)) {
        $stmt->bind_param("i", $contract_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $tenant_id = $row['tenant_id'];
        }
        $stmt->close();
    }
}

// Fetch tenants for dropdown
$sql_tenants = "SELECT id, name FROM tenants ORDER BY name";
$result_tenants = $conn->query($sql_tenants);

// Fetch contracts for dropdown (will be filtered by JavaScript based on tenant selection)
$contract_filter = $tenant_id ? "WHERE tenant_id = $tenant_id" : "";
$sql_contracts = "SELECT tc.id, tc.tenant_id, tc.purpose, s.name AS space_name, tc.amount, tc.utilities_fee, tc.currency 
                 FROM tenant_contracts tc
                 JOIN spaces s ON tc.space_id = s.id
                 $contract_filter
                 ORDER BY tc.end_date DESC";
$result_contracts = $conn->query($sql_contracts);

// Fetch accounts for dropdown
$sql_accounts = "SELECT id, name FROM accounts ORDER BY name";
$result_accounts = $conn->query($sql_accounts);

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
    
    // Insert new invoice
    $sql = "INSERT INTO tenant_invoices (tenant_id, contract_id, invoice_number, amount, rent_amount, utilities_amount, currency, issue_date, due_date, period_start, period_end, status, payment_date, payment_amount, account_id, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, $payment_date, $payment_amount, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iisdddssssssis", $tenant_id, $contract_id, $invoice_number, $amount, $rent_amount, $utilities_amount, $currency, $issue_date, $due_date, $period_start, $period_end, $status, $account_id, $notes);
        
        if ($stmt->execute()) {
            $invoice_id = $conn->insert_id;
            
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
            header('Location: tenant_invoices.php' . ($tenant_id ? '?tenant_id=' . $tenant_id : ''));
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
    <h1 class="mb-4">Add New Invoice</h1>
    
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
                    <label for="contract_id" class="form-label">Contract</label>
                    <select class="form-select" id="contract_id" name="contract_id" required>
                        <option value="">Select Contract</option>
                        <?php 
                        // Reset the result pointer
                        $result_contracts->data_seek(0);
                        while ($row = $result_contracts->fetch_assoc()): 
                            $contract_display = $row['space_name'] . ' - ' . $row['purpose'] . ' (' . $row['amount'] . ' ' . $row['currency'] . ')';
                        ?>
                            <option value="<?php echo $row['id']; ?>" 
                                    data-tenant="<?php echo $row['tenant_id']; ?>"
                                    data-amount="<?php echo $row['amount']; ?>"
                                    data-utilities-fee="<?php echo $row['utilities_fee']; ?>"
                                    data-currency="<?php echo $row['currency']; ?>"
                                    <?php echo ($contract_id == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($contract_display); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="invoice_number" class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" id="invoice_number" name="invoice_number" required>
                </div>
                
                <div class="mb-3">
                    <label for="rent_amount" class="form-label">Rent Amount</label>
                    <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" required>
                </div>

                <div class="mb-3">
                    <label for="utilities_amount" class="form-label">Utilities Amount</label>
                    <input type="number" step="0.01" class="form-control" id="utilities_amount" name="utilities_amount" value="0.00">
                </div>

                <div class="mb-3">
                    <label for="amount" class="form-label">Total Amount (Auto-calculated)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" readonly>
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
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="issue_date" class="form-label">Issue Date</label>
                    <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="due_date" class="form-label">Due Date</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" required>
                </div>
                
                <div class="mb-3">
                    <label for="period_start" class="form-label">Period Start</label>
                    <input type="date" class="form-control" id="period_start" name="period_start" required>
                </div>
                
                <div class="mb-3">
                    <label for="period_end" class="form-label">Period End</label>
                    <input type="date" class="form-control" id="period_end" name="period_end" required>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partially Paid</option>
                    </select>
                </div>
                
                <div id="payment_details" style="display: none;">
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Payment Amount</label>
                        <input type="number" step="0.01" class="form-control" id="payment_amount" name="payment_amount">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
        </div>
        
        <div class="mb-3">
            <label for="invoice_attachment" class="form-label">Invoice Document (PDF, DOC, JPG)</label>
            <input type="file" class="form-control" id="invoice_attachment" name="invoice_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Invoice</button>
            <a href="tenant_invoices.php<?php echo $tenant_id ? '?tenant_id=' . $tenant_id : ''; ?>" class="btn btn-secondary">Cancel</a>
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
        $('#contract_id').val('');
    });
    
    // Auto-fill amount and currency based on contract
    $('#contract_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        if (selectedOption.val() !== '') {
            $('#amount').val(selectedOption.data('amount'));
            $('#currency').val(selectedOption.data('currency'));
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
    
    // Set default due date (today + 15 days)
    var today = new Date();
    var dueDate = new Date(today);
    dueDate.setDate(today.getDate() + 15);
    $('#due_date').val(dueDate.toISOString().split('T')[0]);
    
    // Set default period (current month)
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    $('#period_start').val(firstDay.toISOString().split('T')[0]);
    $('#period_end').val(lastDay.toISOString().split('T')[0]);
    
    // Trigger initial filtering if tenant is preselected
    if ($('#tenant_id').val() !== '') {
        $('#tenant_id').trigger('change');
    }
    
    // Trigger initial status check
    $('#status').trigger('change');
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
    
    // Auto-fill from contract when selected
    $('#contract_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        if (selectedOption.val() !== '') {
            $('#rent_amount').val(selectedOption.data('amount'));
            $('#utilities_amount').val(selectedOption.data('utilities-fee'));
            calculateTotal();
        }
    });
});
</script>
<?php require 'footer.php'; // Include the footer ?>