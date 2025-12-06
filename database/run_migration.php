<?php
require_once __DIR__ . '/../include/db_config.php';

try {
    $db = getDBConnection();
    if (!$db) {
        die("Database connection failed.\n");
    }

    $sqlFile = __DIR__ . '/update_telegram_columns.sql';
    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);
    
    // Split by statement (conceptually, but here we can just execute the whole block if using emulation, 
    // or better yet, simpler alter statements without stored proc emulation if possible, 
    // but the prepared statement approach handles IF NOT EXISTS logic well for columns).
    // However, PHP PDO default might not handle multiple statements in one go perfectly unless configured.
    // Let's rely on standard practice: execute raw.

    echo "Executing migration...\n";
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1); // Enable allowing multiple queries
    $db->exec($sql);
    
    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
