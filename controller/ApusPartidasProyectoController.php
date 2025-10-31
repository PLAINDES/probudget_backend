<?php

require_once(__DIR__ . '/../model/src/ApusPartidasProyecto.php');

class ApusPartidasProyectoController
{
    public function getSave($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getSave($request);
    }

    public function getSaveNewinsumo($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getSaveNewinsumo($request);
    }

    public function getCabSave($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getCabSave($request);
    }

    public function getDetailSave($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getDetailSave($request);
    }

    public function getListApus($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getListApus($request);
    }

    public function getListAllApus($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getListAllApus($request);
    }

    public function getListSubpartidas($request)
    {
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getListSubpartidas($request);
    }

    public function getDetailsbDelete($request)
    {   
       
       $apusPartidasProyecto   = new ApusPartidasProyecto();
       return $apusPartidasProyecto->getDelete($request);
    }
}
