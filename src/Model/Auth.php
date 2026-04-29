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
            error_log("LOGIN ATTEMPT - Username: " . ($username ?? 'null'));

            if (empty($username) || empty($password)) {
                $resp->success = false;
                $resp->message = 'Username and password are required';
                error_log("LOGIN FAILED - Missing credentials");
                return $resp;
            }

            $sql = 'SELECT id,email,first_name,last_name,picture,password, roleId FROM users WHERE email = :username';
            $rs = self::fetchObj($sql, compact('username'));

            if ($rs) {
                error_log("USER FOUND - Email: " . $rs->email);
                $passwordcrypt = FG::crypt($password);
                if ($rs->password == $passwordcrypt) {
                    unset($rs->password);
                    $resp->success = true;
                    $resp->message = 'Binevenido a probudget';
                    $resp->data = $rs;
                    error_log("LOGIN SUCCESS - User ID: " . $rs->id);
                    return $resp;
                }
                $resp->success = false;
                $resp->message = 'Password incorrecto';
                error_log("LOGIN FAILED - Wrong password for: " . $username);
                return $resp;  // ← AGREGAR RETURN
            } else {
                $resp->success = false;
                $resp->message = 'User does not exist';
                error_log("LOGIN FAILED - User not found: " . $username);
                return $resp;  // ← AGREGAR RETURN
            }
        } catch (\Throwable $th) {
            $resp->success = false;
            $resp->message = $th->getMessage();
            error_log("LOGIN ERROR - Exception: " . $th->getMessage());
            return $resp;
        }
    }
}
