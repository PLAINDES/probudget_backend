<?php

require_once(__DIR__ . '/../model/src/CopiarPegarPresupuesto.php');

class CopiarPegarPresupuestoController
{
    public function createItem($request)
    {
        $copiarPegarPresupuesto   = new CopiarPegarPresupuesto();
        return $copiarPegarPresupuesto->createItem($request);
    }
}
