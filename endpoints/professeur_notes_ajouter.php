<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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

// Make sure data is not empty
if(
    empty($data->id_eleve) || 
    empty($data->id_matiere) || 
    !isset($data->valeur) || 
    empty($data->date)
) {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "Missing required fields. Required: id_eleve, id_matiere, valeur, date."
    ]);
}

// Validate data
if(!is_numeric($data->valeur) || $data->valeur < 0 || $data->valeur > 20) {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "La valeur de la note doit être entre 0 et 20."
    ]);
}

// If user is a professor, check if they teach this subject
if($user_type == "professeur") {
    $check_query = "SELECT id FROM Matiere WHERE id = :id_matiere AND id_professeur = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id_matiere", $data->id_matiere);
    $check_stmt->bindParam(":user_id", $user_id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        Utils::sendResponse(403, [
            "success" => false,
            "message" => "Access denied. You are not the teacher of this subject."
        ]);
    }
}

// Check if student exists
$check_student_query = "SELECT id FROM Utilisateurs WHERE id = :id_eleve AND type_utilisateur = 'eleve'";
$check_student_stmt = $db->prepare($check_student_query);
$check_student_stmt->bindParam(":id_eleve", $data->id_eleve);
$check_student_stmt->execute();

if($check_student_stmt->rowCount() == 0) {
    Utils::sendResponse(404, [
        "success" => false,
        "message" => "Student not found."
    ]);
}

// Check if subject exists
$check_subject_query = "SELECT id FROM Matiere WHERE id = :id_matiere";
$check_subject_stmt = $db->prepare($check_subject_query);
$check_subject_stmt->bindParam(":id_matiere", $data->id_matiere);
$check_subject_stmt->execute();

if($check_subject_stmt->rowCount() == 0) {
    Utils::sendResponse(404, [
        "success" => false,
        "message" => "Subject not found."
    ]);
}

// Create query to insert note
$query = "INSERT INTO Notes (id_eleve, id_matiere, valeur, commentaire, date) 
          VALUES (:id_eleve, :id_matiere, :valeur, :commentaire, :date)";

$stmt = $db->prepare($query);

// Sanitize and bind parameters
$id_eleve = htmlspecialchars(strip_tags($data->id_eleve));
$id_matiere = htmlspecialchars(strip_tags($data->id_matiere));
$valeur = htmlspecialchars(strip_tags($data->valeur));
$commentaire = isset($data->commentaire) ? htmlspecialchars(strip_tags($data->commentaire)) : null;
$date = htmlspecialchars(strip_tags($data->date));

$stmt->bindParam(":id_eleve", $id_eleve);
$stmt->bindParam(":id_matiere", $id_matiere);
$stmt->bindParam(":valeur", $valeur);
$stmt->bindParam(":commentaire", $commentaire);
$stmt->bindParam(":date", $date);

// Execute query
if($stmt->execute()) {
    $note_id = $db->lastInsertId();
    
    // Get note details for response
    $note_query = "SELECT n.id, n.valeur, n.commentaire, n.date,
                        u.nom AS eleve_nom, u.prenom AS eleve_prenom,
                        m.nom AS matiere_nom
                  FROM Notes n
                  JOIN Utilisateurs u ON n.id_eleve = u.id
                  JOIN Matiere m ON n.id_matiere = m.id
                  WHERE n.id = :note_id";
                  
    $note_stmt = $db->prepare($note_query);
    $note_stmt->bindParam(":note_id", $note_id);
    $note_stmt->execute();
    
    $note = $note_stmt->fetch(PDO::FETCH_ASSOC);
    
    Utils::sendResponse(201, [
        "success" => true,
        "message" => "Note added successfully.",
        "note" => [
            "id" => $note["id"],
            "valeur" => $note["valeur"],
            "commentaire" => $note["commentaire"],
            "date" => $note["date"],
            "eleve" => [
                "nom" => $note["eleve_nom"],
                "prenom" => $note["eleve_prenom"]
            ],
            "matiere" => [
                "nom" => $note["matiere_nom"]
            ]
        ]
    ]);
} else {
    Utils::sendResponse(500, [
        "success" => false,
        "message" => "Failed to add note."
    ]);
}