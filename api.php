<?php
header('Content-Type: application/json');

// 1. Path to your database file
// Make sure the PHP process has permission to read this file
$dbFile = 'your_database_file.db'; 

try {
    // Connect using the SQLite driver
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

// 2. Route Handling
$action = $_GET['action'] ?? '';

if ($action === 'get_layers') {
    $stmt = $pdo->query("SELECT id, name, color FROM layers ORDER BY name ASC");
    echo json_encode($stmt->fetchAll());
} 

elseif ($action === 'get_projects') {
    // Join projects and layers
    $sql = "SELECT 
                p.*, 
                l.name as layer_name, 
                l.color as color 
            FROM projects p
            LEFT JOIN layers l ON p.layer_id = l.id
            ORDER BY p.id DESC";
            
    $stmt = $pdo->query($sql);
    $projects = $stmt->fetchAll();

    // 3. Process Geometry
    foreach ($projects as &$p) {
        if (!empty($p['geometry'])) {
            // SQLite stores strings, so we decode it for Leaflet
            $p['geometry'] = json_decode($p['geometry']);
        }
    }

    echo json_encode($projects);
} 

else {
    echo json_encode(['error' => 'Invalid action']);
}
