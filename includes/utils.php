<?php
/**
 * Utility functions for API responses
 */
class Utils {
    /**
     * Send JSON response
     * @param int $status_code HTTP status code
     * @param array $data Response data
     */
    public static function sendResponse($status_code, $data) {
        http_response_code($status_code);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit();
    }
    
    /**
     * Get authorization token from headers
     * @return string|null Bearer token or null
     */
    public static function getAuthorizationToken() {
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
}