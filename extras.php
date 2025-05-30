<?php
require 'auth.php';
require 'db.php';

// Fetch accounts for the dropdown
$sql_accounts = "SELECT id, name FROM accounts ORDER BY name ASC";
$result_accounts = $conn->query($sql_accounts);

// Get selected account from GET
$selected_account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;


// Fetch both payments and expense payments
$sql = "
    SELECT 
        p.payment_date, 
        p.account_id, 
        p.amount AS payment_amount, 
        p.notes AS payment_description,
        p.fund_type,
        p.apartment_id AS apartment_id,
        'payment' AS payment_type
    FROM payments p
    " . ($selected_account_id ? "WHERE p.account_id = $selected_account_id" : "") . "
    UNION ALL
    SELECT 
        ep.payment_date, 
        ep.account_id, 
        ep.amount AS payment_amount, 
        ep.notes AS payment_description,
        NULL AS fund_type,
        NULL AS apartment_id,
        'expense_payment' AS payment_type
    FROM expense_payments ep
    " . ($selected_account_id ? "WHERE ep.account_id = $selected_account_id" : "") . "
    ORDER BY payment_date ASC
";


$result = $conn->query($sql);

if (!$result) {
    echo "Error fetching data: " . $conn->error;
    exit;
}

$account_summary = [];
$balance = 0;

while ($row = $result->fetch_assoc()) {
    $is_income = false;
    $is_payment = false;

    if ($row['payment_type'] == 'payment' && ($row['fund_type'] == 'intretinere' || $row['fund_type'] == 'fond special' || $row['fund_type'] == 'fond rulment' || $row['fund_type'] == 'utilitati chiriasi' || $row['fund_type'] == 'chirie' || ($row['fund_type'] == 'transfer' && strpos($row['payment_description'], 'Transfer from') !== false))) {
        $is_income = true;
    } elseif (
        $row['payment_type'] == 'expense_payment' ||
        ($row['payment_type'] == 'payment' && $row['fund_type'] == 'facturi utilitati' || $row['fund_type'] == 'other' || $row['fund_type'] == 'comision' || ($row['fund_type'] == 'transfer' && strpos($row['payment_description'], 'Transfer to') !== false))
    ) {
        $is_payment = true;
    } elseif ($row['payment_type'] == 'transfer') {
        // Transfers: if selected account is TO, it's income. If FROM, it's payment.
        if (isset($_GET['account_id'])) {
            if ($row['account_id'] == $selected_account_id) {
                $is_income = true; // incoming transfer
            } else {
                $is_payment = true; // outgoing transfer
            }
        }
    }

    // Update balance
    if ($is_income) {
        $balance += $row['payment_amount'];
    } elseif ($is_payment) {
        $balance -= $row['payment_amount'];
    }

    $account_summary[] = [
        'date' => $row['payment_date'],
        'apartment_number' => ($is_income && $row['apartment_id'] ? 'Ap. ' . $row['apartment_id'] : ''),
        'account_number' => $row['account_id'],
        'anexa' => '', // You can put something here for transfers if needed
        'description' => $row['payment_description'],
        'income' => $is_income ? $row['payment_amount'] : 0,
        'payment' => $is_payment ? $row['payment_amount'] : 0,
        'balance' => $balance
    ];
}

$account_summary = array_reverse($account_summary);

require 'header.php';
?>

<div class="container">
	<form method="GET" class="mb-4">
		<div class="row g-3 align-items-center">
			<div class="col-auto">
				<label for="account_id" class="col-form-label">Select Account:</label>
			</div>
			<div class="col-auto">
				<select name="account_id" id="account_id" class="form-select" onchange="this.form.submit()">
					<option value="">-- All Accounts --</option>
					<?php while($acc = $result_accounts->fetch_assoc()): ?>
						<option value="<?php echo $acc['id']; ?>" <?php echo ($selected_account_id == $acc['id']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($acc['name']); ?>
						</option>
					<?php endwhile; ?>
				</select>
			</div>
		</div>
	</form>
    <h1 class="mb-4">Account Summary</h1>

    <table class="table table-striped">
        <thead>
            <tr>
                <th scope="col">Data</th>
                <th scope="col">Nr crt</th>
                <th scope="col">Nr act casa</th>
                <th scope="col">Anexa</th>
                <th scope="col">Explicatii</th>
                <th scope="col">Incasari</th>
                <th scope="col">Plati</th>
                <th scope="col">Sold</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($account_summary as $row): ?>
                <tr>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $row['apartment_number']; ?></td>
                    <td><?php echo ''; ?></td>
                    <td><?php echo $row['anexa']; ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['income'] > 0 ? number_format($row['income'], 2) : ''; ?></td>
                    <td><?php echo $row['payment'] > 0 ? number_format($row['payment'], 2) : ''; ?></td>
                    <td><?php echo number_format($row['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require 'footer.php'; ?>
