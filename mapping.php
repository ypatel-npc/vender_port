<?php
session_start();

// Redirect if no CSV file is uploaded
if (!isset($_SESSION['csv_file']) || !isset($_SESSION['csv_headers'])) {
    header('Location: upload.php');
    exit();
}

$csv_headers = $_SESSION['csv_headers'];
$required_fields = ['590']; // Add your required fields here

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapping = $_POST['mapping'];
    $_SESSION['mapping'] = $mapping; // Store mapping in session
    
    // Redirect to progress page instead of process.php
    header('Location: progress.html');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Mapping</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .mapping-form { max-width: 500px; margin: 0 auto; }
        .field-map { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="mapping-form">
        <h2>Map CSV Columns</h2>
        <form method="POST">
            <?php foreach ($required_fields as $field): ?>
            <div class="field-map">
                <label><?php echo ucfirst($field); ?>:</label>
                <select name="mapping[<?php echo htmlspecialchars($field); ?>]" required>
                    <option value="">Select CSV Column</option>
                    <?php foreach ($csv_headers as $index => $header): ?>
                    <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($header); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            <input type="submit" value="Process CSV">
        </form>
    </div>
</body>
</html> 