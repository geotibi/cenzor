<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    echo "Invalid bill ID.";
    exit;
}

$bill_id = (int) $_GET['bill_id'];

// Fetch bill details
$sql_bill = "SELECT * FROM building_bills WHERE id = $bill_id";
$result_bill = $conn->query($sql_bill);

if (!$result_bill || $result_bill->num_rows == 0) {
    echo "Bill not found.";
    exit;
}

$bill = $result_bill->fetch_assoc();

// Fetch attachments for this bill
$sql_attachments = "SELECT * FROM bill_attachments WHERE bill_id = $bill_id ORDER BY upload_date DESC";
$result_attachments = $conn->query($sql_attachments);

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Attachments for Bill #<?php echo $bill_id; ?></h1>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Bill Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Bill Type:</strong> <?php echo htmlspecialchars($bill['bill_type']); ?></p>
                    <p><strong>Bill Number:</strong> <?php echo htmlspecialchars($bill['bill_no']); ?></p>
                    <p><strong>Amount:</strong> <?php echo htmlspecialchars($bill['amount']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Bill Date:</strong> <?php echo htmlspecialchars($bill['bill_date']); ?></p>
                    <p><strong>Bill Deadline:</strong> <?php echo htmlspecialchars($bill['bill_deadline']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($bill['description']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attachments</h5>
            <a href="edit_bill.php?id=<?php echo $bill_id; ?>" class="btn btn-primary btn-sm">Add New Attachment</a>
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
                                        <a href="edit_bill.php?id=<?php echo $bill_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No attachments found for this bill.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="facturi_utilitati.php" class="btn btn-secondary">Back to Bills</a>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>