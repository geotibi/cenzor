<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Fetch apartments from the database
$sql = "SELECT * FROM apartments";
$result = $conn->query($sql);

// Include the header
require 'header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Apartments</h1>
            
            <!-- Button to Add a New Apartment -->
            <div class="mb-3">
                <a href="add_apartment.php" class="btn btn-success">Add New Apartment</a>
            </div>
            
            <!-- Apartment Table with responsive wrapper -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Scara</th>
                            <th>Numar</th>
                            <th>Locatar</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['scara']; ?></td>
                                    <td><?php echo $row['number']; ?></td>
                                    <td><?php echo $row['owner_name']; ?></td>
                                    <td><?php echo $row['notes']; ?></td>
                                    <td>
                                        <a href="edit_apartment.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No apartments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; // include the footer ?>