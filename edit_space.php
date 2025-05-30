<?php
require 'auth.php';
require 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid space ID.";
    exit;
}

$id = (int) $_GET['id'];

// Fetch current data
$sql = "SELECT * FROM spaces WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows !== 1) {
    echo "Space not found.";
    exit;
}

$space = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);

    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE spaces SET name = ?, location = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $location, $id);
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
    <h1 class="mb-4">Edit Space</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" action="edit_space.php?id=<?php echo $id; ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Space Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($space['name']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <textarea class="form-control" id="location" name="location" rows="3"><?php echo htmlspecialchars($space['location']); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
        <a href="spaces.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require 'footer.php'; ?>
