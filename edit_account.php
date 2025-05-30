<?php
require 'auth.php';
require 'db.php';

if (isset($_GET['id'])) {
    $account_id = $_GET['id'];

    // Fetch the account details
    $sql = "SELECT * FROM accounts WHERE id = $account_id";
    $result = $conn->query($sql);
    $account = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $balance = $_POST['balance'];

        // Update account in the database
        $sql = "UPDATE accounts SET name = '$name', balance = '$balance' WHERE id = $account_id";
        if ($conn->query($sql) === TRUE) {
            header('Location: accounts.php');
            exit;
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Account - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Cenzor Admin</a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="mb-4">Edit Account</h1>

        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Account Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo $account['name']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="balance" class="form-label">Balance</label>
                <input type="number" step="0.01" class="form-control" id="balance" name="balance" value="<?php echo $account['balance']; ?>" required>
            </div>
            <button type="submit" class="btn btn-warning">Update Account</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
