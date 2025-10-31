<?php

require_once(__DIR__ . '/../model/src/Insumos.php');

class InsumosController
{
    public function getList($request)
    {   
      $insumos   = new Insumos();
      return $insumos->getListInsumo($request->proyectos_generales_id);
    }

    public function getIndicesUnificados()
    {   
      $insumos   = new Insumos();
      return $insumos->getIndicesUnificados();
    }

    public function renameInsumo($request)
    {   
      $insumos   = new Insumos();
      return $insumos->renameInsumo($request);
    }
}