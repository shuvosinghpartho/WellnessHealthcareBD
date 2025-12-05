<?php
// Enable error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Allow requests from your frontend (replace with your actual frontend origin if needed)
header("Access-Control-Allow-Origin: *");  // Your frontend URL
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Include the database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "WellnessHealthcareBD";  // Replace with your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If the request is a preflight OPTIONS request, we return immediately
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
?>

