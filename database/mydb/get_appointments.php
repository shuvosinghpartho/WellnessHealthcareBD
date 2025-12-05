<?php
// Error handling first
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Headers must be first
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection with error handling
require 'conn.php';

// Validate database connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Handle single appointment request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    if (!is_numeric($id)) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid appointment ID']));
    }

    $stmt = $conn->prepare("SELECT * FROM appointment WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        die(json_encode(['error' => 'Database query failed']));
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['error' => 'Appointment not found']));
    }

    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    die(json_encode([$row]));
}

// Handle multiple appointments
try {
    $searchTerm = $_GET['search'] ?? '';
    $searchType = $_GET['searchType'] ?? 'name';
    $statusFilter = $_GET['status'] ?? '';

    $sql = "SELECT * FROM appointment WHERE 1=1";
    $params = [];
    $types = '';

    // Search filters
    if (!empty($searchTerm)) {
        if ($searchType === 'name') {
            $sql .= " AND (Name LIKE ? OR Email LIKE ? OR Doctor LIKE ? OR Service LIKE ?)";
            $searchParam = "%$searchTerm%";
            $params = array_fill(0, 4, $searchParam);
            $types .= 'ssss';
        } else {
            $phoneDigits = preg_replace('/\D/', '', $searchTerm);
            $sql .= " AND REPLACE(Phone, ' ', '') LIKE ?";
            $params[] = "%$phoneDigits%";
            $types .= 's';
        }
    }

    // Status filter
    if (!empty($statusFilter)) {
        $sql .= " AND Status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($appointments);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $stmt->close();
    $conn->close();
}
?>