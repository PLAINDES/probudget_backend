<?php

//require_once(__DIR__ . '/../model/src/DetalleInsumos.php');

namespace App\Controllers;

use App\Model\DetalleInsumos;

class DetalleInsumosController
{
    public function getSave($request)
    {

        $detalleInsumos   = new DetalleInsumos($request);
        return $detalleInsumos->getSave();
    }

    public function deleteinsumo($request)
    {

        $detalleInsumos   = new DetalleInsumos(false);
        return $detalleInsumos->deleteinsumo($request);
    }

    public function getList($request)
    {
        $detalleInsumos   = new DetalleInsumos($request);
        return $detalleInsumos->getListadoAcumulacion();
    }
}
