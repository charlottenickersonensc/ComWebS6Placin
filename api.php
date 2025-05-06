<?php
/**
 * API de Gestion Scolaire - Tous les points d'accès dans un seul fichier
 * Gère l'authentification, la récupération des notes et la gestion des notes
 */

// Inclusion de la configuration
require_once 'config.php';

// Activation CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gestion des requêtes OPTIONS préliminaires
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Connexion à la base de données
$db = getDbConnection();

// Récupération du point d'accès depuis la requête
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Gestion des différents points d'accès
switch ($endpoint) {
    case 'login':
        handleLogin($db);
        break;
        
    case 'eleve_notes':
        handleEleveNotes($db);
        break;
        
    case 'classe_notes':
        handleClasseNotes($db);
        break;
        
    case 'ajouter_note':
        handleAjouterNote($db);
        break;
        
    case 'modifier_note':
        handleModifierNote($db);
        break;
        
    default:
        // Affichage des informations de l'API par défaut
        sendResponse(200, [
            "name" => "API de Gestion Scolaire",
            "version" => "1.0.0",
            "description" => "API RESTful pour système de gestion scolaire",
            "endpoints" => [
                "?endpoint=login" => "Se connecter et obtenir un token",
                "?endpoint=eleve_notes" => "Récupérer les notes d'un élève",
                "?endpoint=classe_notes&id={class_id}" => "Récupérer toutes les notes d'une classe",
                "?endpoint=ajouter_note" => "Ajouter une nouvelle note",
                "?endpoint=modifier_note" => "Modifier une note existante"
            ]
        ]);
        break;
}

/**
 * Gestion de la connexion et de l'authentification
 */
function handleLogin($db) {
    // Accepter uniquement les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, ["success" => false, "message" => "Méthode non autorisée"]);
    }
    
    // Récupération des données envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérification des champs requis
    if (empty($data->email) || empty($data->password)) {
        sendResponse(400, ["success" => false, "message" => "Email et mot de passe sont requis"]);
    }
    
    // Recherche de l'utilisateur dans la base de données
    $query = "SELECT id, nom, prenom, email, mot_de_passe, type_utilisateur 
              FROM Utilisateurs 
              WHERE email = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérification du mot de passe
        if (password_verify($data->password, $user['mot_de_passe'])) {
            // Génération du token
            $token = generateToken(
                $user['id'],
                $user['nom'],
                $user['prenom'],
                $user['email'],
                $user['type_utilisateur']
            );
            
            // Retour des informations utilisateur et du token
            sendResponse(200, [
                "success" => true,
                "message" => "Connexion réussie",
                "id" => $user['id'],
                "nom" => $user['nom'],
                "prenom" => $user['prenom'],
                "email" => $user['email'],
                "type_utilisateur" => $user['type_utilisateur'],
                "token" => $token
            ]);
        }
    }
    
    // Si on arrive ici, l'authentification a échoué
    sendResponse(401, ["success" => false, "message" => "Email ou mot de passe invalide"]);
}

/**
 * Gestion de la récupération des notes d'un élève
 */
