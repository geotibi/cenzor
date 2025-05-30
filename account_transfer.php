<!-- account_transfer.php -->
<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch all accounts for the dropdown
$accounts = $conn->query("SELECT id, name, balance FROM accounts ORDER BY name");

// Get enum values for fund types
function getEnumValues($conn, $table, $column)
{
    try {
        // Debug: Check if we can query the database at all
        $test_query = "SHOW TABLES";
        $test_result = $conn->query($test_query);
        if (!$test_result) {
            error_log("Database connection test failed: " . $conn->error);
            return [];
        }

        // Debug: Check if the table exists
        $table_query = "SHOW TABLES LIKE '$table'";
        $table_result = $conn->query($table_query);
        if ($table_result->num_rows === 0) {
            error_log("Table '$table' does not exist");
            return [];
        }

        // Debug: Check if the column exists
        $column_query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
        $column_result = $conn->query($column_query);
        if ($column_result->num_rows === 0) {
            error_log("Column '$column' does not exist in table '$table'");
            return [];
        }

        // Get the column type
        $row = $column_result->fetch_assoc();
        error_log("Column type for $table.$column: " . print_r($row, true));

        if (!isset($row['Type'])) {
            error_log("No 'Type' field in column result");
            return [];
        }

        // Try to extract enum values
        if (preg_match("/^enum\((.*)\)$/i", $row['Type'], $matches)) {
            error_log("Regex matched. Raw enum string: " . $matches[1]);

            // Parse the comma-separated list of enum values, handling quoted strings
            $enum_values = str_getcsv($matches[1], ',', "'");
            error_log("Parsed enum values: " . print_r($enum_values, true));

            return array_map('trim', $enum_values);
        } else {
            error_log("Regex did not match for type: " . $row['Type']);
            return [];
        }
    } catch (Exception $e) {
        error_log("Exception in getEnumValues: " . $e->getMessage());
        return [];
    }
}

// Get fund types from transfers table
$fund_types = getEnumValues($conn, 'transfers', 'fund_type');

