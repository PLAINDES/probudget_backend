<?php

namespace App\Middleware;

use App\Model\Utilitarian\HelperJWT;

class CognitoTokenMiddleware
{
    public static function validate()
    {
        error_log("=== Middleware validate() ===");
        $token = self::getTokenFromCookie();

        if (!$token) {
            http_response_code(401);

            die(json_encode([
                'success' => false,
                'message' => 'Token no proporcionado'
            ]));
        }

        error_log("Token recibido: " . $token);

        try {
            $payload = HelperJWT::decode($token);
            error_log("Token decodificado: " . json_encode($payload));

            if (!$payload) {
                throw new \Exception('Token inválido');
            }


            return $payload;
        } catch (\Throwable $th) {
            error_log("Error: " . $th->getMessage());
            http_response_code(401);

            die(json_encode([
                'success' => false,
                'message' => 'Token inválido'
            ]));
        }
    }

    private static function getTokenFromCookie()
    {
        return $_COOKIE['token'] ?? null;
    }
}
