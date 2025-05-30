<?php
require 'auth.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO spaces (name, location) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $location);
        $stmt->execute();
        $stmt->close();
        header("Location: spaces.php");
        exit;
    } else {
        $error = "Name is required.";
    }
}

require 'header.php';
?>

<div class="container">
    <h1 class="mb-4">Add New Space</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" action="add_space.php">
        <div class="mb-3">
            <label for="name" class="form-label">Space Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>

        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <textarea class="form-control" id="location" name="location" rows="3"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Save</button>
        <a href="spaces.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require 'footer.php'; ?>