function handleEleveNotes($db) {
    // Récupération et vérification du token d'authentification
    $token = getAuthorizationToken();
    
    if (!$token) {
        sendResponse(401, ["success" => false, "message" => "Authentification requise"]);
    }
    
    // Vérification des permissions - tout le monde peut accéder mais on filtre selon le rôle
    $auth = checkPermissions($token);
    
    if (!$auth['success']) {
        sendResponse(401, ["success" => false, "message" => $auth['message']]);
    }
    
    $user_data = $auth['data'];
    $user_id = $user_data['id'];
    $user_type = $user_data['type_utilisateur'];
    
    // Détermination des notes à récupérer
    if ($user_type === 'eleve') {
        // Les élèves ne peuvent voir que leurs propres notes
        $student_id = $user_id;
    } else if (($user_type === 'professeur' || $user_type === 'admin') && isset($_GET['id_eleve'])) {
        // Les professeurs et les administrateurs peuvent demander les notes d'un élève spécifique
        $student_id = intval($_GET['id_eleve']);
        
        // Si c'est un professeur, vérifier qu'il enseigne à cet élève
        if ($user_type === 'professeur') {
            $check_query = "SELECT ec.id_eleve
                           FROM Matiere m 
                           JOIN Notes n ON m.id = n.id_matiere
                           JOIN Eleves_Classes ec ON n.id_eleve = ec.id_eleve
                           WHERE m.id_professeur = :user_id AND ec.id_eleve = :id_eleve";
            
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":user_id", $user_id);
            $check_stmt->bindParam(":id_eleve", $student_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                sendResponse(403, ["success" => false, "message" => "Vous n'enseignez pas à cet élève"]);
            }
        }
    } else {
        sendResponse(400, ["success" => false, "message" => "ID élève manquant ou permissions insuffisantes"]);
    }
    
    // Récupération des notes
    $query = "SELECT n.id, n.valeur, n.commentaire, n.date, 
                m.nom AS matiere_nom, m.description AS matiere_description,
                u.nom AS professeur_nom, u.prenom AS professeur_prenom
              FROM Notes n
              JOIN Matiere m ON n.id_matiere = m.id
              LEFT JOIN Utilisateurs u ON m.id_professeur = u.id
              WHERE n.id_eleve = :student_id
              ORDER BY n.date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    
    $notes = [];
    $total = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notes[] = [
            'id' => $row['id'],
            'valeur' => $row['valeur'],
            'commentaire' => $row['commentaire'],
            'date' => $row['date'],
            'matiere' => [
                'nom' => $row['matiere_nom'],
                'description' => $row['matiere_description']
            ],
            'professeur' => [
                'nom' => $row['professeur_nom'],
                'prenom' => $row['professeur_prenom']
            ]
        ];
        
        $total += $row['valeur'];
    }
    
    $count = count($notes);
    $moyenne = $count > 0 ? round($total / $count, 2) : 0;
    
    // Retour des résultats
    sendResponse(200, [
        'success' => true,
        'moyenne' => $moyenne,
        'nombre_notes' => $count,
        'notes' => $notes
    ]);
}

/**
 * Gestion de la récupération des notes pour une classe entière
 */
