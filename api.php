<?php
header('Content-Type: application/json');

$dbFile = 'projects.db'; 
$adminPassword = 'YourPassword123'; // CHANGE THIS TO YOUR SECURE PASSWORD

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

// Simple Auth Check for Admin Actions
$providedPass = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isDelete = $_SERVER['REQUEST_METHOD'] === 'DELETE';

$action = $_GET['action'] ?? '';

// 1. LOGIN ACTION
if ($action === 'login' && $isPost) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['password'] ?? '') === $adminPassword) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}

// 2. GET ACTIONS (Public & Admin)
if ($action === 'get_layers') {
    $stmt = $pdo->query("SELECT id, name, color as default_color FROM layers ORDER BY name ASC");
    echo json_encode($stmt->fetchAll());
} 

elseif ($action === 'get_projects') {
    $sql = "SELECT p.*, l.name as layer_name, l.color as layer_color 
            FROM projects p LEFT JOIN layers l ON p.layer_id = l.id ORDER BY p.id DESC";
    $stmt = $pdo->query($sql);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$p) {
        if (!empty($p['geometry'])) $p['geometry'] = json_decode($p['geometry']);
    }
    echo json_encode($projects);
} 

// 3. ADMIN SAVE ACTIONS (Requires Password)
if ($providedPass !== $adminPassword) {
    http_response_code(401);
    exit;
}

if ($action === 'save_layer' && $isPost) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data['id'])) {
        $stmt = $pdo->prepare("UPDATE layers SET name = ?, color = ? WHERE id = ?");
        $stmt->execute([$data['name'], $data['default_color'], $data['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO layers (name, color) VALUES (?, ?)");
        $stmt->execute([$data['name'], $data['default_color']]);
    }
    echo json_encode(['success' => true]);
}

elseif ($action === 'save_project' && $isPost) {
    $data = json_decode(file_get_contents('php://input'), true);
    $geo = json_encode($data['geometry']);
    
    if (!empty($data['id'])) {
        $sql = "UPDATE projects SET name=?, description=?, layer_id=?, start_date=?, completion_date=?, progress=?, color=?, doc_link=?, engineer=?, contractor=?, award_amount=?, geometry=? WHERE id=?";
        $params = [$data['name'], $data['desc'], $data['layer_id'], $data['start_date'], $data['completion_date'], $data['progress'], $data['color'], $data['doc'], $data['engineer'], $data['contractor'], $data['award_amount'], $geo, $data['id']];
    } else {
        $sql = "INSERT INTO projects (name, description, layer_id, start_date, completion_date, progress, color, doc_link, engineer, contractor, award_amount, geometry) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = [$data['name'], $data['desc'], $data['layer_id'], $data['start_date'], $data['completion_date'], $data['progress'], $data['color'], $data['doc'], $data['engineer'], $data['contractor'], $data['award_amount'], $geo];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true]);
}

elseif ($action === 'delete' && $isDelete) {
    $type = $_GET['type'] === 'layer' ? 'layers' : 'projects';
    $stmt = $pdo->prepare("DELETE FROM $type WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    echo json_encode(['success' => true]);
}
