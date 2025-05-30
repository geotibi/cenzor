<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->query("SET SESSION sql_mode = ''");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $fund_type = $_POST['fund_type'];
    $account_id = $_POST['account_id'];
    $payment_date = $_POST['payment_date'];

    $stmt = $conn->prepare("INSERT INTO payments (apartment_id, amount, fund_type, account_id, payment_date, notes, rent_id) VALUES (NULL, ?, ?, ?, ?, 'comision pe operatiune', NULL)");
    $stmt->bind_param("dsis", $amount, $fund_type, $account_id, $payment_date);
    $stmt->execute();

    header("Location: index.php"); // Redirect after insert
    exit;
}

// Fetch enum values for fund_type
function getEnumValues($conn, $table, $column)
{
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $row = $result->fetch_assoc();
    preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
    $enum = str_getcsv($matches[1], ',', "'");
    return $enum;
}

$fund_types = getEnumValues($conn, 'payments', 'fund_type');

// Fetch accounts
$accounts = $conn->query("SELECT id, name FROM accounts ORDER BY name");
require 'header.php';
?>

<div class="container">
    <h2>Add Comision Payment</h2>
    <form method="post" action="">

        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" name="amount" id="amount" required>
        </div>

        <div class="mb-3">
            <label for="fund_type" class="form-label">Fund Type</label>
            <select name="fund_type" id="fund_type" class="form-select" required>
                <?php foreach ($fund_types as $type): ?>
                    <option value="<?= $type ?>" <?= $type === 'comision' ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="account_id" class="form-label">Account</label>
            <select name="account_id" id="account_id" class="form-select" required>
                <option value="">-- Select Account --</option>
                <?php while ($row = $accounts->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="payment_date" class="form-label">Payment Date</label>
            <input type="date" class="form-control" name="payment_date" id="payment_date" required>
        </div>

        <button type="submit" class="btn btn-primary">Add Payment</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>

    </form>
</div>
<?php require 'footer.php'; ?>