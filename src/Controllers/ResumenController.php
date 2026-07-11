<?php

namespace App\Controllers;

use App\Model\Resumen;

class ResumenController
{
    public function save($request)
    {
        $resumen = new Resumen();
        return $resumen->save($request);
    }

    public function probudgetPdfSave($request)
    {
        $resumen = new Resumen();
        return $resumen->probudgetPdfSave($request);
    }

    public function getList($request)
    {
        $resumen = new Resumen();
        return $resumen->getList($request);
    }

    public function getListProbudgetResumen($request)
    {
        $resumen = new Resumen();
        return $resumen->getListProbudgetResumen($request);
    }
}
