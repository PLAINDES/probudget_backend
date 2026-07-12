<?php

/**
 * Description of HelperJWT
 *
 * @author AJAC
 */

namespace App\Model\Utilitarian;

use Firebase\JWT\JWT;
use stdClass;

class HelperJWT
{
    public static function encode($payload)
    {
        $jwt = JWT::encode($payload, $_ENV['API_SECRET_KEY']);
        return $jwt;
    }

    public static function decode($jwt)
    {
        $resp = new stdClass();
        try {
            $decoded = JWT::decode($jwt, $_ENV['API_SECRET_KEY'], array('HS256'));
            $resp->success = true;
            $resp->data = $decoded;
        } catch (Exception $e) {
            $resp->success = false;
            $resp->message = $e->getMessage();
        }
        return $resp;
    }

    /**
     * Decodifica el payload de un JWT SIN verificar firma.
     * Es seguro usarlo aquí porque el username extraído solo se usa para calcular
     * el SECRET_HASH del refresh — si el username es incorrecto o el token es falso,
     * Cognito simplemente rechazará el refreshSession() en el siguiente paso.
     * Nunca se confía en los datos de este decode para autenticar directamente.
     */
    public static function decodeJwtPayloadUnsafe($jwt)
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        return json_decode($payload, true);
    }
}
