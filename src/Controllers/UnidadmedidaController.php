<?php

//require_once(__DIR__ . '/../model/src/Unidadmedida.php');

namespace App\Controllers;

use App\Model\Unidadmedida;

class UnidadmedidaController
{
    public function getList()
    {
        $unidadmedida   = new Unidadmedida();
        return $unidadmedida->getListUnidadMedida();
    }

    public function saveData($request)
    {
        $unidadmedida   = new Unidadmedida();
        return $unidadmedida->saveData($request);
    }
}
