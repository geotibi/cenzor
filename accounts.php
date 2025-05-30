<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Fetch accounts from the database
$sql = "SELECT * FROM accounts";
$result = $conn->query($sql);
require 'header.php'; // Include the header
?>


<div class="container">
    <h1 class="mb-4">Accounts</h1>
    
    <!-- Button to Add a New Account -->
    <a href="add_account.php" class="btn btn-success mb-3">Add New Account</a>
    
    <!-- Account Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['balance']; ?></td>
                        <td>
                            <a href="edit_account.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No accounts found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require 'footer.php'; ?>