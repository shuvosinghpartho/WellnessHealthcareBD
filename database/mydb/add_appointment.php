<?php
// Allow cross-origin requests from any origin
header("Access-Control-Allow-Origin: *");  // Allow any origin
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// If the request is a preflight OPTIONS request, we return immediately
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'conn.php'; // Include the database connection file

// Get the JSON input data
$data = json_decode(file_get_contents("php://input"), true);  // Parse the JSON data

if ($data) {
    // Extract data from the decoded JSON object
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $service = $data['service'] ?? '';
    $doctor = $data['doctor'] ?? '';
    $date = $data['date'] ?? '';
    $time = $data['time'] ?? '';
    $notes = $data['notes'] ?? '';

    // Prepare and execute the SQL query to insert the appointment
    $stmt = $conn->prepare("INSERT INTO appointment (Name, Email, Phone, Service, Doctor, Date, Time, Notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssisssss", $name, $email, $phone, $service, $doctor, $date, $time, $notes);
        if ($stmt->execute()) {
            echo "✅ Appointment added successfully.";
        } else {
            echo "❌ Error executing the statement: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "❌ Error preparing the statement: " . $conn->error;
    }
} else {
    echo "❌ Invalid JSON data.";
}

$conn->close();
?>

