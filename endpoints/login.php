<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database and auth files
include_once '../includes/db.php';
include_once '../includes/auth.php';
include_once '../includes/utils.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create auth object
$auth = new Auth($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Make sure data is not empty
if(!empty($data->email) && !empty($data->password)) {
    
    // Login the user
    $result = $auth->login($data->email, $data->password);
    
    if($result["success"]) {
        // Success
        Utils::sendResponse(200, $result);
    } else {
        // Login failed
        Utils::sendResponse(401, [
            "success" => false,
            "message" => $result["message"]
        ]);
    }
} else {
    // Data incomplete
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "Email and password are required."
    ]);
}