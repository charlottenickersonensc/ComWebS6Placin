<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include necessary files
include_once '../includes/db.php';
include_once '../includes/auth.php';
include_once '../includes/utils.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create auth object
$auth = new Auth($db);

// Get JWT token from the request
$token = Utils::getAuthorizationToken();

// Check if token is valid
if(!$token) {
    Utils::sendResponse(401, [
        "success" => false,
        "message" => "Access denied. Token not provided."
    ]);
}

// Validate token and check permissions (only professors and admins)
$auth_result = $auth->checkPermissions($token, ["professeur", "admin"]);

if(!$auth_result["success"]) {
    Utils::sendResponse(401, [
        "success" => false,
        "message" => $auth_result["message"]
    ]);
}

// Get user data from token
$user_data = $auth_result["data"];
$user_id = $user_data["id"];
$user_type = $user_data["type_utilisateur"];

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Make sure note ID is provided
if(empty($data->id)) {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "Note ID is required."
    ]);
}

// Get the note
$note_query = "SELECT n.*, m.id_professeur FROM Notes n 
              JOIN Matiere m ON n.id_matiere = m.id 
              WHERE n.id = :note_id";
              
$note_stmt = $db->prepare($note_query);
$note_stmt->bindParam(":note_id", $data->id);
$note_stmt->execute();

if($note_stmt->rowCount() == 0) {
    Utils::sendResponse(404, [
        "success" => false,
        "message" => "Note not found."
    ]);
}

$note = $note_stmt->fetch(PDO::FETCH_ASSOC);

// If user is a professor, check if they teach this subject
if($user_type == "professeur" && $note["id_professeur"] != $user_id) {
    Utils::sendResponse(403, [
        "success" => false,
        "message" => "Access denied. You are not the teacher of this subject."
    ]);
}

// Make sure at least one field to update is provided
if(!isset($data->valeur) && !isset($data->commentaire) && !isset($data->date)) {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "No fields to update. Provide at least one of: valeur, commentaire, date."
    ]);
}

// Validate grade value if provided
if(isset($data->valeur)) {
    if(!is_numeric($data->valeur) || $data->valeur < 0 || $data->valeur > 20) {
        Utils::sendResponse(400, [
            "success" => false,
            "message" => "La valeur de la note doit être entre 0 et 20."
        ]);
    }
}

// Build update query
$query = "UPDATE Notes SET ";
$params = [];

if(isset($data->valeur)) {
    $query .= "valeur = :valeur, ";
    $params[":valeur"] = htmlspecialchars(strip_tags($data->valeur));
}

if(isset($data->commentaire)) {
    $query .= "commentaire = :commentaire, ";
    $params[":commentaire"] = htmlspecialchars(strip_tags($data->commentaire));
}

if(isset($data->date)) {
    $query .= "date = :date, ";
    $params[":date"] = htmlspecialchars(strip_tags($data->date));
}

// Remove trailing comma
$query = rtrim($query, ", ");

// Add WHERE clause
$query .= " WHERE id = :id";
$params[":id"] = $data->id;

// Prepare and execute query
$stmt = $db->prepare($query);

foreach($params as $param => $value) {
    $stmt->bindValue($param, $value);
}

// Execute query
if($stmt->execute()) {
    // Get updated note details for response
    $updated_query = "SELECT n.id, n.valeur, n.commentaire, n.date,
                        u.nom AS eleve_nom, u.prenom AS eleve_prenom,
                        m.nom AS matiere_nom
                      FROM Notes n
                      JOIN Utilisateurs u ON n.id_eleve = u.id
                      JOIN Matiere m ON n.id_matiere = m.id
                      WHERE n.id = :note_id";
                      
    $updated_stmt = $db->prepare($updated_query);
    $updated_stmt->bindParam(":note_id", $data->id);
    $updated_stmt->execute();
    
    $updated_note = $updated_stmt->fetch(PDO::FETCH_ASSOC);
    
    Utils::sendResponse(200, [
        "success" => true,
        "message" => "Note updated successfully.",
        "note" => [
            "id" => $updated_note["id"],
            "valeur" => $updated_note["valeur"],
            "commentaire" => $updated_note["commentaire"],
            "date" => $updated_note["date"],
            "eleve" => [
                "nom" => $updated_note["eleve_nom"],
                "prenom" => $updated_note["eleve_prenom"]
            ],
            "matiere" => [
                "nom" => $updated_note["matiere_nom"]
            ]
        ]
    ]);
} else {
    Utils::sendResponse(500, [
        "success" => false,
        "message" => "Failed to update note."
    ]);
}