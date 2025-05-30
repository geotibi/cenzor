<?php
require 'auth.php';  // Include authentication logic
require 'db.php';    // Include database connection

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/facturi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch available accounts from the database
$sql_accounts = "SELECT id, name FROM accounts";
$result_accounts = $conn->query($sql_accounts);

// Fetch furnizori data (for getting the bill_type based on furnizor_id)
$sql_furnizori = "SELECT id, nume FROM furnizori ORDER by nume ASC";
$result_furnizori = $conn->query($sql_furnizori);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture form data and sanitize inputs
    $bill_no = $conn->real_escape_string($_POST['bill_no']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $bill_date = $conn->real_escape_string($_POST['bill_date']);
    $bill_deadline = $conn->real_escape_string($_POST['bill_deadline']);
    $account_id = $conn->real_escape_string($_POST['account_id']);
    $description = $conn->real_escape_string($_POST['description']);
    $repartizare_luna = $conn->real_escape_string($_POST['repartizare_luna']);
    $furnizor_id = $conn->real_escape_string($_POST['furnizor_id']);  // This is the furnizor ID selected from the form

    // Fetch the furnizor name based on furnizor_id
    $sql_furnizor_name = "SELECT nume FROM furnizori WHERE id = ?";
    if ($stmt = $conn->prepare($sql_furnizor_name)) {
        $stmt->bind_param("i", $furnizor_id);
        $stmt->execute();
        $stmt->bind_result($furnizor_name);
        $stmt->fetch();
        $stmt->close();
    }

    // Check if the furnizor exists and fetch the name
    if (!isset($furnizor_name)) {
        echo "Invalid furnizor selected.";
        exit;
    }

    // Insert new bill into the database
    $sql = "INSERT INTO building_bills (bill_type, bill_no, amount, bill_date, bill_deadline, account_id, description, repartizare_luna, furnizor_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare statement to insert the new bill
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters
        $stmt->bind_param("ssdssssss", $furnizor_name, $bill_no, $amount, $bill_date, $bill_deadline, $account_id, $description, $repartizare_luna, $furnizor_id);

        // Execute statement
        if ($stmt->execute()) {
            $bill_id = $conn->insert_id; // Get the ID of the newly inserted bill
            
            // Handle file upload if a file was selected
            if (isset($_FILES['bill_attachment']) && $_FILES['bill_attachment']['error'] == 0) {
                $file_name = $_FILES['bill_attachment']['name'];
                $file_tmp = $_FILES['bill_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Keep the original filename but ensure it's unique by adding bill_id if needed
                    $new_file_name = $furnizor_name . '_' . $bill_no . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    // If file already exists, add timestamp to make it unique
                    if (file_exists($file_path)) {
                        $new_file_name = $furnizor_name . '_' . $bill_no . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                    }
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert file information into the database
                        $sql_attachment = "INSERT INTO bill_attachments (bill_id, file_name, file_path) VALUES (?, ?, ?)";
                        if ($stmt_attachment = $conn->prepare($sql_attachment)) {
                            $stmt_attachment->bind_param("iss", $bill_id, $file_name, $file_path);
                            $stmt_attachment->execute();
                            $stmt_attachment->close();
                        }
                    }
                }
            }
            
            header('Location: facturi_utilitati.php');
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
    <h1 class="mb-4">Add New Bill</h1>

    <form method="POST" enctype="multipart/form-data">
        <!-- Furnizor Selection -->
        <div class="mb-3">
            <label for="furnizor_id" class="form-label">Select Furnizor</label>
            <select class="form-select" id="furnizor_id" name="furnizor_id" required>
                <option value="">Select a Furnizor</option>
                <?php while ($row = $result_furnizori->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['nume']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Bill No -->
        <div class="mb-3">
            <label for="bill_no" class="form-label">Bill No.</label>
            <input type="text" class="form-control" id="bill_no" name="bill_no" required>
        </div>
        
        <!-- Amount -->
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
        </div>

        <!-- Bill Date -->
        <div class="mb-3">
            <label for="bill_date" class="form-label">Bill Date</label>
            <input type="date" class="form-control" id="bill_date" name="bill_date" required>
        </div>

        <!-- Bill Deadline -->
        <div class="mb-3">
            <label for="bill_deadline" class="form-label">Bill Deadline</label>
            <input type="date" class="form-control" id="bill_deadline" name="bill_deadline" required>
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

        <!-- Description -->
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <input type="text" class="form-control" id="description" name="description" required>
        </div>

        <!-- Repartizare Luna -->
        <div class="mb-3">
            <label for="repartizare_luna" class="form-label">Repartizare Luna</label>
            <input type="date" class="form-control" id="repartizare_luna" name="repartizare_luna" required>
        </div>

        <!-- File Upload -->
        <div class="mb-3">
            <label for="bill_attachment" class="form-label">Upload Invoice (PDF, JPG, PNG, DOC, XLS)</label>
            <input type="file" class="form-control" id="bill_attachment" name="bill_attachment">
            <div class="form-text">Max file size: 10MB</div>
        </div>

        <button type="submit" class="btn btn-success">Save Bill</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require 'footer.php'; // Include the footer ?>