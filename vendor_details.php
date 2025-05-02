<?php
session_start();

// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils.php';

// Verify if file is uploaded
if (!isset($_SESSION['csv_file'])) {
    header('Location: upload.php');
    exit();
}

try {
    // Connect to database using the connection function from database.php
    $pdo = get_vendor_db_connection();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validate required fields
        if (empty($_POST['vendor_name'])) {
            throw new Exception('Vendor name is required.');
        }

        // Sanitize inputs
        $vendor_name = trim(strip_tags($_POST['vendor_name']));
        $contact_person = trim(strip_tags($_POST['contact_person'] ?? ''));
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = trim(strip_tags($_POST['phone'] ?? ''));
        $address = trim(strip_tags($_POST['address'] ?? ''));

        // Insert vendor details
        $stmt = $pdo->prepare("INSERT INTO vendors (vendor_name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $vendor_name,
            $contact_person,
            $email,
            $phone,
            $address
        ]);
        
        // Store vendor ID in session
        $_SESSION['vendor_id'] = $pdo->lastInsertId();
        
        // Redirect to mapping page
        header('Location: mapping.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error = 'Database connection error. Please try again later.';
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Details</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .submit-btn { 
            background: #4CAF50; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Enter Vendor Details</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="vendor_name">Vendor Name *</label>
                <input type="text" id="vendor_name" name="vendor_name" required>
            </div>
            
            <div class="form-group">
                <label for="contact_person">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone">
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"></textarea>
            </div>
            
            <button type="submit" class="submit-btn">Continue to Mapping</button>
        </form>
    </div>
</body>
</html> 