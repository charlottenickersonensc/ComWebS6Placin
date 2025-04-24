<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
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

// Validate token and check permissions
$auth_result = $auth->checkPermissions($token, ["eleve", "professeur", "admin"]);

if(!$auth_result["success"]) {
    Utils::sendResponse(401, [
        "success" => false,
        "message" => $auth_result["message"]
    ]);
}

$user_data = $auth_result["data"];
$user_id = $user_data["id"];
$user_type = $user_data["type_utilisateur"];

// SQL query to get student grades
if($user_type == "eleve") {
    // Get all notes for the logged-in student
    $query = "SELECT n.id, n.valeur, n.commentaire, n.date, 
                m.nom AS matiere_nom, m.description AS matiere_description,
                u.nom AS professeur_nom, u.prenom AS professeur_prenom
              FROM Notes n
              JOIN Matiere m ON n.id_matiere = m.id
              LEFT JOIN Utilisateurs u ON m.id_professeur = u.id
              WHERE n.id_eleve = :user_id
              ORDER BY n.date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
} elseif(isset($_GET['id_eleve']) && ($user_type == "professeur" || $user_type == "admin")) {
    // Professor or admin can view specific student grades
    $id_eleve = intval($_GET['id_eleve']);
    
    // If professor, check if they teach the student
    if($user_type == "professeur") {
        $check_query = "SELECT ec.id_eleve
                       FROM Matiere m 
                       JOIN Notes n ON m.id = n.id_matiere
                       JOIN Eleves_Classes ec ON n.id_eleve = ec.id_eleve
                       WHERE m.id_professeur = :user_id AND ec.id_eleve = :id_eleve";
        
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":user_id", $user_id);
        $check_stmt->bindParam(":id_eleve", $id_eleve);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() == 0) {
            Utils::sendResponse(403, [
                "success" => false,
                "message" => "Access denied. You don't teach this student."
            ]);
        }
    }
    
    // Get all notes for the specified student
    $query = "SELECT n.id, n.valeur, n.commentaire, n.date, 
                m.nom AS matiere_nom, m.description AS matiere_description,
                u.nom AS professeur_nom, u.prenom AS professeur_prenom
              FROM Notes n
              JOIN Matiere m ON n.id_matiere = m.id
              LEFT JOIN Utilisateurs u ON m.id_professeur = u.id
              WHERE n.id_eleve = :id_eleve
              ORDER BY n.date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id_eleve", $id_eleve);
    $stmt->execute();
} else {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "Missing required parameters or insufficient permissions."
    ]);
}

// Check if any records found
if($stmt->rowCount() > 0) {
    // Notes array
    $notes_arr = [];
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $note_item = [
            "id" => $row["id"],
            "valeur" => $row["valeur"],
            "commentaire" => $row["commentaire"],
            "date" => $row["date"],
            "matiere" => [
                "nom" => $row["matiere_nom"],
                "description" => $row["matiere_description"]
            ],
            "professeur" => [
                "nom" => $row["professeur_nom"],
                "prenom" => $row["professeur_prenom"]
            ]
        ];
        
        array_push($notes_arr, $note_item);
    }
    
    // Calculate average
    $total_value = 0;
    $count = count($notes_arr);
    
    foreach($notes_arr as $note) {
        $total_value += $note["valeur"];
    }
    
    $average = $count > 0 ? $total_value / $count : 0;
    
    // Return success response
    Utils::sendResponse(200, [
        "success" => true,
        "moyenne" => round($average, 2),
        "nombre_notes" => $count,
        "notes" => $notes_arr
    ]);
} else {
    // No notes found
    Utils::sendResponse(200, [
        "success" => true,
        "message" => "Aucune note trouvée.",
        "moyenne" => 0,
        "nombre_notes" => 0,
        "notes" => []
    ]);
}