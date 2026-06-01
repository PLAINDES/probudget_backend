<?php

namespace App\Validators;

use Respect\Validation\Validator as v;

class GastosFinancierosValidator
{
    public static function validateSave($data)
    {
        $validator = v::key(
            'proyectoGeneralesId',
            v::intVal()->positive()
        )
        ->key(
            'tipoGarantiaId',
            v::intVal()->positive()
        )
        ->key(
            'tasa',
            v::numericVal()->min(0),
            false
        )
        ->key(
            'comisionBanco',
            v::numericVal()->min(0),
            false
        )
        ->key(
            'periodoMeses',
            v::numericVal()->min(0),
            false
        )
        ->key(
            'garantiaBancaria',
            v::numericVal()->min(0),
            false
        );

        try {
            $validator->assert((array) $data);

            return [
                'success' => true
            ];
        } catch (\Throwable $e) {
            error_log("Error validando: " . $e->getMessage());
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }
}
