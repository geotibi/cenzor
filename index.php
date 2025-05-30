<?php
require 'auth.php'; // restrict access to logged-in users
require 'header.php'; // include the header
?>

<div class="container">
    <h1 class="mb-4">Welcome to Cenzor Admin Dashboard</h1>
	<div class="mb-4">
		<a href="dashboard_facturi.php" class="btn btn-success w-100">Dashboard facturi</a>
	</div>
    <div class="mb-4">
		<a href="anexa.php" class="btn btn-info w-100">Anexa lista de plată</a>
	</div>
    <div class="row row-cols-1 row-cols-md-2 g-3">
        <div class="col">
            <a href="accounts.php" class="btn btn-primary w-100">Conturi</a>
        </div>
        <div class="col">
            <a href="account_funds.php" class="btn btn-primary w-100">Fonduri</a>
        </div>
    </div>

    <!-- Apartamente area -->
    <div style="height: 20px;"></div> <!-- spacer -->
	<div class="card mb-4">
		<div class="card-header">
			<h2>Apartamente</h2>
		</div>
		<div class="card-body">
			<div class="row row cols-1 row cols-md-2 row-cols-md-3 g-4" role="group" aria-label="Apartments actions">
                <div class="col">
                    <a href="apartments.php" class="btn btn-primary w-100">Apartamente</a>
                </div>                
                <div class="col">
                    <a href="number_of_persons.php" class="btn btn-primary w-100">Număr persoane</a>
                </div>
                <div class="col">
                    <a href="apartment_fees.php" class="btn btn-primary w-100">Întreținere</a>
                </div>
			</div>
		</div>
	</div>

    <div style="height: 20px;"></div> <!-- spacer -->
	<div class="card mb-4">
		<div class="card-header">
			<h2>Spații închiriate</h2>
		</div>
		<div class="card-body">
			<div class="row row cols-1 row cols-md-2 row-cols-md-3 g-4" role="group" aria-label="Spatii actions">
                <div class="col">
                    <a href="spaces.php" class="btn btn-primary w-100">Spații</a>
                </div>
                <div class="col">
                    <a href="tenants.php" class="btn btn-primary w-100">Chiriași</a>
                </div>
                <div class="col">
                    <a href="extras.php" class="btn btn-warning w-100">Facturi***</a>
                </div>
			</div>
		</div>
	</div>
    <!-- Financial area -->
    <div style="height: 20px;"></div> <!-- spacer -->
	<div class="card mb-4">
		<div class="card-header">
			<h2>Financiar</h2>
		</div>
		<div class="card-body">
			<div class="row row cols-1 row cols-md-2 row-cols-md-3 g-4" role="group" aria-label="Financial actions">
                <div class="col">
                    <a href="facturi_utilitati.php" class="btn btn-primary w-100">Facturi utilități/furnizori</a>
                </div>
                <div class="col">
                    <a href="expenses.php" class="btn btn-primary w-100">Facturi</a>
                </div>
                <div class="col">
                    <a href="add_payment.php" class="btn btn-primary w-100">Adaugă plată</a>
                </div>
                <div class="col">
                    <a href="add_comision.php" class="btn btn-primary w-100">Adaugă comision</a>
                </div>
                <div class="col">
                    <a href="account_transfer.php" class="btn btn-primary w-100">Transfer între conturi</a>
                </div>
                <div class="col">
                    <a href="extras.php" class="btn btn-warning w-100">Extras de cont</a>
                </div>
			</div>
		</div>
	</div>
</div>

<?php require 'footer.php'; // include the footer ?>