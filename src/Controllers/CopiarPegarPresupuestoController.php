<?php

//require_once(__DIR__ . '/../model/src/CopiarPegarPresupuesto.php');

namespace App\Controllers;

use App\Model\CopiarPegarPresupuesto;

class CopiarPegarPresupuestoController
{
    public function createItem($request)
    {
        $copiarPegarPresupuesto   = new CopiarPegarPresupuesto();
        return $copiarPegarPresupuesto->createItem($request);
    }
}
