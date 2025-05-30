<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['expense_id']) || !is_numeric($_GET['expense_id'])) {
    echo "Invalid expense ID.";
    exit;
}

$expense_id = (int) $_GET['expense_id'];

// Fetch expense details
$sql_expense = "SELECT e.*, a.name AS account_name 
                FROM expenses e 
                LEFT JOIN accounts a ON e.account_id = a.id 
                WHERE e.id = $expense_id";
$result_expense = $conn->query($sql_expense);

if (!$result_expense || $result_expense->num_rows == 0) {
    echo "Expense not found.";
    exit;
}

$expense = $result_expense->fetch_assoc();

// Fetch attachments for this expense
$sql_attachments = "SELECT * FROM expense_attachments WHERE expense_id = $expense_id ORDER BY upload_date DESC";
$result_attachments = $conn->query($sql_attachments);

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Attachments for Expense #<?php echo $expense_id; ?></h1>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Expense Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($expense['invoice_number']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($expense['category']); ?></p>
                    <p><strong>Amount:</strong> <?php echo htmlspecialchars($expense['amount']); ?></p>
                    <p><strong>Account:</strong> <?php echo htmlspecialchars($expense['account_name']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Expense Date:</strong> <?php echo htmlspecialchars($expense['expense_date']); ?></p>
                    <p><strong>Deadline:</strong> <?php echo htmlspecialchars($expense['expense_deadline']); ?></p>
                    <p><strong>Repartizare Luna:</strong> <?php echo htmlspecialchars($expense['repartizare_luna']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($expense['description']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attachments</h5>
            <a href="edit_expense.php?id=<?php echo $expense_id; ?>" class="btn btn-primary btn-sm">Add New Attachment</a>
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
                                        <a href="edit_expense.php?id=<?php echo $expense_id; ?>&delete_attachment=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No attachments found for this expense.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="expenses.php" class="btn btn-secondary">Back to Expenses</a>
    </div>
</div>

<?php require 'footer.php'; // Include the footer ?>