<?php
ob_start();
session_start();
require 'db.php';

// Redirect to index if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Basic brute-force protection (per session)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } elseif ($_SESSION['login_attempts'] >= 5) {
        $error = "Too many failed login attempts. Please try again later.";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, password_hash, first_name, last_name, email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($user_id, $hash, $first_name, $last_name, $email);

        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['login_attempts'] = 0; // reset on success
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $error = "Invalid login credentials.";
        }
        $stmt->close();
    }
}

require 'login-header.php';
?>

<form method="post" class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input name="username" type="text" class="form-control" id="username" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input name="password" type="password" class="form-control" id="password" required>
    </div>

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="d-grid">
        <button type="submit" class="btn btn-primary">Login</button>
    </div>
</form>

</div>
</div>
</div>
</div>
</div>

<?php require 'footer.php'; ?>