<?php

require_once(__DIR__ . '/../model/src/Unidadmedida.php');

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
