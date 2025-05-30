<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid tenant ID.";
    exit;
}

$tenant_id = (int) $_GET['id'];

// Fetch tenant details
$sql = "SELECT * FROM tenants WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "Tenant not found.";
        exit;
    }
    
    $tenant = $result->fetch_assoc();
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $name = $conn->real_escape_string($_POST['name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Update tenant
    $sql = "UPDATE tenants SET 
            name = ?, 
            contact_person = ?, 
            phone = ?, 
            email = ?, 
            address = ?, 
            notes = ? 
            WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssi", $name, $contact_person, $phone, $email, $address, $notes, $tenant_id);
        
        if ($stmt->execute()) {
            // Redirect to tenants list
            header('Location: tenants.php');
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
    <h1 class="mb-4">Edit Tenant</h1>
    
    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Tenant Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($tenant['name']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="contact_person" class="form-label">Contact Person</label>
            <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($tenant['contact_person']); ?>">
        </div>
        
        <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($tenant['phone']); ?>">
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($tenant['email']); ?>">
        </div>
        
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($tenant['address']); ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($tenant['notes']); ?></textarea>
        </div>
        
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-lg">Save Changes</button>
            <a href="tenants.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    
    <div class="mt-5">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contracts</h5>
                        <a href="tenant_contracts.php?tenant_id=<?php echo $tenant_id; ?>" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Fetch active contracts
                        $sql = "SELECT tc.id, tc.purpose, tc.start_date, tc.end_date, tc.amount, tc.currency, s.name AS space_name
                                FROM tenant_contracts tc
                                JOIN spaces s ON tc.space_id = s.id
                                WHERE tc.tenant_id = ? AND tc.status = 'active'
                                ORDER BY tc.end_date DESC
                                LIMIT 3";
                        
                        if ($stmt = $conn->prepare($sql)) {
                            $stmt->bind_param("i", $tenant_id);
                            $stmt->execute();
                            $contracts_result = $stmt->get_result();
                            
                            if ($contracts_result->num_rows > 0) {
                                echo '<div class="list-group">';
                                while ($contract = $contracts_result->fetch_assoc()) {
                                    echo '<div class="list-group-item list-group-item-action">';
                                    echo '<div class="d-flex w-100 justify-content-between">';
                                    echo '<h6 class="mb-1">' . htmlspecialchars($contract['space_name']) . '</h6>';
                                    echo '<small>' . htmlspecialchars($contract['amount']) . ' ' . htmlspecialchars($contract['currency']) . '</small>';
                                    echo '</div>';
                                    echo '<p class="mb-1">' . htmlspecialchars($contract['purpose']) . '</p>';
                                    echo '<small>From ' . htmlspecialchars($contract['start_date']) . ' to ' . htmlspecialchars($contract['end_date']) . '</small>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-info">No active contracts found.</div>';
                            }
                            
                            $stmt->close();
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Unpaid Invoices</h5>
                        <a href="tenant_invoices.php?tenant_id=<?php echo $tenant_id; ?>" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Fetch unpaid invoices
                        $sql = "SELECT ti.id, ti.invoice_number, ti.amount, ti.currency, ti.issue_date, ti.due_date
                                FROM tenant_invoices ti
                                WHERE ti.tenant_id = ? AND ti.status = 'unpaid'
                                ORDER BY ti.due_date ASC
                                LIMIT 3";
                        
                        if ($stmt = $conn->prepare($sql)) {
                            $stmt->bind_param("i", $tenant_id);
                            $stmt->execute();
                            $invoices_result = $stmt->get_result();
                            
                            if ($invoices_result->num_rows > 0) {
                                echo '<div class="list-group">';
                                while ($invoice = $invoices_result->fetch_assoc()) {
                                    echo '<div class="list-group-item list-group-item-action">';
                                    echo '<div class="d-flex w-100 justify-content-between">';
                                    echo '<h6 class="mb-1">' . htmlspecialchars($invoice['invoice_number']) . '</h6>';
                                    echo '<small>' . htmlspecialchars($invoice['amount']) . ' ' . htmlspecialchars($invoice['currency']) . '</small>';
                                    echo '</div>';
                                    echo '<p class="mb-1">Issued: ' . htmlspecialchars($invoice['issue_date']) . '</p>';
                                    echo '<small class="text-danger">Due: ' . htmlspecialchars($invoice['due_date']) . '</small>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-success">No unpaid invoices found.</div>';
                            }
                            
                            $stmt->close();
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>