<?php
require 'auth.php';  // Include authentication logic
require 'db.php';    // Include database connection

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/expenses/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch available accounts from the database
$sql_accounts = "SELECT id, name FROM accounts";
$result_accounts = $conn->query($sql_accounts);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture form data and sanitize inputs
    $invoice_number = $conn->real_escape_string($_POST['invoice_number']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $account_id = $conn->real_escape_string($_POST['account_id']);
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    $expense_deadline = $conn->real_escape_string($_POST['expense_deadline']);
    $repartizare_luna = $conn->real_escape_string($_POST['repartizare_luna']);

    // Insert new expense into the database
    $sql = "INSERT INTO expenses (invoice_number, description, category, amount, account_id, expense_date, expense_deadline, repartizare_luna) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare statement to insert the new expense
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters
        $stmt->bind_param("sssdssss", $invoice_number, $description, $category, $amount, $account_id, $expense_date, $expense_deadline, $repartizare_luna);

        // Execute statement
        if ($stmt->execute()) {
            $expense_id = $conn->insert_id; // Get the ID of the newly inserted expense
            
            // Handle file upload if a file was selected
            if (isset($_FILES['expense_attachment']) && $_FILES['expense_attachment']['error'] == 0) {
                $file_name = $_FILES['expense_attachment']['name'];
                $file_tmp = $_FILES['expense_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Keep the original filename but ensure it's unique
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
            echo "Error: " . $stmt->error;
        }
    } else {
        echo "Error: Could not prepare statement.";
    }
}

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Add New Expense</h1>

    <form method="POST" enctype="multipart/form-data">
        <!-- Invoice Number -->
        <div class="mb-3">
            <label for="invoice_number" class="form-label">Invoice Number</label>
            <input type="text" class="form-control" id="invoice_number" name="invoice_number" required>
        </div>
        
        <!-- Category -->
        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <input type="text" class="form-control" id="category" name="category" required>
        </div>
        
        <!-- Description -->
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>
        
        <!-- Amount -->
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
        </div>

        <!-- Account Selection -->
        <div class="mb-3">
            <label for="account_id" class="form-label">Select Account</label>
            <select class="form-select" id="account_id" name="account_id" required>
                <option value="">Select an Account</option>
                <?php while ($row = $result_accounts->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Expense Date -->
        <div class="mb-3">
            <label for="expense_date" class="form-label">Expense Date</label>
            <input type="date" class="form-control" id="expense_date" name="expense_date" required>
        </div>

        <!-- Expense Deadline -->
        <div class="mb-3">
            <label for="expense_deadline" class="form-label">Expense Deadline</label>
            <input type="date" class="form-control" id="expense_deadline" name="expense_deadline">
        </div>

        <!-- Repartizare Luna -->
        <div class="mb-3">
            <label for="repartizare_luna" class="form-label">Repartizare Luna</label>
            <input type="date" class="form-control" id="repartizare_luna" name="repartizare_luna" required>
        </div>

        <!-- File Upload -->
        <div class="mb-3">
            <label for="expense_attachment" class="form-label">Upload Document (PDF, JPG, PNG, DOC, XLS)</label>
            <input type="file" class="form-control" id="expense_attachment" name="expense_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>

        <button type="submit" class="btn btn-success">Save Expense</button>
        <a href="expenses.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require 'footer.php'; // Include the footer ?>