// If fund_types is empty, use hardcoded values
if (empty($fund_types)) {
    $fund_types = ['special_fund', 'ball_fund', 'general'];
}

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $conn->begin_transaction();

        $from_account_id = (int) ($_POST['from_account_id'] ?? 0);
        $to_account_id = (int) ($_POST['to_account_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $fund_type = $_POST['fund_type'] ?? '';
        $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? '';

        // Validate inputs
        if (!$from_account_id || !$to_account_id || $amount <= 0 || !$fund_type) {
            throw new Exception("All fields are required and amount must be greater than 0.");
        }

        if ($from_account_id === $to_account_id) {
            throw new Exception("Source and destination accounts cannot be the same.");
        }

        // Check if source account has enough balance
        $balance_check = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
        $balance_check->bind_param("i", $from_account_id);
        $balance_check->execute();
        $balance_result = $balance_check->get_result();

        if ($balance_result->num_rows === 0) {
            throw new Exception("Source account not found.");
        }

        $balance_row = $balance_result->fetch_assoc();
        $current_balance = (float) $balance_row['balance'];

        if ($current_balance < $amount) {
            throw new Exception("Insufficient funds in source account. Available balance: " . number_format($current_balance, 2) . " RON");
        }

        // 1. Insert into transfers table
        $transfer_stmt = $conn->prepare("INSERT INTO transfers (from_account_id, to_account_id, amount, fund_type, transfer_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $transfer_stmt->bind_param("iidsss", $from_account_id, $to_account_id, $amount, $fund_type, $transfer_date, $notes);
        $transfer_stmt->execute();
        $transfer_id = $transfer_stmt->insert_id;
        $transfer_stmt->close();

        // 2. Create outgoing payment record
        $outgoing_notes = "Transfer to " . getAccountName($conn, $to_account_id) . ". " . $notes;
        $outgoing_stmt = $conn->prepare("INSERT INTO payments (apartment_id, amount, fund_type, account_id, payment_date, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
        $outgoing_stmt->bind_param("dsiss", $amount, $fund_type, $from_account_id, $transfer_date, $outgoing_notes);
        $outgoing_stmt->execute();
        $outgoing_payment_id = $outgoing_stmt->insert_id;
        $outgoing_stmt->close();

        // 3. Create incoming payment record
        $incoming_notes = "Transfer from " . getAccountName($conn, $from_account_id) . ". " . $notes;
        $incoming_stmt = $conn->prepare("INSERT INTO payments (apartment_id, amount, fund_type, account_id, payment_date, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
        $incoming_stmt->bind_param("dsiss", $amount, $fund_type, $to_account_id, $transfer_date, $incoming_notes);
        $incoming_stmt->execute();
        $incoming_payment_id = $incoming_stmt->insert_id;
        $incoming_stmt->close();

        // 4. Update account balances
        $update_from = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
        $update_from->bind_param("di", $amount, $from_account_id);
        $update_from->execute();
        $update_from->close();

        $update_to = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $update_to->bind_param("di", $amount, $to_account_id);
        $update_to->execute();
        $update_to->close();

        // 5. Create payment links to connect the transfer with payments
        $link_stmt = $conn->prepare("INSERT INTO payment_links (payment_id, expense_id, apartment_fee_id, rent_id, tenant_invoice_id, building_bills_id, amount, fund_type, notes) VALUES (?, NULL, NULL, NULL, NULL, NULL, ?, ?, ?)");

        // Link for outgoing payment
        $link_stmt->bind_param("idss", $outgoing_payment_id, $amount, $fund_type, $notes);
        $link_stmt->execute();

        // Link for incoming payment
        $link_stmt->bind_param("idss", $incoming_payment_id, $amount, $fund_type, $notes);
        $link_stmt->execute();

        $link_stmt->close();

        $conn->commit();
        $success_message = "Transfer completed successfully. Transfer ID: " . $transfer_id;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
        error_log("Transfer error: " . $e->getMessage());
    }
}

// Helper function to get account name
function getAccountName($conn, $account_id)
{
    $stmt = $conn->prepare("SELECT name FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }

    return "Unknown Account";
}

require 'header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Account Transfer</h1>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Transfer Funds Between Accounts</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row mb-3">
                            <label for="from_account_id" class="col-sm-3 col-form-label">From Account:</label>
                            <div class="col-sm-9">
                                <select name="from_account_id" id="from_account_id" class="form-select" required>
                                    <option value="">--Select Source Account--</option>
                                    <?php
                                    $accounts->data_seek(0);
                                    while ($row = $accounts->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['name']} (Balance: " . number_format($row['balance'], 2) . " RON)</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="to_account_id" class="col-sm-3 col-form-label">To Account:</label>
                            <div class="col-sm-9">
                                <select name="to_account_id" id="to_account_id" class="form-select" required>
                                    <option value="">--Select Destination Account--</option>
                                    <?php
                                    $accounts->data_seek(0);
                                    while ($row = $accounts->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['name']} (Balance: " . number_format($row['balance'], 2) . " RON)</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="amount" class="col-sm-3 col-form-label">Amount:</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="number" id="amount" name="amount" step="0.01" class="form-control"
                                        required>
                                    <span class="input-group-text">RON</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="fund_type" class="col-sm-3 col-form-label">Fund Type:</label>
                            <div class="col-sm-9">
                                <select name="fund_type" id="fund_type" class="form-select" required>
                                    <option value="">--Select Fund Type--</option>
                                    <?php foreach ($fund_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="transfer_date" class="col-sm-3 col-form-label">Transfer Date:</label>
                            <div class="col-sm-9">
                                <input type="date" id="transfer_date" name="transfer_date" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="notes" class="col-sm-3 col-form-label">Notes:</label>
                            <div class="col-sm-9">
                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">Complete Transfer</button>
                                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Transfers Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Transfers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>From Account</th>
                                    <th>To Account</th>
                                    <th>Amount</th>
                                    <th>Fund Type</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $transfers_query = "
                                    SELECT t.*, a1.name as from_account, a2.name as to_account
                                    FROM transfers t
                                    JOIN accounts a1 ON t.from_account_id = a1.id
                                    JOIN accounts a2 ON t.to_account_id = a2.id
                                    ORDER BY t.transfer_date DESC, t.id DESC
                                    LIMIT 10
                                ";
                                $transfers = $conn->query($transfers_query);

                                if ($transfers->num_rows > 0) {
                                    while ($row = $transfers->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>{$row['id']}</td>";
                                        echo "<td>" . date('d-m-Y', strtotime($row['transfer_date'])) . "</td>";
                                        echo "<td>{$row['from_account']}</td>";
                                        echo "<td>{$row['to_account']}</td>";
                                        echo "<td>" . number_format($row['amount'], 2) . " RON</td>";
                                        echo "<td>" . ucfirst(str_replace('_', ' ', $row['fund_type'])) . "</td>";
                                        echo "<td>{$row['notes']}</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center'>No transfers found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Initialize Select2 for better dropdown experience
        $('#from_account_id, #to_account_id, #fund_type').select2({
            theme: 'bootstrap-5'
        });

        // Prevent selecting the same account for source and destination
        $('#from_account_id, #to_account_id').change(function () {
            const fromAccount = $('#from_account_id').val();
            const toAccount = $('#to_account_id').val();

            if (fromAccount && toAccount && fromAccount === toAccount) {
                alert('Source and destination accounts cannot be the same.');
                $(this).val('').trigger('change');
            }
        });
    });
</script>

<?php require 'footer.php'; ?>