function handleClasseNotes($db) {
    // Récupération et vérification du token d'authentification
    $token = getAuthorizationToken();
    
    if (!$token) {
        sendResponse(401, ["success" => false, "message" => "Authentification requise"]);
    }
    
    // Vérification des permissions - seulement les professeurs et les administrateurs
    $auth = checkPermissions($token, ['professeur', 'admin']);
    
    if (!$auth['success']) {
        sendResponse(401, ["success" => false, "message" => $auth['message']]);
    }
    
    $user_data = $auth['data'];
    $user_id = $user_data['id'];
    $user_type = $user_data['type_utilisateur'];
    
    // Vérification de l'ID de classe
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        sendResponse(400, ["success" => false, "message" => "ID de classe requis"]);
    }
    
    $id_classe = intval($_GET['id']);
    
    // Si l'utilisateur est un professeur, vérifier qu'il enseigne à cette classe
    if ($user_type === 'professeur') {
        $check_query = "SELECT DISTINCT m.id 
                       FROM Matiere m 
                       JOIN Notes n ON m.id = n.id_matiere
                       JOIN Eleves_Classes ec ON n.id_eleve = ec.id_eleve
                       WHERE m.id_professeur = :user_id AND ec.id_classe = :id_classe";
        
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":user_id", $user_id);
        $check_stmt->bindParam(":id_classe", $id_classe);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            sendResponse(403, ["success" => false, "message" => "Vous n'enseignez pas à cette classe"]);
        }
    }
    
    // Récupération des informations de la classe
    $classe_query = "SELECT * FROM Classes WHERE id = :id_classe";
    $classe_stmt = $db->prepare($classe_query);
    $classe_stmt->bindParam(":id_classe", $id_classe);
    $classe_stmt->execute();
    
    if ($classe_stmt->rowCount() === 0) {
        sendResponse(404, ["success" => false, "message" => "Classe non trouvée"]);
    }
    
    $classe = $classe_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupération des élèves de la classe
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
    
    while ($student = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Récupération des notes de l'élève
        $notes_query = "SELECT n.id, n.valeur, n.commentaire, n.date, 
                        m.id AS matiere_id, m.nom AS matiere_nom
                      FROM Notes n
                      JOIN Matiere m ON n.id_matiere = m.id
                      WHERE n.id_eleve = :id_eleve";
        
        $notes_stmt = $db->prepare($notes_query);
        $notes_stmt->bindParam(":id_eleve", $student["id"]);
        $notes_stmt->execute();
        
        $notes = [];
        $total = 0;
        
        while ($note = $notes_stmt->fetch(PDO::FETCH_ASSOC)) {
            $note_item = [
                'id' => $note['id'],
                'valeur' => $note['valeur'],
                'commentaire' => $note['commentaire'],
                'date' => $note['date'],
                'matiere' => [
                    'id' => $note['matiere_id'],
                    'nom' => $note['matiere_nom']
                ]
            ];
            
            $notes[] = $note_item;
            $all_notes[] = $note_item;
            $total += $note['valeur'];
        }
        
        $count = count($notes);
        $moyenne = $count > 0 ? round($total / $count, 2) : 0;
        
        $students[] = [
            'id' => $student['id'],
            'nom' => $student['nom'],
            'prenom' => $student['prenom'],
            'email' => $student['email'],
            'moyenne' => $moyenne,
            'nombre_notes' => $count,
            'notes' => $notes
        ];
    }
    
    // Calcul de la moyenne de la classe
    $class_total = 0;
    $class_count = count($all_notes);
    
    foreach ($all_notes as $note) {
        $class_total += $note['valeur'];
    }
    
    $class_moyenne = $class_count > 0 ? round($class_total / $class_count, 2) : 0;
    
    // Retour des résultats
    sendResponse(200, [
        'success' => true,
        'classe' => [
            'id' => $classe['id'],
            'nom' => $classe['nom']
        ],
        'moyenne_classe' => $class_moyenne,
        'nombre_eleves' => count($students),
        'nombre_notes' => $class_count,
        'eleves' => $students
    ]);
}

/**
 * Gestion de l'ajout d'une nouvelle note
 */
function handleAjouterNote($db) {
    // Uniquement les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, ["success" => false, "message" => "Méthode non autorisée"]);
    }
    
    // Récupération et vérification du token d'authentification
    $token = getAuthorizationToken();
    
    if (!$token) {
        sendResponse(401, ["success" => false, "message" => "Authentification requise"]);
    }
    
    // Vérification des permissions - seulement les professeurs et les administrateurs
    $auth = checkPermissions($token, ['professeur', 'admin']);
    
    if (!$auth['success']) {
        sendResponse(401, ["success" => false, "message" => $auth['message']]);
    }
    
    $user_data = $auth['data'];
    $user_id = $user_data['id'];
    $user_type = $user_data['type_utilisateur'];
    
    // Récupération des données envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérification des champs requis
    if (empty($data->id_eleve) || empty($data->id_matiere) || !isset($data->valeur) || empty($data->date)) {
        sendResponse(400, [
            "success" => false, 
            "message" => "Champs requis: id_eleve, id_matiere, valeur, date"
        ]);
    }
    
    // Validation de la valeur de la note
    if (!is_numeric($data->valeur) || $data->valeur < 0 || $data->valeur > 20) {
        sendResponse(400, ["success" => false, "message" => "La note doit être entre 0 et 20"]);
    }
    
    // Si l'utilisateur est un professeur, vérifier qu'il enseigne cette matière
    if ($user_type === 'professeur') {
        $check_query = "SELECT id FROM Matiere WHERE id = :id_matiere AND id_professeur = :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":id_matiere", $data->id_matiere);
        $check_stmt->bindParam(":user_id", $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            sendResponse(403, ["success" => false, "message" => "Vous n'enseignez pas cette matière"]);
        }
    }
    
    // Insertion de la nouvelle note
    $query = "INSERT INTO Notes (id_eleve, id_matiere, valeur, commentaire, date) 
              VALUES (:id_eleve, :id_matiere, :valeur, :commentaire, :date)";
    
    $stmt = $db->prepare($query);
    
    $id_eleve = htmlspecialchars(strip_tags($data->id_eleve));
    $id_matiere = htmlspecialchars(strip_tags($data->id_matiere));
    $valeur = htmlspecialchars(strip_tags($data->valeur));
    $commentaire = isset($data->commentaire) ? htmlspecialchars(strip_tags($data->commentaire)) : null;
    $date = htmlspecialchars(strip_tags($data->date));
    
    $stmt->bindParam(':id_eleve', $id_eleve);
    $stmt->bindParam(':id_matiere', $id_matiere);
    $stmt->bindParam(':valeur', $valeur);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':date', $date);
    
    if ($stmt->execute()) {
        $id = $db->lastInsertId();
        
        sendResponse(201, [
            'success' => true,
            'message' => 'Note ajoutée avec succès',
            'id' => $id
        ]);
    } else {
        sendResponse(500, ["success" => false, "message" => "Échec de l'ajout de la note"]);
    }
}

