<?php

//require_once(__DIR__ . '/../model/src/Proyectogeneral.php');
//require_once(__DIR__ . '/../model/src/Subcategoria.php');

namespace App\Controllers;

use App\Model\Proyectogeneral;
use App\Model\Subcategoria;

class ProyectogeneralController
{
    public function save($request)
    {
        $body = (object) $request->params();
        $proyectogeneral = new Proyectogeneral($body);
        $response = $proyectogeneral->save();
        return $response;
    }

    public function getProyectoGeneralId($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);
        return $proyectogeneral->getProyectoGeneralId();
    }

    public function getListProyectoGeneral($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);
        return $proyectogeneral->getListProyectoGeneral();
    }

    public function getDelete($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);
        return $proyectogeneral->getDelete();
    }
}
