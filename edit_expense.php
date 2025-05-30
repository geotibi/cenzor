<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/expenses/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_GET['id'])) {
    $expense_id = (int) $_GET['id']; // Always cast to int for safety

    // Fetch the expense details
    $sql = "SELECT * FROM expenses WHERE id = $expense_id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $expense = $result->fetch_assoc();
    } else {
        echo "Expense not found.";
        exit;
    }
    
    // Fetch accounts for dropdown
    $sql_accounts = "SELECT id, name FROM accounts";
    $result_accounts = $conn->query($sql_accounts);
    
    // Fetch attachments for this expense
    $sql_attachments = "SELECT * FROM expense_attachments WHERE expense_id = $expense_id";
    $result_attachments = $conn->query($sql_attachments);

    // Handle attachment deletion
    if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment'])) {
        $attachment_id = (int) $_GET['delete_attachment'];
        
        // Get the file path before deleting the record
        $sql_get_attachment = "SELECT file_path FROM expense_attachments WHERE id = $attachment_id AND expense_id = $expense_id";
        $result_get_attachment = $conn->query($sql_get_attachment);
        
        if ($result_get_attachment && $result_get_attachment->num_rows > 0) {
            $attachment = $result_get_attachment->fetch_assoc();
            $file_path = $attachment['file_path'];
            
            // Delete the record from the database
            $sql_delete = "DELETE FROM expense_attachments WHERE id = $attachment_id AND expense_id = $expense_id";
            if ($conn->query($sql_delete) === TRUE) {
                // Delete the file from the server
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Redirect to refresh the page
                header("Location: edit_expense.php?id=$expense_id");
                exit;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $invoice_number = $conn->real_escape_string($_POST['invoice_number']);
        $description = $conn->real_escape_string($_POST['description']);
        $category = $conn->real_escape_string($_POST['category']);
        $amount = (float) $_POST['amount'];
        $account_id = (int) $_POST['account_id'];
        $expense_date = $conn->real_escape_string($_POST['expense_date']);
        $expense_deadline = !empty($_POST['expense_deadline']) ? "'".$conn->real_escape_string($_POST['expense_deadline'])."'" : "NULL";
        $repartizare_luna = $conn->real_escape_string($_POST['repartizare_luna']);
        
        // Update expense in the database
        $sql = "UPDATE expenses SET 
                    invoice_number = '$invoice_number',
                    description = '$description',
                    category = '$category',
                    amount = $amount,
                    account_id = $account_id,
                    expense_date = '$expense_date',
                    expense_deadline = $expense_deadline,
                    repartizare_luna = '$repartizare_luna'
                WHERE id = $expense_id";

        if ($conn->query($sql) === TRUE) {
            // Handle file upload if a file was selected
            if (isset($_FILES['expense_attachment']) && $_FILES['expense_attachment']['error'] == 0) {
                $file_name = $_FILES['expense_attachment']['name'];
                $file_tmp = $_FILES['expense_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Use category and invoice_number for the filename
                    $new_file_name = $category . '_' . $invoice_number . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    // If file already exists, add timestamp to make it unique
                    if (file_exists($file_path)) {
                        $new_file_name = $category . '_' . $invoice_number . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                    }
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert file information into the database
                        $sql_attachment = "INSERT INTO expense_attachments (expense_id, file_name, file_path) VALUES (?, ?, ?)";
                        if ($stmt_attachment = $conn->prepare($sql_attachment)) {
                            $stmt_attachment->bind_param("iss", $expense_id, $file_name, $file_path);
                            $stmt_attachment->execute();
                            $stmt_attachment->close();
                        }
                    }
                }
            }
            
            header('Location: expenses.php');
            exit;
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }
} else {
    echo "Invalid request.";
    exit;
}

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Edit Expense</h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="invoice_number" class="form-label">Invoice Number</label>
            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?php echo htmlspecialchars($expense['invoice_number']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($expense['category']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($expense['description']); ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($expense['amount']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="account_id" class="form-label">Account</label>
            <select class="form-select" id="account_id" name="account_id" required>
                <?php while ($row = $result_accounts->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>" <?php echo ($row['id'] == $expense['account_id']) ? 'selected' : ''; ?>>
                        <?php echo $row['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="expense_date" class="form-label">Expense Date</label>
            <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo htmlspecialchars($expense['expense_date']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="expense_deadline" class="form-label">Expense Deadline</label>
            <input type="date" class="form-control" id="expense_deadline" name="expense_deadline" value="<?php echo htmlspecialchars($expense['expense_deadline']); ?>">
        </div>
        
        <div class="mb-3">
            <label for="repartizare_luna" class="form-label">Repartizare luna</label>
            <input type="date" class="form-control" id="repartizare_luna" name="repartizare_luna" value="<?php echo htmlspecialchars($expense['repartizare_luna']); ?>" required>
        </div>
        
        <!-- File Upload -->
        <div class="mb-3">
            <label for="expense_attachment" class="form-label">Add New Attachment (PDF, JPG, PNG, DOC, XLS)</label>
            <input type="file" class="form-control" id="expense_attachment" name="expense_attachment">
            <div class="form-text">Max file size: 10MB</div>
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
                                <a href="edit_expense.php?id=<?php echo $expense_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No attachments found for this expense.</div>
        <?php endif; ?>
        
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-lg">Save Changes</button>
            <a href="expenses.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require 'footer.php'; // Include the footer ?>