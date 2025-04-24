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

// Get class ID from URL
$id_classe = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id_classe <= 0) {
    Utils::sendResponse(400, [
        "success" => false,
        "message" => "Class ID is required."
    ]);
}

// If user is a professor, check if they teach this class
if($user_type == "professeur") {
    $check_query = "SELECT DISTINCT m.id 
                   FROM Matiere m 
                   JOIN Notes n ON m.id = n.id_matiere
                   JOIN Eleves_Classes ec ON n.id_eleve = ec.id_eleve
                   WHERE m.id_professeur = :user_id AND ec.id_classe = :id_classe";
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":user_id", $user_id);
    $check_stmt->bindParam(":id_classe", $id_classe);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        // Additional check - see if professor is assigned to this class directly
        $check_query2 = "SELECT m.id 
                      FROM Matiere m 
                      WHERE m.id_professeur = :user_id";
                      
        $check_stmt2 = $db->prepare($check_query2);
        $check_stmt2->bindParam(":user_id", $user_id);
        $check_stmt2->execute();
        
        if($check_stmt2->rowCount() == 0) {
            Utils::sendResponse(403, [
                "success" => false,
                "message" => "Access denied. You don't teach this class."
            ]);
        }
    }
}

// Get class info
$classe_query = "SELECT * FROM Classes WHERE id = :id_classe";
$classe_stmt = $db->prepare($classe_query);
$classe_stmt->bindParam(":id_classe", $id_classe);
$classe_stmt->execute();

if($classe_stmt->rowCount() == 0) {
    Utils::sendResponse(404, [
        "success" => false,
        "message" => "Class not found."
    ]);
}

$classe_info = $classe_stmt->fetch(PDO::FETCH_ASSOC);

// Get students in the class
$students_query = "SELECT u.id, u.nom, u.prenom, u.email
                  FROM Utilisateurs u
                  JOIN Eleves_Classes ec ON u.id = ec.id_eleve
                  WHERE ec.id_classe = :id_classe
                  ORDER BY u.nom, u.prenom";
                  
$students_stmt = $db->prepare($students_query);
$students_stmt->bindParam(":id_classe", $id_classe);
$students_stmt->execute();

$students = [];
$all_notes = [];

if($students_stmt->rowCount() > 0) {
    while($student = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get student's notes
        $notes_query = "SELECT n.id, n.valeur, n.commentaire, n.date, 
                        m.id AS matiere_id, m.nom AS matiere_nom
                      FROM Notes n
                      JOIN Matiere m ON n.id_matiere = m.id
                      WHERE n.id_eleve = :id_eleve";
                      
        $notes_stmt = $db->prepare($notes_query);
        $notes_stmt->bindParam(":id_eleve", $student["id"]);
        $notes_stmt->execute();
        
        $notes = [];
        $moyenne = 0;
        $total = 0;
        
        while($note = $notes_stmt->fetch(PDO::FETCH_ASSOC)) {
            $notes[] = [
                "id" => $note["id"],
                "valeur" => $note["valeur"],
                "commentaire" => $note["commentaire"],
                "date" => $note["date"],
                "matiere" => [
                    "id" => $note["matiere_id"],
                    "nom" => $note["matiere_nom"]
                ]
            ];
            
            $total += $note["valeur"];
        }
        
        $count = count($notes);
        $moyenne = $count > 0 ? round($total / $count, 2) : 0;
        
        $students[] = [
            "id" => $student["id"],
            "nom" => $student["nom"],
            "prenom" => $student["prenom"],
            "email" => $student["email"],
            "moyenne" => $moyenne,
            "nombre_notes" => $count,
            "notes" => $notes
        ];
        
        $all_notes = array_merge($all_notes, $notes);
    }
    
    // Calculate class average
    $class_total = 0;
    $class_count = count($all_notes);
    
    foreach($all_notes as $note) {
        $class_total += $note["valeur"];
    }
    
    $class_moyenne = $class_count > 0 ? round($class_total / $class_count, 2) : 0;
    
    // Return success response
    Utils::sendResponse(200, [
        "success" => true,
        "classe" => [
            "id" => $classe_info["id"],
            "nom" => $classe_info["nom"]
        ],
        "moyenne_classe" => $class_moyenne,
        "nombre_eleves" => count($students),
        "nombre_notes" => $class_count,
        "eleves" => $students
    ]);
} else {
    // No students found
    Utils::sendResponse(200, [
        "success" => true,
        "classe" => [
            "id" => $classe_info["id"],
            "nom" => $classe_info["nom"]
        ],
        "message" => "Aucun élève trouvé dans cette classe.",
        "moyenne_classe" => 0,
        "nombre_eleves" => 0,
        "nombre_notes" => 0,
        "eleves" => []
    ]);
}