<?php
require 'auth.php';
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable strict mode for the current session
$conn->query("SET SESSION sql_mode = ''");

// Add more debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create payment_credits table if it doesn't exist
$create_credits_table = "
CREATE TABLE IF NOT EXISTS payment_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    payment_id INT NOT NULL,
    credit_amount DECIMAL(10,2) NOT NULL,
    remaining_amount DECIMAL(10,2) NOT NULL,
    fund_type ENUM('ball_fund', 'utilities', 'special_fund', 'penalties', 'previous_unpaid', 'other') NOT NULL,
    created_date DATE NOT NULL,
    notes TEXT,
    status ENUM('active', 'fully_used', 'expired') DEFAULT 'active',
    FOREIGN KEY (apartment_id) REFERENCES apartments(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_apartment_status (apartment_id, status),
    INDEX idx_fund_type (fund_type)
)";

$conn->query($create_credits_table);

// Create credit_usage table to track how credits are used
$create_usage_table = "
CREATE TABLE IF NOT EXISTS credit_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_id INT NOT NULL,
    used_payment_id INT NOT NULL,
    used_amount DECIMAL(10,2) NOT NULL,
    used_date DATE NOT NULL,
    notes TEXT,
    FOREIGN KEY (credit_id) REFERENCES payment_credits(id),
    FOREIGN KEY (used_payment_id) REFERENCES payments(id)
)";

$conn->query($create_usage_table);

