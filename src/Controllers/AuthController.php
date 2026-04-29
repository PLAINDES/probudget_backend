<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CursoController
 *
 * @author PLATAFORMA
 */

//require_once(__DIR__ . '/../model/src/auth/Auth.php');
/**
 *
 */

namespace App\Controllers;

use App\Model\Auth;
use App\Model\Utilitarian\HelperJWT;

class AuthController
{
    public function checkToken($token)
    {
        $auth = new Auth();
        return $auth->checkToken($token);
    }

    public function login($request)
    {
        $body = json_decode(file_get_contents('php://input'), true);

        error_log("CONTROLLER LOGIN - Body: " . json_encode($body));

        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        if (!$username || !$password) {
            error_log("CONTROLLER LOGIN - Missing credentials");
            return (object)[
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }

        $auth = new Auth();
        $resp = $auth->login($username, $password);

        error_log("CONTROLLER LOGIN - Model response: " . json_encode($resp));

        if ($resp->success) {
            $user = $resp->data;
            $payload = [
                'id' => $user->id,
                'email' => $user->email,
                'roleId' => $user->roleId,
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24)
            ];
            $token = HelperJWT::encode($payload);
            error_log("CONTROLLER LOGIN - Token generated for: " . $user->email);

            return (object)[
                'success' => true,
                'data' => (object)[
                    'usuario' => $user,
                    'token' => $token
                ]
            ];
        }
        error_log("CONTROLLER LOGIN - Failed: " . $resp->message);
        return $resp;
    }
}
