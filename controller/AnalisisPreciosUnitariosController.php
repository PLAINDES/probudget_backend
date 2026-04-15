<?php

require_once(__DIR__ . '/../model/src/AnalisisPreciosUnitarios.php');
require_once(__DIR__ . '/../model/src/AnalisisPreciosUnitariosDetalles.php');

class AnalisisPreciosUnitariosController
{
    public function getCabSave($request)
    {

        $analisisPreciosUnitarios   = new AnalisisPreciosUnitarios($request);
        return $analisisPreciosUnitarios->getSave();
    }
    public function getDetailsbSave($request)
    {

        $AnalisisPreciosUnitariosDetalles   = new AnalisisPreciosUnitariosDetalles($request);
        return $AnalisisPreciosUnitariosDetalles->getSave();
    }
    public function getDetailsbDelete($request)
    {

        $AnalisisPreciosUnitariosDetalles   = new AnalisisPreciosUnitariosDetalles($request);
        return $AnalisisPreciosUnitariosDetalles->getDelete();
    }

    public function getListAnalisisPreciosUnitarios($request)
    {
        $AnalisisPreciosUnitarios   = new AnalisisPreciosUnitarios($request);
        return $AnalisisPreciosUnitarios->getListAnalisisPreciosUnitarios();
    }
}
