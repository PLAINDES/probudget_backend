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

use App\Services\CognitoService;
use App\Model\Auth;
use App\Model\Utilitarian\HelperJWT;
use App\Model\User;
use App\Validators\AuthValidator;

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

        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        if (!$username || !$password) {
            return (object)[
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }

        $cognito = new CognitoService();
        $cognitoResponse = $cognito->authenticateUser($username, $password);

        if (!$cognitoResponse['success']) {
            return (object)[
                'success' => false,
                'message' => $cognitoResponse['message']
            ];
        }

        $user = new User();
        $userData = $user->findOrCreateFromEmail($username);

        if (!$userData['success']) {
            return (object)[
                'success' => false,
                'message' => $userData['message'] ?? 'No se pudo crear el usuario'
            ];
        }

        $_SESSION['usuario'] = $userData['data'];
        $_SESSION['accessToken'] = $cognitoResponse['accessToken'];
        $_SESSION['refreshToken'] = $cognitoResponse['refreshToken'];

        $payload = [
            'id' => $userData['data']->id,
            'email' => $userData['data']->email,
            'exp' => time() + (60 * 60 * 24)
        ];
        $token = HelperJWT::encode($payload);

        return [
            'success' => true,
            'data' => [
                'usuario' => $userData['data'],
                'token' => $token,
                'accessToken' => $cognitoResponse['accessToken']
            ]
        ];
    }

    public function signUp($request)
    {
        try {
            $body = [
                'email' => $request->param('email'),
                'password' => $request->param('password'),
                'accept_policies' => filter_var(
                    $request->param('accept_policies'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ];

            $validation = AuthValidator::validateSignUp($body);

            if (!$validation['success']) {
                error_log(
                    "Validation error: " .
                    print_r($validation['errors'], true)
                );

                return (object) [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            $email = $request->param('email');
            $password = $request->param('password');
            $firstName = $request->param('firstName');
            $lastName = $request->param('lastName');

            $acceptPolicies = filter_var(
                $request->param('accept_policies'),
                FILTER_VALIDATE_BOOLEAN
            );

            $domain = $request->param('domain');
            $roleId = $request->param('roleId');

            $cognito = new CognitoService();

            $cognitoResponse = $cognito->signUp(
                $email,
                $password,
                $firstName,
                $lastName
            );

            if (!$cognitoResponse['success']) {
                error_log(
                    "Cognito Response: " .
                    print_r($cognitoResponse, true)
                );

                return [
                    'success' => false,
                    'message' => $cognitoResponse['message']
                ];
            }

            error_log("ANTES DE DB");

            $user = new User();

            $dbResponse = $user->signUp(
                $email,
                $acceptPolicies,
                $domain,
                $roleId
            );

            error_log(
                "DB Response: " .
                print_r($dbResponse, true)
            );

            if (!$dbResponse['success']) {
                error_log("ERROR DB");

                return (object) [
                    'success' => false,
                    'message' => $dbResponse['message']
                ];
            }

            return (object) [
                'success' => true,
                'message' => 'Usuario registrado. Revisa tu email para confirmación.',
                'userSub' => $cognitoResponse['userSub']
            ];
        } catch (\Throwable $e) {
            error_log("=== ERROR GENERAL SIGNUP ===");

            error_log("Mensaje: " . $e->getMessage());

            error_log("Archivo: " . $e->getFile());

            error_log("Linea: " . $e->getLine());

            error_log("Trace: " . $e->getTraceAsString());

            return (object) [
                'success' => false,
                'message' => 'Error interno del servidor'
            ];
        }
    }

    public function confirmEmail($request)
    {
        try {
            $email = $request->param('email') ?? null;
            $code = $request->param('code') ?? null;

            error_log("Email: " . $email);
            error_log("Code: " . $code);

            $cognito = new CognitoService();
            $cognitoResponse = $cognito->confirmSignUp($email, $code);

            return $cognitoResponse;
        } catch (\Throwable $th) {
            error_log("Error: " . $th->getMessage());

            return (object) [
                'success' => false,
                'message' => 'Error interno del servidor'
            ];
        }
    }

    public function logout($request)
    {
        session_destroy();
        return [
            'success' => true,
            'message' => 'Sesion cerrada'
        ];
    }
}
