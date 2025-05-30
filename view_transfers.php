<!-- view_transfers.php -->
<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Add debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get all accounts for filter dropdowns
$accounts = $conn->query("SELECT id, name FROM accounts ORDER BY name");

// Get enum values for fund types
function getEnumValues($conn, $table, $column)
{
    try {
        $column_query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
        $column_result = $conn->query($column_query);

        if ($column_result->num_rows === 0) {
            return [];
        }

        $row = $column_result->fetch_assoc();

        if (!isset($row['Type'])) {
            return [];
        }

        if (preg_match("/^enum$$(.*)$$$/i", $row['Type'], $matches)) {
            $enum_values = str_getcsv($matches[1], ',', "'");
            return array_map('trim', $enum_values);
        }

        return [];
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

// Process filters
$filter_from_account = isset($_GET['from_account']) ? (int) $_GET['from_account'] : 0;
$filter_to_account = isset($_GET['to_account']) ? (int) $_GET['to_account'] : 0;
$filter_fund_type = isset($_GET['fund_type']) ? $_GET['fund_type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_amount_min = isset($_GET['amount_min']) ? (float) $_GET['amount_min'] : 0;
$filter_amount_max = isset($_GET['amount_max']) ? (float) $_GET['amount_max'] : 0;

// Build query with filters
$query = "
    SELECT t.*, a1.name as from_account, a2.name as to_account
    FROM transfers t
    JOIN accounts a1 ON t.from_account_id = a1.id
    JOIN accounts a2 ON t.to_account_id = a2.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($filter_from_account) {
    $query .= " AND t.from_account_id = ?";
    $params[] = $filter_from_account;
    $types .= "i";
}

if ($filter_to_account) {
    $query .= " AND t.to_account_id = ?";
    $params[] = $filter_to_account;
    $types .= "i";
}

if ($filter_fund_type) {
    $query .= " AND t.fund_type = ?";
    $params[] = $filter_fund_type;
    $types .= "s";
}

if ($filter_date_from) {
    $query .= " AND t.transfer_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if ($filter_date_to) {
    $query .= " AND t.transfer_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

if ($filter_amount_min > 0) {
    $query .= " AND t.amount >= ?";
    $params[] = $filter_amount_min;
    $types .= "d";
}

if ($filter_amount_max > 0) {
    $query .= " AND t.amount <= ?";
    $params[] = $filter_amount_max;
    $types .= "d";
}

$query .= " ORDER BY t.transfer_date DESC, t.id DESC";

// Execute the query with parameters
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$transfers = $stmt->get_result();

require 'header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Account Transfers</h1>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="from_account" class="form-label">From Account</label>
                            <select name="from_account" id="from_account" class="form-select">
                                <option value="">All Source Accounts</option>
                                <?php
                                $accounts->data_seek(0);
                                while ($row = $accounts->fetch_assoc()) {
                                    $selected = ($filter_from_account == $row['id']) ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="to_account" class="form-label">To Account</label>
                            <select name="to_account" id="to_account" class="form-select">
                                <option value="">All Destination Accounts</option>
                                <?php
                                $accounts->data_seek(0);
                                while ($row = $accounts->fetch_assoc()) {
                                    $selected = ($filter_to_account == $row['id']) ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="fund_type" class="form-label">Fund Type</label>
                            <select name="fund_type" id="fund_type" class="form-select">
                                <option value="">All Fund Types</option>
                                <?php foreach ($fund_types as $type): ?>
                                    <?php $selected = ($filter_fund_type == $type) ? 'selected' : ''; ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= $selected ?>>
                                        <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                value="<?= $filter_date_from ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                value="<?= $filter_date_to ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="amount_min" class="form-label">Min Amount</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="amount_min" name="amount_min" step="0.01"
                                    value="<?= $filter_amount_min > 0 ? $filter_amount_min : '' ?>">
                                <span class="input-group-text">RON</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="amount_max" class="form-label">Max Amount</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="amount_max" name="amount_max" step="0.01"
                                    value="<?= $filter_amount_max > 0 ? $filter_amount_max : '' ?>">
                                <span class="input-group-text">RON</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="view_transfers.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Stats -->
            <?php
            // Calculate summary statistics
            $total_transfers = $transfers->num_rows;
            $total_amount = 0;

            $transfers_copy = $transfers;
            while ($row = $transfers_copy->fetch_assoc()) {
                $total_amount += $row['amount'];
            }
            $transfers->data_seek(0); // Reset pointer
            ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Transfers</h5>
                            <h2 class="display-6"><?= $total_transfers ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Amount Transferred</h5>
                            <h2 class="display-6"><?= number_format($total_amount, 2) ?> RON</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfers Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transfer History</h5>
                    <a href="account_transfer.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> New Transfer
                    </a>
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
                                    echo "<tr><td colspan='7' class='text-center'>No transfers found matching the criteria</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="mt-4">
                <a href="export_transfers.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"
                    class="btn btn-success">
                    <i class="bi bi-file-excel"></i> Export to Excel
                </a>
                <a href="print_transfers.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>"
                    class="btn btn-secondary" target="_blank">
                    <i class="bi bi-printer"></i> Print Report
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Initialize Select2 for better dropdown experience
        $('#from_account, #to_account, #fund_type').select2({
            theme: 'bootstrap-5'
        });
    });
</script>

<?php require 'footer.php'; ?>