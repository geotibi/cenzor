<?php
require 'auth.php';
require 'db.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/facturi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_GET['id'])) {
    $bill_id = (int) $_GET['id']; // Always cast to int for safety

    // Fetch the bill details
    $sql = "SELECT * FROM building_bills WHERE id = $bill_id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $bill = $result->fetch_assoc();
    } else {
        echo "Bill not found.";
        exit;
    }
    
    // Fetch attachments for this bill
    $sql_attachments = "SELECT * FROM bill_attachments WHERE bill_id = $bill_id";
    $result_attachments = $conn->query($sql_attachments);

    // Handle attachment deletion
    if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment'])) {
        $attachment_id = (int) $_GET['delete_attachment'];
        
        // Get the file path before deleting the record
        $sql_get_attachment = "SELECT file_path FROM bill_attachments WHERE id = $attachment_id AND bill_id = $bill_id";
        $result_get_attachment = $conn->query($sql_get_attachment);
        
        if ($result_get_attachment && $result_get_attachment->num_rows > 0) {
            $attachment = $result_get_attachment->fetch_assoc();
            $file_path = $attachment['file_path'];
            
            // Delete the record from the database
            $sql_delete = "DELETE FROM bill_attachments WHERE id = $attachment_id AND bill_id = $bill_id";
            if ($conn->query($sql_delete) === TRUE) {
                // Delete the file from the server
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Redirect to refresh the page
                header("Location: edit_bill.php?id=$bill_id");
                exit;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $bill_type = $conn->real_escape_string($_POST['bill_type']); // Added this line to capture bill_type
        $bill_no = $conn->real_escape_string($_POST['bill_no']);
        $amount = (float) $_POST['amount'];
        $bill_date = $conn->real_escape_string($_POST['bill_date']);
        $bill_deadline = !empty($_POST['bill_deadline']) ? "'".$conn->real_escape_string($_POST['bill_deadline'])."'" : "NULL";
        $description = $conn->real_escape_string($_POST['description']);
        $repartizare_luna = $conn->real_escape_string($_POST['repartizare_luna']);
        
        // Update bill in the database
        $sql = "UPDATE building_bills SET 
                    bill_type = '$bill_type',
                    bill_no = '$bill_no',
                    amount = $amount,
                    bill_date = '$bill_date',
                    bill_deadline = $bill_deadline,
                    description = '$description',
                    repartizare_luna = '$repartizare_luna'
                WHERE id = $bill_id";

        if ($conn->query($sql) === TRUE) {
            // Handle file upload if a file was selected
            if (isset($_FILES['bill_attachment']) && $_FILES['bill_attachment']['error'] == 0) {
                $file_name = $_FILES['bill_attachment']['name'];
                $file_tmp = $_FILES['bill_attachment']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // List of allowed file extensions
                $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    // Use bill_type and bill_no for the filename
                    $new_file_name = $bill_type . '_' . $bill_no . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    // If file already exists, add timestamp to make it unique
                    if (file_exists($file_path)) {
                        $new_file_name = $bill_type . '_' . $bill_no . '_' . time() . '.' . $file_ext;
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
    <h1 class="mb-4">Edit Bill</h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="bill_type" class="form-label">Bill Name</label>
            <input type="text" class="form-control" id="bill_type" name="bill_type" value="<?php echo htmlspecialchars($bill['bill_type']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="bill_no" class="form-label">Bill No</label>
            <input type="text" class="form-control" id="bill_no" name="bill_no" value="<?php echo htmlspecialchars($bill['bill_no']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($bill['amount']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="bill_date" class="form-label">Bill Date</label>
            <input type="date" class="form-control" id="bill_date" name="bill_date" value="<?php echo htmlspecialchars($bill['bill_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="bill_deadline" class="form-label">Bill Deadline</label>
            <input type="date" class="form-control" id="bill_deadline" name="bill_deadline" value="<?php echo htmlspecialchars($bill['bill_deadline']); ?>">
        </div>
        <div class="mb-3">
            <label for="repartizare_luna" class="form-label">Repartizare luna</label>
            <input type="date" class="form-control" id="repartizare_luna" name="repartizare_luna" value="<?php echo htmlspecialchars($bill['repartizare_luna']); ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($bill['description']); ?></textarea>
        </div>
        
        <!-- File Upload -->
        <div class="mb-3">
            <label for="bill_attachment" class="form-label">Add New Attachment (PDF, JPG, PNG, DOC, XLS)</label>
            <input type="file" class="form-control" id="bill_attachment" name="bill_attachment">
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
                                <a href="edit_bill.php?id=<?php echo $bill_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No attachments found for this bill.</div>
        <?php endif; ?>
        
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-lg">Save Changes</button>
            <a href="facturi_utilitati.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require 'footer.php'; // Include the footer ?>