<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DowAuth
 *
 * @author wheredia
 */

//require_once(__DIR__ . '/../../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../../utilitarian/FG.php');

namespace App\Model;

use App\Model\Persistence\Mysql;
use App\Model\Utilitarian\FG;
use Firebase\JWT\JWT;
use stdClass;

/**
 *
 */
class Auth extends Mysql // Cambiado de Mariadb a Mysql
{
    public function checkToken($token)
    {
        $resp = array();
        try {
            $secretkey = $_ENV['API_SECRET_KEY'];
            $realAccesskey = $_ENV['API_ACCESS_KEY'];
            JWT::$leeway = 5;
            $decoded = JWT::decode($token, $secretkey, array('HS256'));
            $accesskey = $decoded->key;
            if ($accesskey == $realAccesskey) {
                $resp['success'] = true;
                $resp['message'] = 'Token valido';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Las credenciales no coinciden, validar token.';
            }
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
        }
        return $resp;
    }

    public function login($username, $password)
    {
        $resp = new stdClass();
        try {
            if (empty($username) || empty($password)) {
                $resp->success = false;
                $resp->message = 'Username and password are required';
                return $resp;
            }

            $sql = 'SELECT id,email,first_name,last_name,picture,password, roleId FROM users WHERE email = :username';
            $rs = self::fetchObj($sql, compact('username'));
            if ($rs) {
                $passwordcrypt = FG::crypt($password);
                if ($rs->password == $passwordcrypt) {
                    unset($rs->password);
                    $resp->success = true;
                    $resp->message = 'Bineveninido a probudjet';
                    $resp->data = $rs;
                    return $resp;
                }
                $resp->success = false;
                $resp->message = 'Password incorrecto';
            } else {
                $resp->success = false;
                $resp->message = 'User does not exist';
            }
        } catch (\Throwable $th) {
            $resp->success = false;
            $resp->message = $th->getMessage();
        }
        return $resp;
    }
}
