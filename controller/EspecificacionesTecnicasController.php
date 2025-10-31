<?php

require_once(__DIR__ . '/../model/src/EspecificacionesTecnicas.php');

class EspecificacionesTecnicasController
{

    public function getSave($request)
    {   

      $especificacionesTecnicas   = new EspecificacionesTecnicas($request);
      return $especificacionesTecnicas->getSave();
    }
    public function getList($request)
    {   

      $especificacionesTecnicas   = new EspecificacionesTecnicas($request);
      return $especificacionesTecnicas->getListado();
    }

    public function getDelete($request)
    {   
        
      $especificacionesTecnicas   = new EspecificacionesTecnicas($request);
      return $especificacionesTecnicas->getDelete();
    }
    public function getUnitaria($request)
    {           
      $especificacionesTecnicas   = new EspecificacionesTecnicas($request);
      return $especificacionesTecnicas->getUnitaria();
    }
}