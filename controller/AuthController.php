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

require_once(__DIR__ . '/../model/src/auth/Auth.php');
/**
 *
 */

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

        $username = $body['username'];
        $password = $body['password'];

        $auth = new Auth();
        $resp = $auth->login($username, $password);
        return $resp;
    }
}
