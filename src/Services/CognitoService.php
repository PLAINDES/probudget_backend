<?php

namespace App\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;

class CognitoService
{
    private $client;
    private $clientId;
    private $clientSecret;
    private $userPoolId;
    private $region;

    public function __construct()
    {
        $this->region = $_ENV['REGION_AWS'];
        $this->clientId = $_ENV['COGNITO_CLIENT_ID'];
        $this->clientSecret = $_ENV['COGNITO_CLIENT_SECRET'];
        $this->userPoolId = $_ENV['COGNITO_USER_POOL_ID'];

        $this->client = new CognitoIdentityProviderClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
              'key' => $_ENV['AWS_ACCESS_KEY_S3'],
              'secret' => $_ENV['AWS_SECRET_KEY_S3']
            ]
        ]);
    }

    /*
    Validar token de acceso de Cognito
     */
    public function validateToken($token)
    {
        try {
            $result = $this->client->getUser([
              'AccessToken' => $token
            ]);

            return [
              'success' => true,
              'message' => 'Token valido',
              'data' => $result
            ];
        } catch (AwsException $e) {
            error_log($e->getAwsErrorMessage());
            error_log($e->getAwsErrorCode());

            return [
              'success' => false,
              'message' => $e->parseAwsError($e->getAwsErrorCode()) ?? 'Token invalido',
              'data' => []
            ];
        }
    }

    /**
    * Registrar usuario en Cognito
    */

    public function signUp($email, $password, $firstName = '', $lastName = '')
    {
        try {
            $userAttributes = [
                [
                    'Name' => 'email',
                    'Value' => $email
                ]
            ];

            if (!empty($firstName)) {
                $userAttributes[] = [
                    'Name' => 'given_name',
                    'Value' => $firstName
                ];
            }

            if (!empty($lastName)) {
                $userAttributes[] = [
                    'Name' => 'family_name',
                    'Value' => $lastName
                ];
            }

            $result = $this->client->signUp([
                'ClientId' => $this->clientId,
                'SecretHash' => $this->calculateSecretHash($email),
                'Username' => $email,
                'Password' => $password,
                'UserAttributes' => $userAttributes
            ]);

            return [
                'success' => true,
                'message' => 'Usuario registrado. Verifica tu email.',
                'userSub' => $result['UserSub']
            ];
        } catch (AwsException $e) {
            error_log($e->getAwsErrorMessage());
            error_log($e->getAwsErrorCode());

            return [
                'success' => false,
                'message' => $this->parseAwsError(
                    $e->getAwsErrorCode()
                )
            ];
        }
    }

    /**
    * Confirmar email del usuario
    */
    public function confirmSignUp($email, $confirmationCode)
    {
        try {
            $this->client->confirmSignUp([
                'ClientId' => $this->clientId,
                'Username' => $email,
                'ConfirmationCode' => $confirmationCode,
                'SecretHash' => $this->calculateSecretHash($email)
            ]);

            return [
                'success' => true,
                'message' => 'Email confirmado'
            ];
        } catch (AwsException $e) {
            error_log($e->getAwsErrorMessage());
            error_log($e->getAwsErrorCode());

            return [
                'success' => false,
                'message' => $this->parseAwsError(
                    $e->getAwsErrorCode()
                )
            ];
        }
    }

    /**
    * Iniciar sesión con email/password
    */
    public function authenticateUser($email, $password)
    {
        try {
            $result = $this->client->adminInitiateAuth([
              'UserPoolId' => $this->userPoolId,
                'ClientId' => $this->clientId,
                'AuthFlow' => 'ADMIN_USER_PASSWORD_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $email,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $this->calculateSecretHash($email)
                ]
            ]);

            return [
                'success' => true,
                'accessToken' => $result['AuthenticationResult']['AccessToken'],
                'idToken' => $result['AuthenticationResult']['IdToken'],
                'refreshToken' => $result['AuthenticationResult']['RefreshToken'],
            ];
        } catch (AwsException $e) {
            error_log($e->getAwsErrorMessage());
            error_log($e->getAwsErrorCode());

            return [
              'success' => false,
              'message' => $this->parseAwsError($e->getAwsErrorCode())
            ];
        }
    }

    /**
    * Refrescar token de acceso
    */
    public function refreshToken($refreshToken)
    {
        try {
            $result = $this->client->adminInitiateAuth([
              'UserPoolId' => $this->userPoolId,
                'ClientId' => $this->clientId,
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'AuthParameters' => [
                    'REFRESH_TOKEN' => $refreshToken,
                    'SECRET_HASH' => $this->calculateSecretHash($refreshToken)
                ]
            ]);

            return [
                'success' => true,
                'accessToken' => $result['AuthenticationResult']['AccessToken'],
                'idToken' => $result['AuthenticationResult']['IdToken'],
            ];
        } catch (AwsException $e) {
            error_log($e->getAwsErrorMessage());
            error_log($e->getAwsErrorCode());

            return [
              'success' => false,
              'message' => $e->getMessage()
            ];
        }
    }

    private function parseAwsError($error)
    {
        $errorMap = [
            'UsernameExistsException' => 'El usuario ya existe',
            'InvalidPasswordException' => 'La contraseña no cumple los requisitos',
            'UserNotFoundException' => 'Usuario no encontrado',
            'NotAuthorizedException' => 'Credenciales inválidas',
            'UserNotConfirmedException' => 'Usuario no confirmado',
            'PasswordResetRequiredException' => 'La contraseña debe ser cambiada',
            'TooManyRequestsException' => 'Demasiadas solicitudes',
            'CodeMismatchException' => 'El código de confirmación es incorrecto',
            'ExpiredCodeException' => 'El código de confirmación ha expirado',
            'UsernameExistsException' => 'El usuario ya existe',
            'CodeMismatchException' => 'El código de confirmación es incorrecto o ha expirado',
        ];

        return $errorMap[$error] ?? $error;
    }

    private function calculateSecretHash($username)
    {
        return base64_encode(
            hash_hmac(
                'sha256',
                $username . $this->clientId,
                $this->clientSecret,
                true
            )
        );
    }
}
