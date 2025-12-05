<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'conn.php';

// Check for single appointment request by ID
$id = $_GET['id'] ?? null;
if ($id) {
    // Validate ID is numeric
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid appointment ID']);
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM appointment WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
        exit();
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        exit();
    }

    $row = $result->fetch_assoc();
    $appointment = [
        'id' => $row['id'],
        'Name' => $row['Name'],
        'Email' => $row['Email'],
        'Phone' => $row['Phone'],
        'Service' => $row['Service'],
        'Doctor' => $row['Doctor'],
        'Date' => $row['Date'],
        'Time' => $row['Time'],
        'Status' => $row['Status'],
        'Notes' => $row['Notes']
    ];
    
    echo json_encode([$appointment]);
    $stmt->close();
    $conn->close();
    exit();
}

// Handle multiple appointments request with filters
$searchTerm = $_GET['search'] ?? '';
$searchType = $_GET['searchType'] ?? 'name';
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM appointment WHERE 1=1";
$params = [];
$types = '';

// Add search conditions
if (!empty($searchTerm)) {
    if ($searchType === 'name') {
        $sql .= " AND (Name LIKE ? OR Email LIKE ? OR Doctor LIKE ? OR Service LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_fill(0, 4, $searchParam);
        $types .= str_repeat('s', 4);
    } else {
        $phoneDigits = preg_replace('/\D/', '', $searchTerm);
        $sql .= " AND REPLACE(REPLACE(REPLACE(REPLACE(Phone, '+', ''), ' ', ''), '-', ''), '.', '') LIKE ?";
        $phoneParam = "%$phoneDigits%";
        $params[] = $phoneParam;
        $types .= 's';
    }
}

// Add status filter
if (!empty($statusFilter)) {
    $sql .= " AND Status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit();
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

$result = $stmt->get_result();
$appointments = [];

while ($row = $result->fetch_assoc()) {
    $appointments[] = [
        'id' => $row['id'],
        'Name' => $row['Name'],
        'Email' => $row['Email'],
        'Phone' => $row['Phone'],
        'Service' => $row['Service'],
        'Doctor' => $row['Doctor'],
        'Date' => $row['Date'],
        'Time' => $row['Time'],
        'Status' => $row['Status'],
        'Notes' => $row['Notes']
    ];
}

echo json_encode($appointments);

$stmt->close();
$conn->close();
?>