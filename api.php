<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Content-Security-Policy: frame-ancestors *;"); 

// --- CONFIGURATION ---
$admin_pass = "CityStaff2025"; // Use this consistently in Admin Login
$db_file = 'city_projects.db';

// --- AUTHENTICATION LOGIC ---
$provided_pass = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 1. Handle Login Action
if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['password']) && $data['password'] === $admin_pass) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid Password"]);
    }
    exit;
}

// 2. Protect all non-GET actions (Save/Delete) and the get_layers/get_projects for Admin
// We allow GET requests for the public index.html, but admin.html sends the pass for everything.
$protected_actions = ['save_project', 'save_layer', 'delete', 'get_layers', 'get_projects'];
if ($method !== 'GET' || in_array($action, $protected_actions)) {
    // If it's the admin portal calling, it will have the password.
    // If it's the public portal calling get_projects/get_layers, we can allow it without pass.
    if ($method !== 'GET' && $provided_pass !== $admin_pass) {
        http_response_code(401);
        exit(json_encode(["error" => "Unauthorized"]));
    }
}

// --- DATABASE CONNECTION ---
try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(["error" => $e->getMessage()]));
}

// Ensure Tables Exist
$db->exec("CREATE TABLE IF NOT EXISTS layers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, default_color TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, layer_id INTEGER, 
    start_date TEXT, completion_date TEXT, progress INTEGER, color TEXT, weight INTEGER, 
    doc_link TEXT, geometry TEXT, engineer TEXT, bid_date TEXT, award_date TEXT, 
    award_amount TEXT, contractor TEXT
)");

// --- ROUTING ---

if ($action === 'get_layers') {
    echo json_encode($db->query("SELECT * FROM layers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC));
}

elseif ($action === 'get_projects') {
    $rows = $db->query("SELECT projects.*, layers.name as layer_name FROM projects LEFT JOIN layers ON projects.layer_id = layers.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { 
        $r['geometry'] = json_decode($r['geometry']); 
    }
    echo json_encode($rows);
}

elseif ($action === 'save_layer') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data['id'])) {
        $st = $db->prepare("UPDATE layers SET name=?, default_color=? WHERE id=?");
        $st->execute([$data['name'], $data['default_color'], $data['id']]);
    } else {
        $st = $db->prepare("INSERT INTO layers (name, default_color) VALUES (?,?)");
        $st->execute([$data['name'], $data['default_color']]);
    }
    echo json_encode(["success" => true]);
}

elseif ($action === 'save_project') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Mapping incoming JSON keys to Database Columns
    $params = [
        $data['name'], 
        $data['desc'], 
        $data['layer_id'], 
        $data['start_date'], 
        $data['completion_date'], // Matches Admin JS
        $data['progress'], 
        $data['color'], 
        5, 
        $data['doc'], 
        json_encode($data['geometry']), 
        $data['engineer'], 
        $data['bid_date'] ?? '', 
        $data['award_date'] ?? '', 
        $data['award_amount'], 
        $data['contractor']
    ];

    if (!empty($data['id'])) {
        $params[] = $data['id'];
        $st = $db->prepare("UPDATE projects SET name=?, description=?, layer_id=?, start_date=?, completion_date=?, progress=?, color=?, weight=?, doc_link=?, geometry=?, engineer=?, bid_date=?, award_date=?, award_amount=?, contractor=? WHERE id=?");
    } else {
        $st = $db->prepare("INSERT INTO projects (name, description, layer_id, start_date, completion_date, progress, color, weight, doc_link, geometry, engineer, bid_date, award_date, award_amount, contractor) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    }
    $st->execute($params);
    echo json_encode(["success" => true]);
}

elseif ($action === 'delete') {
    $table = ($_GET['type'] === 'layer') ? 'layers' : 'projects';
    $st = $db->prepare("DELETE FROM $table WHERE id = ?");
    $st->execute([$_GET['id']]);
    echo json_encode(["success" => true]);
}
?>
