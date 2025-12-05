<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conn.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $conn->connect_error
    ]);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input',
        'error' => json_last_error_msg()
    ]);
    exit();
}

$requiredFields = ['id', 'name', 'phone', 'service', 'doctor', 'date', 'time', 'status'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields',
        'missing_fields' => $missingFields
    ]);
    exit();
}

$id = (int)$data['id'];
if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid appointment ID format'
    ]);
    exit();
}

$name = trim($data['name']);
$phone = trim($data['phone']);
$email = isset($data['email']) ? trim($data['email']) : '';
$service = trim($data['service']);
$doctor = trim($data['doctor']);
$date = trim($data['date']);
$time = trim($data['time']);
$status = trim($data['status']);
$notes = isset($data['notes']) ? trim($data['notes']) : '';

if (empty($name) || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Name must be between 1-100 characters'
    ]);
    exit();
}

if (!preg_match('/^[0-9\-\+\(\)\s]{6,20}$/', $phone)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid phone number format'
    ]);
    exit();
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid email format'
    ]);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format (YYYY-MM-DD required)'
    ]);
    exit();
}

if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid time format (HH:MM required)'
    ]);
    exit();
}

$allowedStatuses = ['pending', 'confirmed', 'cancelled'];
if (!in_array($status, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid status value'
    ]);
    exit();
}

try {
    $checkStmt = $conn->prepare("SELECT id FROM appointment WHERE id = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare check failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $id);
    if (!$checkStmt->execute()) {
        throw new Exception("Check execute failed: " . $checkStmt->error);
    }
    
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Appointment not found'
        ]);
        $checkStmt->close();
        exit();
    }
    $checkStmt->close();

    // Corrected SQL query without comments
    $updateStmt = $conn->prepare("UPDATE appointment SET 
        name = ?, 
        email = ?, 
        phone = ?, 
        service = ?, 
        doctor = ?, 
        date = ?, 
        time = ?, 
        status = ?, 
        notes = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    if (!$updateStmt) {
        throw new Exception("Prepare update failed: " . $conn->error);
    }
    
    $updateStmt->bind_param("sssssssssi", 
        $name, $email, $phone, $service, $doctor, 
        $date, $time, $status, $notes, $id
    );
    
    if (!$updateStmt->execute()) {
        throw new Exception("Update execute failed: " . $updateStmt->error);
    }
    
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Appointment updated successfully',
        'affected_rows' => $affectedRows,
        'appointment' => [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'service' => $service,
            'doctor' => $doctor,
            'date' => $date,
            'time' => $time,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database operation failed',
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>