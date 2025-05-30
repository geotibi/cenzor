<?php
require 'auth.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $balance = $_POST['balance'];

    // Insert new account into the database
    $sql = "INSERT INTO accounts (name, balance) VALUES ('$name', '$balance')";
    if ($conn->query($sql) === TRUE) {
        header('Location: accounts.php');
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Account - Admin Dashboard</title>
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
        <h1 class="mb-4">Add New Account</h1>

        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Account Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="balance" class="form-label">Balance</label>
                <input type="number" step="0.01" class="form-control" id="balance" name="balance" required>
            </div>
            <button type="submit" class="btn btn-success">Save Account</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
