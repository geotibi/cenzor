<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title ?? 'Admin Dashboard - Cenzor') ?></title>


    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1 0 auto;
        }

        @media (min-width: 992px) {
            .dropdown:hover .dropdown-menu {
                display: block;
                margin-top: 0;
            }
        }

        .dropdown-menu {
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease-in-out;
            opacity: 0;
            visibility: hidden;
            display: block;
            margin-top: -10px;
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            margin-top: 0;
        }

        .dropdown-menu:not(.show):not(:hover),
        .dropdown:not(:hover) .dropdown-menu:not(.show) {
            display: none;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Cenzor Admin</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- Main Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="mainMenuDropdown" role="button" data-bs-toggle="dropdown">
                            Meniu Principal
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Apartamente</h6></li>
                            <li><a class="dropdown-item" href="apartments.php">Apartamente</a></li>
                            <li><a class="dropdown-item" href="add_apartment.php">Adaugă apartament</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Chiriași</h6></li>
                            <li><a class="dropdown-item" href="tenants.php">Chiriași</a></li>
                            <li><a class="dropdown-item" href="add_tenant.php">Adaugă chiriaș</a></li>
                            <li><a class="dropdown-item" href="tenant_contracts.php">Contracte chiriași</a></li>
                        </ul>
                    </li>

                    <!-- Financial -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="financialDropdown" role="button" data-bs-toggle="dropdown">
                            Financiar
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Payments</h6></li>
                            <li><a class="dropdown-item" href="add_payment.php">Adaugă plată</a></li>
                            <li><a class="dropdown-item" href="payments.php">Vizualizare plăți</a></li>
                            <li><a class="dropdown-item" href="account_transfer.php">Transfer între conturi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Expenses</h6></li>
                            <li><a class="dropdown-item" href="expenses.php">View Expenses</a></li>
                            <li><a class="dropdown-item" href="add_expense.php">Add Expense</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Facturi</h6></li>
                            <li><a class="dropdown-item" href="facturi_utilitati.php">Facturi utilități/furnizori</a></li>
                            <li><a class="dropdown-item" href="add_bill.php">Adaugă factură</a></li>
                        </ul>
                    </li>

                    <!-- Reports -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                            Rapoarte
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="apartment_fees.php">Întreținere</a></li>
                            <li><a class="dropdown-item" href="apartment_unpaid_summary.php">Sumar întreținere neplătită</a></li>
                            <li><a class="dropdown-item" href="view_transfers.php">Istoric transferuri</a></li>
                            <li><a class="dropdown-item" href="accounts.php">Conturi</a></li>
                        </ul>
                    </li>
                </ul>

                <div class="d-flex align-items-center text-white me-3">
                    <?php if ($first_name && $last_name): ?>
                        <span class="me-3">Welcome, <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?> (<?= htmlspecialchars($email) ?>)</span>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        $(document).ready(function () {
            if (window.innerWidth >= 992) {
                $('.dropdown').on('mouseenter', function () {
                    $('.dropdown-menu.show').removeClass('show');
                    $(this).find('.dropdown-menu').addClass('show');
                }).on('mouseleave', function () {
                    $(this).find('.dropdown-menu').removeClass('show');
                });
            }

            $('.dropdown-toggle').on('click', function () {
                var $dropdown = $(this).parent('.dropdown');
                var $menu = $dropdown.find('.dropdown-menu');
                if ($menu.hasClass('show')) {
                    $menu.removeClass('show');
                    return;
                }
                $('.dropdown-menu.show').removeClass('show');
                $menu.addClass('show');
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });
        });
    </script>

    <main class="flex-shrink-0">
