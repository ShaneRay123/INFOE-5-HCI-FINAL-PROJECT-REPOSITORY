<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN job_title VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN location VARCHAR(100) DEFAULT NULL"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "Executed: $query\n";
        } catch (PDOException $e) {
            // Ignore error if column already exists (Duplicate column name)
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "Skipped (already exists): $query\n";
            } else {
                echo "Error executing $query: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Database schema updated successfully.\n";
    
} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
