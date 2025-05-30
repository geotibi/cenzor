<?php
require 'auth.php';
require 'db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $name = $conn->real_escape_string($_POST['name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Insert new tenant
    $sql = "INSERT INTO tenants (name, contact_person, phone, email, address, notes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssss", $name, $contact_person, $phone, $email, $address, $notes);
        
        if ($stmt->execute()) {
            // Redirect to tenants list
            header('Location: tenants.php');
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

require 'header.php'; // Include the header
?>

<div class="container">
    <h1 class="mb-4">Add New Tenant</h1>
    
    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Tenant Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        
        <div class="mb-3">
            <label for="contact_person" class="form-label">Contact Person</label>
            <input type="text" class="form-control" id="contact_person" name="contact_person">
        </div>
        
        <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone">
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
        </div>
        
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
        </div>
        
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Tenant</button>
            <a href="tenants.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require 'footer.php'; // Include the footer ?>