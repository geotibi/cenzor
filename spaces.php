<?php
require 'auth.php';
require 'db.php';

$sql = "SELECT * FROM spaces ORDER BY id DESC";
$result = $conn->query($sql);

require 'header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">Spaces</h1>
    
    <a href="add_space.php" class="btn btn-success mb-3">Add New Space</a>

    <table id="spaces_table" class="table table-bordered table-striped table-hover w-100">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($space = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $space['id']; ?></td>
                    <td><?php echo htmlspecialchars($space['name']); ?></td>
                    <td><?php echo htmlspecialchars($space['location']); ?></td>
                    <td>
                        <a href="edit_space.php?id=<?php echo $space['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>


    </table>
</div>

<script>
$(document).ready(function() {
    $('#spaces_table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        language: {
            emptyTable: "No spaces found."
        }
    });
});
</script>

<?php require 'footer.php'; ?>
