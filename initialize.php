<?php
$dbFile = 'projects.db';

try {
    // 1. This line creates the physical file if it doesn't exist
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create the Layers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS layers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        color TEXT DEFAULT '#007bff'
    )");

    // 3. Create the Projects table with all fields for your search
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        layer_id INTEGER,
        name TEXT NOT NULL,
        description TEXT,
        progress INTEGER DEFAULT 0,
        start_date TEXT,
        completion_date TEXT,
        engineer TEXT,
        contractor TEXT,
        award_amount TEXT,
        doc_link TEXT,
        geometry TEXT,
        FOREIGN KEY (layer_id) REFERENCES layers(id)
    )");

    echo "Success! 'projects.db' has been created and configured.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
