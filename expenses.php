<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Fetch expenses from the database
$sql = "SELECT e.id AS expense_id, e.invoice_number, e.description, e.category, e.amount, 
               e.expense_date, e.expense_deadline, e.repartizare_luna, 
               a.id AS account_id, a.name AS account_name,
               IF(pl.expense_id IS NOT NULL, 'paid', 'unpaid') AS payment_status,
               (SELECT COUNT(*) FROM expense_attachments WHERE expense_id = e.id) AS attachment_count
        FROM expenses e
        LEFT JOIN payment_links pl ON e.id = pl.expense_id
        LEFT JOIN accounts a ON e.account_id = a.id";
$result = $conn->query($sql);
require 'header.php'; // Include the header
?>

<div class="container-fluid">
	<h1 class="mb-4">Expenses</h1>
	
	<!-- Button to Add a New Expense -->
	<a href="add_expense.php" class="btn btn-success mb-3">Add New Expense</a>
	
	<!-- Filters -->
	<div class="mb-3">
		<button id="filter-paid" class="btn btn-success btn-sm">Show Paid</button>
		<button id="filter-unpaid" class="btn btn-danger btn-sm">Show Unpaid</button>
		<button id="filter-attachments" class="btn btn-info btn-sm">Has Attachments</button>
		<button id="clear-filters" class="btn btn-secondary btn-sm">Clear Filters</button>
	</div>

	<!-- Expenses Table -->
	<table id="expenses_table" class="table table-bordered table-striped table-hover w-100">
		<thead>
			<tr>
				<th>ID</th>
				<th>Description</th>
				<th>Invoice Number</th>
				<th>Category</th>
				<th>Amount</th>
				<th>Date</th>
				<th>Deadline</th>
				<th>Account</th>
				<th>Repartizare luna</th>
				<th>Status</th>
				<th>Attachments</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($result->num_rows > 0): ?>
				<?php while ($row = $result->fetch_assoc()): ?>
					<tr>
						<td><?php echo $row['expense_id']; ?></td>
						<td><?php echo $row['description']; ?></td>
						<td><?php echo $row['invoice_number']; ?></td>
						<td><?php echo $row['category']; ?></td>
						<td><?php echo $row['amount']; ?></td>
						<td><?php echo $row['expense_date']; ?></td>
						<td><?php echo $row['expense_deadline']; ?></td>
						<td><?php echo $row['account_name']; ?></td>
						<td><?php echo $row['repartizare_luna']; ?></td>
						<td data-search="<?php echo $row['payment_status'] == 'paid' ? 'Paid' : 'Unpaid'; ?>">
							<?php if ($row['payment_status'] == 'paid'): ?>
								<span class="text-success">&#10004; Paid</span>
							<?php else: ?>
								<span class="text-danger">&#10006; Unpaid</span>
							<?php endif; ?>
						</td>
						<td data-search="<?php echo $row['attachment_count'] > 0 ? 'Yes' : 'No'; ?>">
							<?php if ($row['attachment_count'] > 0): ?>
								<span class="text-success">&#10004; <?php echo $row['attachment_count']; ?></span>
							<?php else: ?>
								<span class="text-muted">&#10006; None</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="edit_expense.php?id=<?php echo $row['expense_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
							<?php if ($row['attachment_count'] > 0): ?>
								<a href="view_expense_attachments.php?expense_id=<?php echo $row['expense_id']; ?>" class="btn btn-info btn-sm">View Files</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr>
					<td colspan="12">No expenses found.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
	var table = $('#expenses_table').DataTable({
		responsive: true,
		autoWidth: false,
		pageLength: 25,
		// initComplete: function () {
		// 	this.api().columns().every(function () {
		// 		var column = this;
		// 		var input = $('<input type="text" placeholder="Search" style="width:100%;"/>')
		// 			.appendTo($(column.header()))
		// 			.on('keyup change clear', function () {
		// 				if (column.search() !== this.value) {
		// 					column.search(this.value).draw();
		// 				}
		// 			});
		// 	});
		// }
	});

	// Filter buttons
	$('#filter-paid').click(function() {
		table.column(9).search('^Paid$', true, false).draw();  // Match exactly 'Paid'
	});

	$('#filter-unpaid').click(function() {
		table.column(9).search('^Unpaid$', true, false).draw();  // Match exactly 'Unpaid'
	});
	
	$('#filter-attachments').click(function() {
		table.column(10).search('Yes', true, false).draw();  // Match expenses with attachments
	});

	$('#clear-filters').click(function() {
		table.columns().search('').draw(); // Clear all filters
	});
});
</script>

<?php require 'footer.php'; // Include the footer ?>