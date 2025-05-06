<?php
/**
 * Fichier de configuration pour l'API de gestion scolaire
 * Contient les paramètres de base de données et les fonctions utilitaires
 */

// Activer les rapports d'erreurs pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Clé secrète JWT
define('JWT_SECRET', 'your_secret_key_for_jwt');

// Fonction de connexion à la base de données
function getDbConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
        return $conn;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
        exit();
    }
}

// Générer un token JWT
function generateToken($id, $nom, $prenom, $email, $type_utilisateur) {
    $issuedAt = time();
    $expirationTime = $issuedAt + 3600; // Valide pour 1 heure
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'data' => [
            'id' => $id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'type_utilisateur' => $type_utilisateur
        ]
    ];
    
    // Implémentation simple de JWT pour démonstration
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode($payload));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    return "$header.$payload.$signature";
}

// Valider un token JWT
function validateToken($token) {
    $parts = explode('.', $token);
    
    if (count($parts) != 3) {
        return ["success" => false, "message" => "Format de token invalide"];
    }
    
    list($header, $payload, $signature) = $parts;
    
    // Vérifier la signature
    $valid_signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    if ($signature !== $valid_signature) {
        return ["success" => false, "message" => "Signature du token invalide"];
    }
    
    // Vérifier si le token a expiré
    $payload_data = json_decode(base64_decode($payload), true);
    
    if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return ["success" => false, "message" => "Le token a expiré"];
    }
    
    return [
        "success" => true,
        "data" => $payload_data['data']
    ];
}

// Récupérer le token d'autorisation des en-têtes
function getAuthorizationToken() {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $auth = $headers['authorization'];
    } else {
        return null;
    }
    
    if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Vérifier les permissions de l'utilisateur
function checkPermissions($token, $allowed_types = []) {
    $token_data = validateToken($token);
    
    if (!$token_data["success"]) {
        return $token_data;
    }
    
    $user_data = $token_data["data"];
    
    if (empty($allowed_types) || in_array($user_data["type_utilisateur"], $allowed_types)) {
        return ["success" => true, "data" => $user_data];
    } else {
        return ["success" => false, "message" => "Permissions insuffisantes"];
    }
}

// Envoyer une réponse JSON
function sendResponse($status_code, $data) {
    http_response_code($status_code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit();
}