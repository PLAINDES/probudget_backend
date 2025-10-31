<?php

require_once(__DIR__ . '/../model/src/Metrados.php');

class MetradosController
{
    public function getSave($request)
    {
       $metrados = new Metrados($request);        
       return $metrados->getSave();
    }

    public function getListDetalleMetradoId($request)
    {       
        $metrados = new Metrados($request);
        return $metrados->getListMetrado();
    }

    public function getListPresupuestoMetrado($request)
    {
        $metrados   = new Metrados($request);        
        return $metrados->getListPresupuestoMetrado();
        
    }

    public function getDelete($request)
    {
        $metrados   = new Metrados($request);        
        return $metrados->getDelete();
        
    }

}
