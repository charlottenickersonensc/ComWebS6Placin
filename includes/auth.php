<?php
require_once 'db.php';

class Auth {
    private $conn;
    private $table_name = "Utilisateurs";
    private $secret_key = "your_secret_key_for_jwt"; // Change this in production

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Login user and return JWT token
     * @param string $email User email
     * @param string $password User password
     * @return array token or error message
     */
    public function login($email, $password) {
        // Check if email exists and password is correct
        $query = "SELECT id, nom, prenom, email, mot_de_passe, type_utilisateur 
                FROM " . $this->table_name . " 
                WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $count = $stmt->rowCount();
        
        if($count > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $row['id'];
            $nom = $row['nom'];
            $prenom = $row['prenom'];
            $email = $row['email'];
            $password_hash = $row['mot_de_passe'];
            $type_utilisateur = $row['type_utilisateur'];
            
            // Verify the password (using PHP's password_hash)
            if(password_verify($password, $password_hash)) {
                // Generate JWT token
                $token = $this->generateToken($id, $nom, $prenom, $email, $type_utilisateur);
                
                return [
                    "success" => true,
                    "message" => "Successful login.",
                    "id" => $id,
                    "nom" => $nom,
                    "prenom" => $prenom,
                    "email" => $email,
                    "type_utilisateur" => $type_utilisateur,
                    "token" => $token
                ];
            } else {
                return ["success" => false, "message" => "Invalid credentials."];
            }
        } else {
            return ["success" => false, "message" => "Invalid credentials."];
        }
    }

    /**
     * Generate JWT token
     * @param int $id User ID
     * @param string $nom User last name
     * @param string $prenom User first name
     * @param string $email User email
     * @param string $type_utilisateur User type
     * @return string JWT token
     */
    private function generateToken($id, $nom, $prenom, $email, $type_utilisateur) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // Valid for 1 hour
        
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
        
        // In a production environment, use a proper JWT library
        // This is a simple JWT implementation for demonstration
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $this->secret_key, true));
        
        return "$header.$payload.$signature";
    }

    /**
     * Validate JWT token
     * @param string $token JWT token
     * @return array User data or error message
     */
    public function validateToken($token) {
        // Simple JWT validation for demonstration
        // In production, use a proper JWT library
        $parts = explode('.', $token);
        
        if (count($parts) != 3) {
            return ["success" => false, "message" => "Invalid token format"];
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $valid_signature = base64_encode(hash_hmac('sha256', "$header.$payload", $this->secret_key, true));
        
        if ($signature !== $valid_signature) {
            return ["success" => false, "message" => "Invalid token signature"];
        }
        
        // Check if token is expired
        $payload_data = json_decode(base64_decode($payload), true);
        
        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
            return ["success" => false, "message" => "Token has expired"];
        }
        
        return [
            "success" => true,
            "data" => $payload_data['data']
        ];
    }

    /**
     * Check if user has necessary permissions
     * @param string $token JWT token
     * @param array $allowed_types Allowed user types
     * @return array User data or error message
     */
    public function checkPermissions($token, $allowed_types = []) {
        $token_data = $this->validateToken($token);
        
        if (!$token_data["success"]) {
            return $token_data;
        }
        
        $user_data = $token_data["data"];
        
        if (empty($allowed_types) || in_array($user_data["type_utilisateur"], $allowed_types)) {
            return ["success" => true, "data" => $user_data];
        } else {
            return ["success" => false, "message" => "Insufficient permissions"];
        }
    }
}