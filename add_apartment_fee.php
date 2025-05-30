<?php
require 'auth.php';
require 'db.php';

// Get all apartments for dropdown
$apartments_query = "SELECT id, number, owner_name FROM apartments ORDER BY number";
$apartments_result = $conn->query($apartments_query);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        
        // Get form data
        $apartment_id = (int)$_POST['apartment_id'];
        $month_key = $_POST['month_key'];
        $issued_date = $_POST['issued_date'];
        $payment_deadline = $_POST['payment_deadline'];
        $utilities = (float)$_POST['utilities'];
        $fond_rulment = (float)$_POST['fond_rulment'];
        $fond_special = (float)$_POST['fond_special'];
        
        // Check if there are unpaid fees from previous months
        $previous_unpaid_query = "
            SELECT 
                SUM(af.total_amount) - COALESCE(SUM(
                    (SELECT COALESCE(SUM(pl.amount), 0) 
                     FROM payment_links pl 
                     JOIN payments p ON pl.payment_id = p.id 
                     WHERE pl.apartment_fee_id = af.id)
                ), 0) as unpaid_amount
            FROM apartment_fees af
            WHERE af.apartment_id = ? 
            AND af.month_key < ?
            HAVING unpaid_amount > 0
        ";
        
        $stmt = $conn->prepare($previous_unpaid_query);
        $stmt->bind_param("is", $apartment_id, $month_key);
        $stmt->execute();
        $unpaid_result = $stmt->get_result();
        $previous_unpaid = 0;
        
        if ($row = $unpaid_result->fetch_assoc()) {
            $previous_unpaid = (float)$row['unpaid_amount'];
        }
        
        // Calculate penalties (example: 0.1% per day after deadline)
        $penalties = 0;
        if ($previous_unpaid > 0) {
            // Get the most recent unpaid fee
            $last_fee_query = "
                SELECT af.*, 
                    (SELECT COALESCE(SUM(pl.amount), 0) 
                     FROM payment_links pl 
                     JOIN payments p ON pl.payment_id = p.id 
                     WHERE pl.apartment_fee_id = af.id) as paid_amount
                FROM apartment_fees af
                WHERE af.apartment_id = ? 
                AND af.month_key < ?
                HAVING af.total_amount > paid_amount
                ORDER BY af.month_key DESC
                LIMIT 1
            ";
            
            $stmt = $conn->prepare($last_fee_query);
            $stmt->bind_param("is", $apartment_id, $month_key);
            $stmt->execute();
            $last_fee_result = $stmt->get_result();
            
            if ($last_fee = $last_fee_result->fetch_assoc()) {
                $deadline = new DateTime($last_fee['payment_deadline']);
                $now = new DateTime();
                $days_late = $deadline->diff($now)->days;
                
                if ($days_late > 0) {
                    // 0.1% per day
                    $penalties = $previous_unpaid * 0.001 * $days_late;
                }
            }
        }
        
        // Calculate restante (unpaid components)
        $restante_query = "
            SELECT 
                COALESCE(SUM(af.utilities) - SUM(
                    (SELECT COALESCE(SUM(CASE WHEN p.fund_type = 'utilities' THEN pl.amount ELSE 0 END), 0) 
                     FROM payment_links pl 
                     JOIN payments p ON pl.payment_id = p.id 
                     WHERE pl.apartment_fee_id = af.id)
                ), 0) as restante_cote,
                COALESCE(SUM(af.fond_rulment) - SUM(
                    (SELECT COALESCE(SUM(CASE WHEN p.fund_type = 'ball_fund' THEN pl.amount ELSE 0 END), 0) 
                     FROM payment_links pl 
                     JOIN payments p ON pl.payment_id = p.id 
                     WHERE pl.apartment_fee_id = af.id)
                ), 0) as restante_fond_rulment,
                COALESCE(SUM(af.penalties) - SUM(
                    (SELECT COALESCE(SUM(CASE WHEN p.fund_type = 'penalties' THEN pl.amount ELSE 0 END), 0) 
                     FROM payment_links pl 
                     JOIN payments p ON pl.payment_id = p.id 
                     WHERE pl.apartment_fee_id = af.id)
                ), 0) as restante_fond_penalizari
            FROM apartment_fees af
            WHERE af.apartment_id = ? 
            AND af.month_key < ?
        ";
        
        $stmt = $conn->prepare($restante_query);
        $stmt->bind_param("is", $apartment_id, $month_key);
        $stmt->execute();
        $restante_result = $stmt->get_result();
        $restante = $restante_result->fetch_assoc();
        
        $restante_cote = max(0, (float)$restante['restante_cote']);
        $restante_fond_rulment = max(0, (float)$restante['restante_fond_rulment']);
        $restante_fond_penalizari = max(0, (float)$restante['restante_fond_penalizari']);
        
        // Calculate total
        $total = $utilities + $fond_rulment + $fond_special;
        
        // Insert new fee
        $insert_query = "
            INSERT INTO apartment_fees (
                apartment_id, month_key, issued_date, payment_deadline, 
                fond_rulment, total, restante_cote, restante_fond_rulment, 
                restante_fond_penalizari, utilities, previous_unpaid, 
                penalties, fond_special
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param(
            "isssddddddddd", 
            $apartment_id, $month_key, $issued_date, $payment_deadline,
            $fond_rulment, $total, $restante_cote, $restante_fond_rulment,
            $restante_fond_penalizari, $utilities, $previous_unpaid,
            $penalties, $fond_special
        );
        
        if ($stmt->execute()) {
            $conn->commit();
            $success_message = "Apartment fee added successfully!";
        } else {
            throw new Exception("Error adding apartment fee: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container">
    <h1 class="mb-4">Add New Apartment Fee</h1>
    
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
    
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <label for="apartment_id" class="col-sm-3 col-form-label">Apartment</label>
                    <div class="col-sm-9">
                        <select name="apartment_id" id="apartment_id" class="form-select" required onchange="checkPreviousUnpaid()">
                            <option value="">-- Select Apartment --</option>
                            <?php while ($apartment = $apartments_result->fetch_assoc()): ?>
                                <option value="<?php echo $apartment['id']; ?>">
                                    <?php echo htmlspecialchars($apartment['number'] . ' - ' . $apartment['owner_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="month_key" class="col-sm-3 col-form-label">Month</label>
                    <div class="col-sm-9">
                        <input type="month" name="month_key" id="month_key" class="form-control" required value="<?php echo date('Y-m'); ?>" onchange="checkPreviousUnpaid()">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="issued_date" class="col-sm-3 col-form-label">Issued Date</label>
                    <div class="col-sm-9">
                        <input type="date" name="issued_date" id="issued_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="payment_deadline" class="col-sm-3 col-form-label">Payment Deadline</label>
                    <div class="col-sm-9">
                        <input type="date" name="payment_deadline" id="payment_deadline" class="form-control" required value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="utilities" class="col-sm-3 col-form-label">Utilities</label>
                    <div class="col-sm-9">
                        <input type="number" name="utilities" id="utilities" class="form-control" step="0.01" required value="0.00" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="fond_rulment" class="col-sm-3 col-form-label">Fond Rulment</label>
                    <div class="col-sm-9">
                        <input type="number" name="fond_rulment" id="fond_rulment" class="form-control" step="0.01" required value="0.00" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="fond_special" class="col-sm-3 col-form-label">Fond Special</label>
                    <div class="col-sm-9">
                        <input type="number" name="fond_special" id="fond_special" class="form-control" step="0.01" required value="0.00" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div id="unpaid_section" style="display: none;">
                    <div class="alert alert-warning">
                        <h5>Previous Unpaid Amounts</h5>
                        <div id="unpaid_details"></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="total" class="col-sm-3 col-form-label">Total Current Fee</label>
                    <div class="col-sm-9">
                        <input type="number" id="total" class="form-control" step="0.01" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="grand_total" class="col-sm-3 col-form-label">Grand Total (Including Unpaid)</label>
                    <div class="col-sm-9">
                        <input type="number" id="grand_total" class="form-control" step="0.01" readonly>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-sm-9 offset-sm-3">
                        <button type="submit" class="btn btn-primary">Save Fee</button>
                        <a href="apartment_fees.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calculateTotal() {
    const utilities = parseFloat(document.getElementById('utilities').value) || 0;
    const fondRulment = parseFloat(document.getElementById('fond_rulment').value) || 0;
    const fondSpecial = parseFloat(document.getElementById('fond_special').value) || 0;
    
    const total = utilities + fondRulment + fondSpecial;
    document.getElementById('total').value = total.toFixed(2);
    
    // Update grand total
    updateGrandTotal();
}

function updateGrandTotal() {
    const total = parseFloat(document.getElementById('total').value) || 0;
    const unpaidSection = document.getElementById('unpaid_section');
    
    if (unpaidSection.style.display !== 'none') {
        const unpaidAmount = parseFloat(unpaidSection.getAttribute('data-unpaid')) || 0;
        const penalties = parseFloat(unpaidSection.getAttribute('data-penalties')) || 0;
        
        const grandTotal = total + unpaidAmount + penalties;
        document.getElementById('grand_total').value = grandTotal.toFixed(2);
    } else {
        document.getElementById('grand_total').value = total.toFixed(2);
    }
}

function checkPreviousUnpaid() {
    const apartmentId = document.getElementById('apartment_id').value;
    const monthKey = document.getElementById('month_key').value;
    
    if (!apartmentId || !monthKey) return;
    
    fetch(`get_unpaid_fees.php?apartment_id=${apartmentId}&month_key=${monthKey}`)
        .then(response => response.json())
        .then(data => {
            const unpaidSection = document.getElementById('unpaid_section');
            const unpaidDetails = document.getElementById('unpaid_details');
            
            if (data.has_unpaid) {
                unpaidSection.style.display = 'block';
                unpaidSection.setAttribute('data-unpaid', data.unpaid_amount);
                unpaidSection.setAttribute('data-penalties', data.penalties);
                
                let html = `
                    <p><strong>Previous Unpaid:</strong> ${data.unpaid_amount.toFixed(2)} RON</p>
                    <p><strong>Penalties:</strong> ${data.penalties.toFixed(2)} RON</p>
                    <p><strong>Total Unpaid:</strong> ${(data.unpaid_amount + data.penalties).toFixed(2)} RON</p>
                `;
                
                if (data.unpaid_fees.length > 0) {
                    html += '<p><strong>Unpaid Fees:</strong></p><ul>';
                    data.unpaid_fees.forEach(fee => {
                        html += `<li>${fee.month_key}: ${fee.unpaid.toFixed(2)} RON</li>`;
                    });
                    html += '</ul>';
                }
                
                unpaidDetails.innerHTML = html;
            } else {
                unpaidSection.style.display = 'none';
                unpaidDetails.innerHTML = '';
            }
            
            updateGrandTotal();
        })
        .catch(error => console.error('Error checking unpaid fees:', error));
}

// Initialize calculations
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php require 'footer.php'; ?>