/**
 * Gestion de la modification d'une note existante
 */
function handleModifierNote($db) {
    // Uniquement les requêtes PUT
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendResponse(405, ["success" => false, "message" => "Méthode non autorisée"]);
    }
    
    // Récupération et vérification du token d'authentification
    $token = getAuthorizationToken();
    
    if (!$token) {
        sendResponse(401, ["success" => false, "message" => "Authentification requise"]);
    }
    
    // Vérification des permissions - seulement les professeurs et les administrateurs
    $auth = checkPermissions($token, ['professeur', 'admin']);
    
    if (!$auth['success']) {
        sendResponse(401, ["success" => false, "message" => $auth['message']]);
    }
    
    $user_data = $auth['data'];
    $user_id = $user_data['id'];
    $user_type = $user_data['type_utilisateur'];
    
    // Récupération des données envoyées
    $data = json_decode(file_get_contents("php://input"));
    
    // Vérification des champs requis
    if (empty($data->id) || !isset($data->valeur)) {
        sendResponse(400, ["success" => false, "message" => "ID de note et valeur sont requis"]);
    }
    
    // Validation de la valeur de la note
    if (!is_numeric($data->valeur) || $data->valeur < 0 || $data->valeur > 20) {
        sendResponse(400, ["success" => false, "message" => "La note doit être entre 0 et 20"]);
    }
    
    // Si l'utilisateur est un professeur, vérifier qu'il possède cette note
    if ($user_type === 'professeur') {
        $check_query = "SELECT n.id
                      FROM Notes n
                      JOIN Matiere m ON n.id_matiere = m.id
                      WHERE n.id = :note_id AND m.id_professeur = :user_id";
                      
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":note_id", $data->id);
        $check_stmt->bindParam(":user_id", $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            sendResponse(403, ["success" => false, "message" => "Vous n'avez pas la permission de modifier cette note"]);
        }
    }
    
    // Mise à jour de la note
    $query = "UPDATE Notes SET valeur = :valeur";
    
    // Ajout des champs optionnels si fournis
    if (!empty($data->commentaire)) {
        $query .= ", commentaire = :commentaire";
    }
    
    if (!empty($data->date)) {
        $query .= ", date = :date";
    }
    
    $query .= " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':id', $data->id);
    $stmt->bindParam(':valeur', $data->valeur);
    
    if (!empty($data->commentaire)) {
        $commentaire = htmlspecialchars(strip_tags($data->commentaire));
        $stmt->bindParam(':commentaire', $commentaire);
    }
    
    if (!empty($data->date)) {
        $date = htmlspecialchars(strip_tags($data->date));
        $stmt->bindParam(':date', $date);
    }
    
    if ($stmt->execute()) {
        sendResponse(200, ["success" => true, "message" => "Note mise à jour avec succès"]);
    } else {
        sendResponse(500, ["success" => false, "message" => "Échec de la mise à jour de la note"]);
    }
}