// Function to apply available credits to a payment
function applyAvailableCredits($conn, $apartment_id, $fund_type, $payment_id, $required_amount)
{
    $applied_credits = 0;

    // Get available credits for this apartment and fund type
    $credits_query = "
    SELECT id, remaining_amount 
    FROM payment_credits 
    WHERE apartment_id = ? AND fund_type = ? AND status = 'active' AND remaining_amount > 0
    ORDER BY created_date ASC
    ";

    $stmt = $conn->prepare($credits_query);
    $stmt->bind_param('is', $apartment_id, $fund_type);
    $stmt->execute();
    $credits = $stmt->get_result();

    while ($credit = $credits->fetch_assoc() && $applied_credits < $required_amount) {
        $credit_id = $credit['id'];
        $available_credit = $credit['remaining_amount'];
        $needed_amount = $required_amount - $applied_credits;

        $use_amount = min($available_credit, $needed_amount);

        if ($use_amount > 0) {
            // Record credit usage
            $usage_stmt = $conn->prepare("
            INSERT INTO credit_usage (credit_id, used_payment_id, used_amount, used_date, notes)
            VALUES (?, ?, ?, CURDATE(), 'Auto-applied to payment')
            ");
            $usage_stmt->bind_param('iid', $credit_id, $payment_id, $use_amount);
            $usage_stmt->execute();

            // Update remaining credit
            $new_remaining = $available_credit - $use_amount;
            $update_stmt = $conn->prepare("
            UPDATE payment_credits 
            SET remaining_amount = ?, status = CASE WHEN ? <= 0 THEN 'fully_used' ELSE 'active' END
            WHERE id = ?
            ");
            $update_stmt->bind_param('ddi', $new_remaining, $new_remaining, $credit_id);
            $update_stmt->execute();

            $applied_credits += $use_amount;
        }
    }

    return $applied_credits;
}

// Function to create credit for overpayment
function createPaymentCredit($conn, $apartment_id, $payment_id, $overpaid_amount, $fund_type, $notes = '')
{
    $stmt = $conn->prepare("
    INSERT INTO payment_credits (apartment_id, payment_id, credit_amount, remaining_amount, fund_type, created_date, notes, status)
    VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'active')
    ");
    $stmt->bind_param('iiddss', $apartment_id, $payment_id, $overpaid_amount, $overpaid_amount, $fund_type, $notes);
    return $stmt->execute();
}

// Helper to fetch ENUM values with detailed debugging
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

// Function to validate if a foreign key exists
function validateForeignKey($conn, $table, $column, $value)
{
    if ($value === null) {
        return true; // Null values are allowed if the column allows NULL
    }

    $stmt = $conn->prepare("SELECT 1 FROM $table WHERE $column = ? LIMIT 1");
    $stmt->bind_param('i', $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

// Try to get enum values with debugging
$fund_types = getEnumValues($conn, 'payments', 'fund_type');
error_log("Fund types from database: " . print_r($fund_types, true));

// If fund_types is empty, use hardcoded values from the schema
if (empty($fund_types)) {
    error_log("Using hardcoded fund types");
    $fund_types = ['ball_fund', 'utilities', 'special_fund', 'penalties', 'previous_unpaid', 'other'];
}

// Fetch dropdown data
$apartments = $conn->query("SELECT id, number, scara, owner_name FROM apartments ORDER BY scara, number");
$accounts = $conn->query("SELECT id, name FROM accounts ORDER BY name");
$expenses = $conn->query("SELECT id, description FROM expenses ORDER BY id DESC");

// Get all apartment fees initially (will be filtered by JavaScript)
// Updated to use 'total' instead of 'total_amount' and calculate unpaid amount correctly
$apartment_fees = $conn->query("SELECT af.id, af.apartment_id, af.month_key, a.number, a.scara, a.owner_name, 
                               af.total, COALESCE(SUM(pl.amount), 0) as paid_amount
                               FROM apartment_fees af
                               JOIN apartments a ON af.apartment_id = a.id
                               LEFT JOIN payment_links pl ON af.id = pl.apartment_fee_id
                               GROUP BY af.id, af.apartment_id, af.month_key, a.number, a.scara, a.owner_name, af.total
                               HAVING af.total > COALESCE(SUM(pl.amount), 0)
                               ORDER BY a.scara, a.number, af.month_key DESC");

// Replace rents with tenant_invoices
$tenant_invoices = $conn->query("SELECT ti.id, ti.invoice_number, ti.amount, ti.currency, ti.due_date, t.name AS tenant_name, ti.status,
                                ti.rent_amount, ti.utilities_amount
                                FROM tenant_invoices ti 
                                JOIN tenants t ON ti.tenant_id = t.id 
                                WHERE ti.status != 'paid' 
                                ORDER BY ti.due_date DESC");
$bills = $conn->query("SELECT b.id AS id, bill_type, bill_no, bill_date, b.amount AS amount,IF(pl.building_bills_id IS NOT NULL, 'paid', 'unpaid') AS payment_status, b.description FROM building_bills b LEFT JOIN payment_links pl ON b.id =pl.building_bills_id LEFT JOIN accounts a ON b.account_id = a.id WHERE pl.building_bills_id IS NULL ORDER BY id DESC");

// Initialize variables
$success_message = '';
$error_message = '';
$payment_id = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $conn->begin_transaction();

        $link_type = $_POST['link_type'] ?? '';
        $payment_fund_type = $_POST['payment_fund_type'] ?? ''; // Fund type for the payments table
        $account_id = (int) ($_POST['account_id'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? '';

        $link_ids = array_filter($_POST['link_ids'], function ($id) {
            return !empty($id) && is_numeric($id);
        });
        error_log("Submitted link_ids: " . print_r($link_ids, true));

        // For split tenant invoice payments
        $split_payment = isset($_POST['split_payment']) && $_POST['split_payment'] === 'yes';
        $primary_amount = (float) ($_POST['primary_amount'] ?? 0);
        $secondary_amount = (float) ($_POST['secondary_amount'] ?? 0);
        $primary_fund_type = $_POST['primary_fund_type'] ?? '';
        $secondary_fund_type = $_POST['secondary_fund_type'] ?? '';

        if (!$link_type || !$payment_fund_type || !$account_id || empty($link_ids)) {
            throw new Exception("Missing required fields.");
        }

        // Ensure fund_type is in lowercase to match the ENUM values
        $payment_fund_type = trim($payment_fund_type);
        $payment_fund_type = filter_var($payment_fund_type, FILTER_SANITIZE_STRING);

        if (!in_array($payment_fund_type, $fund_types)) {
            throw new Exception("Invalid payment fund type: '$payment_fund_type'. Valid types are: " . implode(", ", $fund_types));
        }

        // Validate fund types for split payment
        if ($split_payment) {
            if (!$primary_fund_type || !$secondary_fund_type) {
                throw new Exception("Both primary and secondary fund types are required for split payments.");
            }

            $primary_fund_type = trim($primary_fund_type);
            $primary_fund_type = filter_var($primary_fund_type, FILTER_SANITIZE_STRING);

            $secondary_fund_type = trim($secondary_fund_type);
            $secondary_fund_type = filter_var($secondary_fund_type, FILTER_SANITIZE_STRING);

            if (!in_array($primary_fund_type, $fund_types)) {
                throw new Exception("Invalid primary fund type: '$primary_fund_type'. Valid types are: " . implode(", ", $fund_types));
            }

            if (!in_array($secondary_fund_type, $fund_types)) {
                throw new Exception("Invalid secondary fund type: '$secondary_fund_type'. Valid types are: " . implode(", ", $fund_types));
            }

            if ($primary_amount <= 0 || $secondary_amount <= 0) {
                throw new Exception("Both primary and secondary amounts must be greater than 0 for split payments.");
            }
        }

        $apartment_id = isset($_POST['apartment_id']) && $_POST['apartment_id'] !== '' ? (int) $_POST['apartment_id'] : null;

        // Optional: if required only for some link_types (not 'bill' or 'tenant_invoice')
        if ($link_type !== 'bill' && $link_type !== 'tenant_invoice' && $link_type !== 'fee' && is_null($apartment_id)) {
            throw new Exception("Apartment must be selected.");
        }

        // For fee type, we don't require apartment_id if show_all_fees is checked
        if ($link_type === 'fee' && is_null($apartment_id) && !isset($_POST['show_all_fees'])) {
            throw new Exception("Apartment must be selected when not showing all fees.");
        }

        // Validate apartment_id if provided
        if ($apartment_id !== null && !validateForeignKey($conn, 'apartments', 'id', $apartment_id)) {
            throw new Exception("Invalid apartment ID: $apartment_id");
        }

        // Calculate total amount if building bills
        $amount = 0;
        if ($link_type === 'bill') {
            $ids_str = implode(',', array_map('intval', $link_ids));
            $res = $conn->query("SELECT SUM(amount) as total FROM building_bills WHERE id IN ($ids_str)");
            $row = $res->fetch_assoc();
            $amount = (float) $row['total'];
        } else if ($link_type === 'tenant_invoice' && $split_payment) {
            // For split payments, use the sum of primary and secondary amounts
            $amount = $primary_amount + $secondary_amount;
        } else {
            $amount = (float) ($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than 0.");
            }
        }

        // Validate account_id
        if (!validateForeignKey($conn, 'accounts', 'id', $account_id)) {
            throw new Exception("Invalid account ID: $account_id");
        }

        // For fee payments with show_all_fees, we need to determine which apartment to associate with the payment
        // We'll use the first selected fee's apartment
        if ($link_type === 'fee' && is_null($apartment_id) && isset($_POST['show_all_fees']) && !empty($link_ids)) {
            $first_fee_id = (int) $link_ids[0];
            $fee_query = $conn->prepare("SELECT apartment_id FROM apartment_fees WHERE id = ?");
            $fee_query->bind_param('i', $first_fee_id);
            $fee_query->execute();
            $fee_result = $fee_query->get_result();

            if ($fee_result->num_rows > 0) {
                $fee_row = $fee_result->fetch_assoc();
                $apartment_id = (int) $fee_row['apartment_id'];
            }
            $fee_query->close();
        }

        // Create a single payment record in the payments table
        $apartment_id_to_bind = ($apartment_id === null) ? null : (int) $apartment_id;
        $stmt = $conn->prepare("INSERT INTO payments (apartment_id, amount, fund_type, account_id, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "idsiss",
            $apartment_id_to_bind,
            $amount,
            $payment_fund_type,
            $account_id,
            $payment_date,
            $notes
        );

        $stmt->execute();
        $payment_id = $stmt->insert_id;
        $stmt->close();

        // Debug log for link IDs
        error_log("Link type: $link_type, Link IDs: " . implode(', ', $link_ids));

        // Insert payment_links with credit handling
        foreach ($link_ids as $link_id) {
            $expense_id = $apartment_fee_id = $tenant_invoice_id = $building_bills_id = null;

            switch ($link_type) {
                case 'expense':
                    $expense_id = (int) $link_id;
                    if (!validateForeignKey($conn, 'expenses', 'id', $expense_id)) {
                        throw new Exception("Invalid expense ID: $expense_id");
                    }
                    break;

                case 'fee':
                    $apartment_fee_id = (int) $link_id;
                    if ($apartment_fee_id <= 0) {
                        continue 2; // Skip invalid or empty selections
                    }
                    if (!validateForeignKey($conn, 'apartment_fees', 'id', $apartment_fee_id)) {
                        throw new Exception("Invalid apartment fee ID: $apartment_fee_id");
                    }
                    break;

                case 'tenant_invoice':
                    $tenant_invoice_id = (int) $link_id;
                    if (!validateForeignKey($conn, 'tenant_invoices', 'id', $tenant_invoice_id)) {
                        throw new Exception("Invalid tenant invoice ID: $tenant_invoice_id");
                    }
                    break;

                case 'bill':
                    if (empty($link_id) || !is_numeric($link_id)) {
                        throw new Exception("Invalid or missing link_id for building bill.");
                    }
                    $building_bills_id = (int) $link_id;
                    if (!validateForeignKey($conn, 'building_bills', 'id', $building_bills_id)) {
                        throw new Exception("Invalid building bill ID: $building_bills_id");
                    }
                    break;

                default:
                    throw new Exception("Invalid link type.");
            }

            // Handle split payments for tenant invoices
            if ($link_type === 'tenant_invoice' && $split_payment) {
                // Get invoice details to update status
                $res = $conn->query("SELECT amount FROM tenant_invoices WHERE id = $tenant_invoice_id");
                $row = $res->fetch_assoc();
                $invoice_amount = (float) $row['amount'];

                // Update invoice status based on total payment amount
                $new_status = '';
                $total_payment = $primary_amount + $secondary_amount;

                if ($total_payment >= $invoice_amount) {
                    $new_status = 'paid';
                } else if ($total_payment > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = 'unpaid';
                }

                // Update the invoice status and payment details
                $update_stmt = $conn->prepare("UPDATE tenant_invoices SET 
                                              status = ?, 
                                              payment_date = ?, 
                                              payment_amount = ? 
                                              WHERE id = ?");
                $update_stmt->bind_param("ssdi", $new_status, $payment_date, $total_payment, $tenant_invoice_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Create two payment_links records with different fund types
                // First payment link with primary fund type
                $stmt = $conn->prepare("INSERT INTO payment_links (payment_id, expense_id, apartment_fee_id, tenant_invoice_id, building_bills_id, amount, fund_type, notes) VALUES (?, NULL, NULL, ?, NULL, ?, ?, ?)");
                $primary_notes = $notes . " (Primary: $primary_fund_type)";
                $stmt->bind_param("iidss", $payment_id, $tenant_invoice_id, $primary_amount, $primary_fund_type, $primary_notes);
                $stmt->execute();
                $stmt->close();

                // Second payment link with secondary fund type
                $stmt = $conn->prepare("INSERT INTO payment_links (payment_id, expense_id, apartment_fee_id, tenant_invoice_id, building_bills_id, amount, fund_type, notes) VALUES (?, NULL, NULL, ?, NULL, ?, ?, ?)");
                $secondary_notes = $notes . " (Secondary: $secondary_fund_type)";
                $stmt->bind_param("iidss", $payment_id, $tenant_invoice_id, $secondary_amount, $secondary_fund_type, $secondary_notes);
                $stmt->execute();
                $stmt->close();
            } else {
                // Regular payment processing (non-split) with credit handling
                $link_fund_type = isset($_POST['link_fund_type']) ? $_POST['link_fund_type'] : $payment_fund_type;

                // Determine per-link amount
                $linked_amount = 0;

                if ($building_bills_id) {
                    $res = $conn->query("SELECT amount FROM building_bills WHERE id = $building_bills_id");
                    $row = $res->fetch_assoc();
                    $linked_amount = (float) $row['amount'];
                } else if ($tenant_invoice_id) {
                    // Get invoice details to update status
                    $res = $conn->query("SELECT amount FROM tenant_invoices WHERE id = $tenant_invoice_id");
                    $row = $res->fetch_assoc();
                    $invoice_amount = (float) $row['amount'];
                    $linked_amount = $amount;

                    // Update invoice status based on payment amount
                    $new_status = '';
                    if ($amount >= $invoice_amount) {
                        $new_status = 'paid';
                    } else if ($amount > 0) {
                        $new_status = 'partial';
                    } else {
                        $new_status = 'unpaid';
                    }

                    // Update the invoice status and payment details
                    $update_stmt = $conn->prepare("UPDATE tenant_invoices SET 
                                                  status = ?, 
                                                  payment_date = ?, 
                                                  payment_amount = ? 
                                                  WHERE id = ?");
                    $update_stmt->bind_param("ssdi", $new_status, $payment_date, $amount, $tenant_invoice_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else if ($apartment_fee_id) {
                    // Enhanced fee handling with credit system
                    $fee_query = $conn->prepare("
                        SELECT 
                            af.total, 
                            af.apartment_id,
                            COALESCE(SUM(pl.amount), 0) as paid_amount
                        FROM 
                            apartment_fees af
                        LEFT JOIN 
                            payment_links pl ON af.id = pl.apartment_fee_id
                        WHERE 
                            af.id = ?
                        GROUP BY 
                            af.id, af.total, af.apartment_id
                    ");
                    $fee_query->bind_param('i', $apartment_fee_id);
                    $fee_query->execute();
                    $fee_result = $fee_query->get_result();

                    if ($fee_result->num_rows > 0) {
                        $fee_row = $fee_result->fetch_assoc();
                        $total_fee = (float) $fee_row['total'];
                        $paid_amount = (float) $fee_row['paid_amount'];
                        $unpaid_amount = $total_fee - $paid_amount;
                        $fee_apartment_id = $fee_row['apartment_id'];

                        // Check for available credits first
                        $applied_credits = applyAvailableCredits($conn, $fee_apartment_id, $payment_fund_type, $payment_id, $unpaid_amount);

                        // Calculate remaining amount to pay after applying credits
                        $remaining_to_pay = $unpaid_amount - $applied_credits;

                        // Determine actual payment amount for this fee
                        if (count($link_ids) > 1) {
                            // If multiple fees, distribute payment proportionally
                            $linked_amount = min($remaining_to_pay, $amount / count($link_ids));
                        } else {
                            // Single fee - use full payment amount
                            $linked_amount = min($remaining_to_pay, $amount);
                        }

                        // Check if there's an overpayment
                        if ($amount > $remaining_to_pay && count($link_ids) == 1) {
                            $overpaid_amount = $amount - $remaining_to_pay;

                            // Create credit for overpayment
                            createPaymentCredit(
                                $conn,
                                $fee_apartment_id,
                                $payment_id,
                                $overpaid_amount,
                                $payment_fund_type,
                                "Overpayment credit from payment #{$payment_id}"
                            );

                            // Use only the required amount for the payment link
                            $linked_amount = $remaining_to_pay;
                        }
                    }
                    $fee_query->close();
                } else {
                    $linked_amount = $amount;
                }

                // Create payment link only if there's an actual payment amount
                if ($linked_amount > 0) {
                    $stmt = $conn->prepare("INSERT INTO payment_links (payment_id, expense_id, apartment_fee_id, tenant_invoice_id, building_bills_id, amount, fund_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                    // Set null values for fields that should be null
                    $expense_id = ($expense_id === null) ? null : $expense_id;
                    $apartment_fee_id = ($apartment_fee_id === null) ? null : $apartment_fee_id;
                    $tenant_invoice_id = ($tenant_invoice_id === null) ? null : $tenant_invoice_id;
                    $building_bills_id = ($building_bills_id === null) ? null : $building_bills_id;

                    $stmt->bind_param("iiiiidss", $payment_id, $expense_id, $apartment_fee_id, $tenant_invoice_id, $building_bills_id, $linked_amount, $link_fund_type, $notes);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $conn->commit();
        $success_message = "Payment added successfully.";

        // Add credit information to success message
        if ($payment_id) {
            $credit_info_query = "
            SELECT 
                (SELECT SUM(credit_amount) FROM payment_credits WHERE payment_id = ?) as credits_created,
                (SELECT SUM(used_amount) FROM credit_usage WHERE used_payment_id = ?) as credits_used
            ";
            $stmt = $conn->prepare($credit_info_query);
            $stmt->bind_param('ii', $payment_id, $payment_id);
            $stmt->execute();
            $credit_info = $stmt->get_result()->fetch_assoc();

            if ($credit_info['credits_created'] > 0) {
                $success_message .= " Credit of " . number_format($credit_info['credits_created'], 2) . " RON created for future use.";
            }
            if ($credit_info['credits_used'] > 0) {
                $success_message .= " " . number_format($credit_info['credits_used'], 2) . " RON in credits applied.";
            }
        }

        // Store success message in session and redirect to refresh the page
        $_SESSION['success_message'] = $success_message;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
        error_log("Payment error: " . $e->getMessage());
    }
}

// Check for success message in session
if (empty($success_message) && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the session message after using it
}

require 'header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Add Payment</h1>

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

            <!-- Add credit information display in the form -->
            <div class="row mb-3" id="credit_info_container" style="display:none;">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-credit-card text-success"></i>
                                Available Credits
                            </h6>
                            <div id="credit_info_content">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <div class="row mb-3">
                            <label for="link_type" class="col-sm-3 col-form-label">Link To:</label>
                            <div class="col-sm-9">
                                <select id="link_type" name="link_type" class="form-select" onchange="showLinkOptions()"
                                    required>
                                    <option value="">--Select--</option>
                                    <option value="expense">Expense</option>
                                    <option value="fee">Apartment Fee</option>
                                    <option value="tenant_invoice">Tenant Invoice</option>
                                    <option value="bill">Building Bill</option>
                                </select>
                            </div>
                        </div>

                        <!-- Show All Fees Checkbox - Initially Hidden -->
                        <div class="row mb-3" id="show_all_fees_field" style="display:none;">
                            <label class="col-sm-3 col-form-label">Show All Fees:</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="show_all_fees"
                                        name="show_all_fees" onchange="toggleFeeDisplay()">
                                    <label class="form-check-label" for="show_all_fees">Show unpaid fees from all
                                        apartments</label>
                                </div>
                            </div>
                        </div>

                        <!-- Split Payment Option - Initially Hidden -->
                        <div class="row mb-3" id="split_payment_field" style="display:none;">
                            <label class="col-sm-3 col-form-label">Split Payment:</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="split_payment"
                                        name="split_payment" value="yes" onchange="toggleSplitPayment()">
                                    <label class="form-check-label" for="split_payment">Split payment into two fund
                                        types</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3" id="apartment_field">
                            <label for="apartment_id" class="col-sm-3 col-form-label">Apartment:</label>
                            <div class="col-sm-9">
                                <select name="apartment_id" id="apartment_id" class="form-select"
                                    onchange="updateApartmentFees()">
                                    <option value="">--Select--</option>
                                    <?php
                                    $apartments->data_seek(0);
                                    while ($row = $apartments->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>Scara {$row['scara']} - Apartment {$row['number']} - {$row['owner_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="account_id" class="col-sm-3 col-form-label">Account:</label>
                            <div class="col-sm-9">
                                <select name="account_id" id="account_id" class="form-select" required>
                                    <option value="">--Select--</option>
                                    <?php
                                    $accounts->data_seek(0);
                                    while ($row = $accounts->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Payment Fund Type (for payments table) -->
                        <div class="row mb-3">
                            <label for="payment_fund_type" class="col-sm-3 col-form-label">Payment Fund Type:</label>
                            <div class="col-sm-9">
                                <select name="payment_fund_type" id="payment_fund_type" class="form-select" required>
                                    <option value="">--Select--</option>
                                    <?php foreach ($fund_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">This fund type is used for the main payment
                                    record.</small>
                            </div>
                        </div>

                        <!-- Link Fund Type (for non-split payment links) -->
                        <div class="row mb-3" id="link_fund_type_container">
                            <label for="link_fund_type" class="col-sm-3 col-form-label">Link Fund Type:</label>
                            <div class="col-sm-9">
                                <select name="link_fund_type" id="link_fund_type" class="form-select">
                                    <option value="">--Same as Payment Fund Type--</option>
                                    <?php foreach ($fund_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Leave empty to use the same fund type as the
                                    payment.</small>
                            </div>
                        </div>

                        <!-- Split Payment Fund Types - Initially Hidden -->
                        <div id="split_fund_types_container" style="display:none;">
                            <div class="row mb-3">
                                <label for="primary_fund_type" class="col-sm-3 col-form-label">Primary Fund
                                    Type:</label>
                                <div class="col-sm-9">
                                    <select name="primary_fund_type" id="primary_fund_type" class="form-select">
                                        <option value="">--Select--</option>
                                        <?php foreach ($fund_types as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="secondary_fund_type" class="col-sm-3 col-form-label">Secondary Fund
                                    Type:</label>
                                <div class="col-sm-9">
                                    <select name="secondary_fund_type" id="secondary_fund_type" class="form-select">
                                        <option value="">--Select--</option>
                                        <?php foreach ($fund_types as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="payment_date" class="col-sm-3 col-form-label">Payment Date:</label>
                            <div class="col-sm-9">
                                <input type="date" id="payment_date" name="payment_date" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="notes" class="col-sm-3 col-form-label">Notes:</label>
                            <div class="col-sm-9">
                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>

                        <div id="expense_select" class="link-select row mb-3" style="display:none;">
                            <label for="expense_id" class="col-sm-3 col-form-label">Expense:</label>
                            <div class="col-sm-9">
                                <select id="expense_id" name="link_ids[]" class="form-select" multiple>
                                    <?php
                                    $expenses->data_seek(0);
                                    while ($row = $expenses->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>#{$row['id']} - {$row['description']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div id="fee_select" class="link-select row mb-3" style="display:none;">
                            <label for="fee_id" class="col-sm-3 col-form-label">Apartment Fee:</label>
                            <div class="col-sm-9">
                                <select id="fee_id" name="link_ids[]" class="form-select" multiple
                                    onchange="updateFeeAmount()">
                                    <!-- Options will be populated by JavaScript -->
                                </select>
                            </div>
                        </div>

                        <div id="tenant_invoice_select" class="link-select row mb-3" style="display:none;">
                            <label for="tenant_invoice_id" class="col-sm-3 col-form-label">Tenant Invoice:</label>
                            <div class="col-sm-9">
                                <select id="tenant_invoice_id" name="link_ids[]" class="form-select"
                                    onchange="updateInvoiceAmount()">
                                    <option value="">--Select--</option>
                                    <?php
                                    $tenant_invoices->data_seek(0);
                                    while ($row = $tenant_invoices->fetch_assoc()) {
                                        $status_badge = '';
                                        if ($row['status'] == 'partial') {
                                            $status_badge = '<span class="badge bg-warning">Partial</span>';
                                        } else if ($row['status'] == 'unpaid') {
                                            $status_badge = '<span class="badge bg-danger">Unpaid</span>';
                                        }
                                        $rent_amount = $row['rent_amount'] > 0 ? $row['rent_amount'] : 0;
                                        $utilities_amount = $row['utilities_amount'] > 0 ? $row['utilities_amount'] : 0;
                                        echo "<option value='{$row['id']}' data-amount='{$row['amount']}' data-rent='{$rent_amount}' data-utilities='{$utilities_amount}'>{$row['tenant_name']} - {$row['invoice_number']} - {$row['amount']} {$row['currency']} {$status_badge}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div id="bill_select" class="link-select row mb-3" style="display:none;">
                            <label for="bill_id" class="col-sm-3 col-form-label">Building Bill:</label>
                            <div class="col-sm-9">
                                <select id="bill_id" name="link_ids[]" class="form-select" multiple
                                    onchange="updateBillAmount()">
                                    <?php
                                    $bills->data_seek(0);
                                    while ($row = $bills->fetch_assoc()) {
                                        echo "<option value='{$row['id']}' data-amount='{$row['amount']}'>{$row['bill_date']} - {$row['bill_type']} - {$row['bill_no']} - {$row['description']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Regular Amount Field -->
                        <div class="row mb-3" id="amount_container">
                            <label for="amount" class="col-sm-3 col-form-label">Amount:</label>
                            <div class="col-sm-9">
                                <input type="number" id="amount" name="amount" step="0.01" class="form-control"
                                    required>
                            </div>
                        </div>

                        <!-- Split Amount Fields - Initially Hidden -->
                        <div id="split_amount_container" style="display:none;">
                            <div class="row mb-3">
                                <label for="primary_amount" class="col-sm-3 col-form-label">Primary Amount:</label>
                                <div class="col-sm-9">
                                    <input type="number" id="primary_amount" name="primary_amount" step="0.01"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="secondary_amount" class="col-sm-3 col-form-label">Secondary Amount:</label>
                                <div class="col-sm-9">
                                    <input type="number" id="secondary_amount" name="secondary_amount" step="0.01"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Total:</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input type="text" id="total_split_amount" class="form-control" readonly>
                                        <span class="input-group-text">RON</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Submit Payment</button>
                                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Store all apartment fees data - Updated to use 'total' instead of 'total_amount'
    const allApartmentFees = [
        <?php
        $apartment_fees->data_seek(0);
        while ($row = $apartment_fees->fetch_assoc()) {
            $unpaidAmount = $row['total'] - $row['paid_amount']; // Changed from total_amount to total
            $status = '';
            if ($row['paid_amount'] >= $row['total']) { // Changed from total_amount to total
                $status = 'paid';
            } else if ($row['paid_amount'] > 0) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            echo "{
                id: {$row['id']}, 
                apartmentId: {$row['apartment_id']}, 
                monthKey: '{$row['month_key']}',
                apartmentNumber: '{$row['number']}',
                scara: '{$row['scara']}',
                ownerName: '{$row['owner_name']}',
                totalAmount: {$row['total']}, 
                paidAmount: {$row['paid_amount']},
                unpaidAmount: {$unpaidAmount},
                status: '{$status}'
            },";
        }
        ?>
    ];

    // Add function to check available credits when apartment is selected
    function checkAvailableCredits(apartmentId) {
        if (!apartmentId) {
            document.getElementById('credit_info_container').style.display = 'none';
            return;
        }
        
        fetch(`get_apartment_credits.php?apartment_id=${apartmentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.credits && data.credits.length > 0) {
                    let creditHtml = '<div class="row">';
                    data.credits.forEach(credit => {
                        creditHtml += `
                            <div class="col-md-4 mb-2">
                                <div class="border p-2 rounded">
                                    <strong>${credit.fund_type}</strong><br>
                                    <span class="text-success">${credit.remaining_amount} RON</span>
                                </div>
                            </div>
                        `;
                    });
                    creditHtml += '</div>';
                    document.getElementById('credit_info_content').innerHTML = creditHtml;
                    document.getElementById('credit_info_container').style.display = 'block';
                } else {
                    document.getElementById('credit_info_container').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching credits:', error);
            });
    }

    function showLinkOptions() {
        const selected = document.getElementById("link_type").value;
        document.querySelectorAll(".link-select").forEach(e => e.style.display = 'none');

        // Hide the show all fees checkbox by default
        document.getElementById("show_all_fees_field").style.display = 'none';

        // Hide the split payment option by default
        document.getElementById("split_payment_field").style.display = 'none';

        // Reset split payment checkbox
        document.getElementById("split_payment").checked = false;
        toggleSplitPayment();

        if (selected) {
            document.getElementById(selected + "_select").style.display = 'flex';
        }

        const apartmentField = document.getElementById("apartment_field");
        if (selected === "bill" || selected === "tenant_invoice") {
            apartmentField.style.display = 'none';
        } else {
            apartmentField.style.display = 'flex';
        }

        // Show the checkbox only when Apartment Fee is selected
        if (selected === "fee") {
            document.getElementById("show_all_fees_field").style.display = 'flex';
        }

        // Show the split payment option only when Tenant Invoice is selected
        if (selected === "tenant_invoice") {
            document.getElementById("split_payment_field").style.display = 'flex';
        }

        // Reset amount field when changing link type
        document.getElementById("amount").value = '';
        if (document.getElementById("primary_amount")) {
            document.getElementById("primary_amount").value = '';
        }
        if (document.getElementById("secondary_amount")) {
            document.getElementById("secondary_amount").value = '';
        }
        if (document.getElementById("total_split_amount")) {
            document.getElementById("total_split_amount").value = '';
        }

        // Update apartment fees if fee is selected
        if (selected === "fee") {
            toggleFeeDisplay();
        }
    }

    function toggleSplitPayment() {
        const splitPayment = document.getElementById("split_payment").checked;
        const amountContainer = document.getElementById("amount_container");
        const splitAmountContainer = document.getElementById("split_amount_container");
        const splitFundTypesContainer = document.getElementById("split_fund_types_container");
        const linkFundTypeContainer = document.getElementById("link_fund_type_container");

        if (splitPayment) {
            // Show split payment fields
            amountContainer.style.display = 'none';
            splitAmountContainer.style.display = 'block';
            splitFundTypesContainer.style.display = 'block';
            linkFundTypeContainer.style.display = 'none';

            // Update the split amounts based on the selected invoice
            updateSplitAmounts();
        } else {
            // Show regular amount field
            amountContainer.style.display = 'flex';
            splitAmountContainer.style.display = 'none';
            splitFundTypesContainer.style.display = 'none';
            linkFundTypeContainer.style.display = 'flex';
        }
    }

    function updateSplitAmounts() {
        const selectedOption = document.querySelector("#tenant_invoice_id option:checked");
        if (selectedOption) {
            const invoiceAmount = parseFloat(selectedOption.getAttribute('data-amount'));
            const rentAmount = parseFloat(selectedOption.getAttribute('data-rent'));
            const utilitiesAmount = parseFloat(selectedOption.getAttribute('data-utilities'));

            // If rent and utilities are specified, use them for the split
            if (rentAmount > 0 || utilitiesAmount > 0) {
                document.getElementById("primary_amount").value = rentAmount.toFixed(2);
                document.getElementById("secondary_amount").value = utilitiesAmount.toFixed(2);

                // Set default fund types
                if (document.getElementById("primary_fund_type").value === "") {
                    const fundTypeSelect = document.getElementById("primary_fund_type");
                    for (let i = 0; i < fundTypeSelect.options.length; i++) {
                        if (fundTypeSelect.options[i].value === "special_fund") {
                            fundTypeSelect.selectedIndex = i;
                            break;
                        }
                    }
                }

                if (document.getElementById("secondary_fund_type").value === "") {
                    const fundTypeSelect = document.getElementById("secondary_fund_type");
                    for (let i = 0; i < fundTypeSelect.options.length; i++) {
                        if (fundTypeSelect.options[i].value === "utilities") {
                            fundTypeSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            } else {
                // Otherwise, split the amount 50/50
                const halfAmount = invoiceAmount / 2;
                document.getElementById("primary_amount").value = halfAmount.toFixed(2);
                document.getElementById("secondary_amount").value = halfAmount.toFixed(2);
            }

            // Update total
            updateTotalSplitAmount();
        }
    }

    function updateTotalSplitAmount() {
        const primaryAmount = parseFloat(document.getElementById("primary_amount").value) || 0;
        const secondaryAmount = parseFloat(document.getElementById("secondary_amount").value) || 0;
        const totalAmount = primaryAmount + secondaryAmount;

        document.getElementById("total_split_amount").value = totalAmount.toFixed(2);
    }

    function toggleFeeDisplay() {
        const showAllFees = document.getElementById("show_all_fees").checked;
        const apartmentField = document.getElementById("apartment_field");

        if (showAllFees) {
            // Hide apartment selection when showing all fees
            apartmentField.style.display = 'none';
            // Show all unpaid/partially paid fees
            showAllUnpaidFees();
        } else {
            // Show apartment selection when not showing all fees
            apartmentField.style.display = 'flex';
            // Show fees for the selected apartment
            updateApartmentFees();
        }
    }

    function showAllUnpaidFees() {
        const feeSelect = document.getElementById("fee_id");

        // Clear existing options
        feeSelect.innerHTML = '';

        // Filter to only show unpaid or partially paid fees
        const unpaidFees = allApartmentFees.filter(fee => fee.unpaidAmount > 0);

        if (unpaidFees.length === 0) {
            const option = document.createElement('option');
            option.text = 'No unpaid fees found';
            option.disabled = true;
            feeSelect.add(option);
            return;
        }

        // Add all unpaid fees to the dropdown
        unpaidFees.forEach(fee => {
            const option = document.createElement('option');
            option.value = fee.id;

            // Format the month key for display (YYYY-MM to Month YYYY)
            const year = fee.monthKey.substring(0, 4);
            const month = fee.monthKey.substring(5, 7);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const monthName = monthNames[parseInt(month) - 1];

            // Include apartment info in the option text
            option.text = `Scara ${fee.scara} - Apt ${fee.apartmentNumber} - ${monthName} ${year}`;

            // Add data attributes for amount calculation
            option.setAttribute('data-amount', fee.totalAmount);
            option.setAttribute('data-paid', fee.paidAmount);

            // Add status badge
            let statusBadge = '';
            if (fee.status === 'partial') {
                statusBadge = '<span class="badge bg-warning">Partial</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Unpaid</span>';
            }

            option.innerHTML = `${option.text} - ${fee.totalAmount.toFixed(2)} RON (Unpaid: ${fee.unpaidAmount.toFixed(2)}) ${statusBadge}`;

            feeSelect.add(option);
        });

        // Enable multiple selection
        feeSelect.multiple = true;
    }

    function updateApartmentFees() {
        const apartmentId = document.getElementById("apartment_id").value;
        
        // Check for available credits
        checkAvailableCredits(apartmentId);
        
        const feeSelect = document.getElementById("fee_id");

        // Clear existing options
        feeSelect.innerHTML = '';

        if (!apartmentId) {
            // If no apartment selected, show message
            const option = document.createElement('option');
            option.text = 'Please select an apartment first';
            option.disabled = true;
            feeSelect.add(option);
            return;
        }

        // Filter fees for the selected apartment
        const filteredFees = allApartmentFees.filter(fee => fee.apartmentId == apartmentId && fee.unpaidAmount > 0);

        if (filteredFees.length === 0) {
            const option = document.createElement('option');
            option.text = 'No unpaid fees found for this apartment';
            option.disabled = true;
            feeSelect.add(option);
            return;
        }

        // Add filtered fees to the dropdown
        filteredFees.forEach(fee => {
            const option = document.createElement('option');
            option.value = fee.id;

            // Format the month key for display (YYYY-MM to Month YYYY)
            const year = fee.monthKey.substring(0, 4);
            const month = fee.monthKey.substring(5, 7);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const monthName = monthNames[parseInt(month) - 1];

            option.text = `#${fee.id} - ${monthName} ${year}`;

            // Add data attributes for amount calculation
            option.setAttribute('data-amount', fee.totalAmount);
            option.setAttribute('data-paid', fee.paidAmount);

            // Add status badge
            let statusBadge = '';
            if (fee.status === 'partial') {
                statusBadge = '<span class="badge bg-warning">Partial</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Unpaid</span>';
            }

            option.innerHTML = `${option.text} - ${fee.totalAmount.toFixed(2)} RON (Unpaid: ${fee.unpaidAmount.toFixed(2)}) ${statusBadge}`;

            feeSelect.add(option);
        });

        // Enable multiple selection
        feeSelect.multiple = true;
    }

    function updateFeeAmount() {
        let totalAmount = 0;
        let selectedFees = document.querySelectorAll("#fee_id option:checked");

        selectedFees.forEach(option => {
            const totalFeeAmount = parseFloat(option.getAttribute('data-amount')) || 0;
            const paidAmount = parseFloat(option.getAttribute('data-paid')) || 0;
            const unpaidAmount = totalFeeAmount - paidAmount;

            if (unpaidAmount > 0) {
                totalAmount += unpaidAmount;
            }
        });

        document.getElementById("amount").value = totalAmount.toFixed(2);
    }

    function updateBillAmount() {
        let totalAmount = 0;
        document.querySelectorAll("#bill_id option:checked").forEach(option => {
            totalAmount += parseFloat(option.getAttribute('data-amount'));
        });
        document.getElementById("amount").value = totalAmount.toFixed(2);
    }

    function updateInvoiceAmount() {
        const selectedOption = document.querySelector("#tenant_invoice_id option:checked");
        if (selectedOption) {
            const invoiceAmount = parseFloat(selectedOption.getAttribute('data-amount'));

            // Update the regular amount field
            document.getElementById("amount").value = invoiceAmount.toFixed(2);

            // If split payment is enabled, update the split amounts
            if (document.getElementById("split_payment").checked) {
                updateSplitAmounts();
            }
        }
    }

    // Add event listeners for split amount fields
    document.addEventListener('DOMContentLoaded', function () {
        const primaryAmountInput = document.getElementById("primary_amount");
        const secondaryAmountInput = document.getElementById("secondary_amount");

        if (primaryAmountInput && secondaryAmountInput) {
            primaryAmountInput.addEventListener('input', updateTotalSplitAmount);
            secondaryAmountInput.addEventListener('input', updateTotalSplitAmount);
        }

        // Initialize the form
        toggleSplitPayment();
        
        // Add loading indicator when form is submitted
        document.getElementById('paymentForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            submitBtn.disabled = true;
        });
    });
</script>

<script>
    $(document).ready(function () {
        $('#apartment_id').select2({
            placeholder: "--Select an Apartment--",
            allowClear: true
        });
    });
</script>

<?php require 'footer.php'; ?>