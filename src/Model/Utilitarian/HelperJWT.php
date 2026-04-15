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
}
