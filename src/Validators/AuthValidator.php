<?php

namespace App\Validators;

use Respect\Validation\Validator as v;

class AuthValidator
{
    public static function validateSignUp($data)
    {
        $passwordRule = v::stringType()
            ->length(8, null)
            ->regex('/[A-Z]/')
            ->regex('/[a-z]/')
            ->regex('/[0-9]/')
            ->regex('/[\W_]/');

        $validator = v::key(
            'email',
            v::notEmpty()->email()
        )
        ->key(
            'password',
            v::notEmpty()->setName(
                'La contraseña'
            )->addRule($passwordRule)
        )
        ->key(
            'accept_policies',
            v::trueVal()
        );

        try {
            $validator->assert($data);

            return [
                'success' => true
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors' => [
                    'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, 
                    un número y un símbolo.'
                ]
            ];
        }
    }
}
