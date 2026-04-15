<?php

require_once(__DIR__ . '/../model/src/GastosGenerales.php');

class GastosGeneralesController
{
    public function getSave($request)
    {
        $gastosGenerales   = new GastosGenerales($request);
        return $gastosGenerales->getSave();
    }

    public function getListGastosGenerales($request)
    {
        $gastosGenerales   = new GastosGenerales($request);
        return $gastosGenerales->getListGastosGenerales();
    }

    public function getDelete($request)
    {
        $gastosGenerales   = new GastosGenerales($request);
        return $gastosGenerales->getDelete();
    }

    public function getSaveTotalGeneralExpense($request)
    {
        $gastosGenerales   = new GastosGenerales(false);
        return $gastosGenerales->getSaveTotalGeneralExpense($request);
    }

    public function changeDisaggregated($request)
    {
        $gastosGenerales   = new GastosGenerales(false);
        return $gastosGenerales->changeDisaggregated($request);
    }
}
