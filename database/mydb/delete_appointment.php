<?php
// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set appropriate headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Include database connection
require 'conn.php';

// Verify database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $conn->connect_error
    ]);
    exit();
}

// Get and validate input data
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input'
    ]);
    exit();
}

if (!isset($input['id']) || empty($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Appointment ID is required'
    ]);
    exit();
}

$appointmentId = (int)$input['id'];

try {
    // Check if appointment exists first
    $checkStmt = $conn->prepare("SELECT id FROM appointment WHERE id = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare check failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $appointmentId);
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
        exit();
    }
    $checkStmt->close();

    // Prepare delete statement
    $deleteStmt = $conn->prepare("DELETE FROM appointment WHERE id = ?");
    if (!$deleteStmt) {
        throw new Exception("Prepare delete failed: " . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $appointmentId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Delete execute failed: " . $deleteStmt->error);
    }
    
    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();
    
    if ($affectedRows === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'No appointment was deleted (possibly already deleted)'
        ]);
        exit();
    }
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Appointment deleted successfully',
        'deleted_id' => $appointmentId
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