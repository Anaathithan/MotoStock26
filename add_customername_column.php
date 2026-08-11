<?php
// Database migration script to add customerName column to Sale table
require_once 'includes/config.php';

echo "Adding customerName column to Sale table...\n";

// Check if column already exists
$check = $conn->query("SHOW COLUMNS FROM sale LIKE 'customerName'");
if ($check && $check->num_rows > 0) {
    echo "customerName column already exists.\n";
} else {
    // Add the column
    $sql = "ALTER TABLE Sale ADD COLUMN customerName VARCHAR(80) DEFAULT NULL AFTER customerID";
    
    if ($conn->query($sql)) {
        echo "customerName column added successfully.\n";
        
        // Update existing records to have 'Walk-in Customer' as default
        $update = $conn->query("UPDATE sale SET customerName = 'Walk-in Customer' WHERE customerName IS NULL");
        if ($update) {
            echo "Existing records updated with default customer names.\n";
        }
    } else {
        echo "Error adding customerName column: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Migration completed.\n";
?>
