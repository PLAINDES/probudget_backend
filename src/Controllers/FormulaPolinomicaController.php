<?php

//require_once(__DIR__ . '/../model/src/FormulaPolinomica.php');

namespace App\Controllers;

use App\Model\FormulaPolinomica;

class FormulaPolinomicaController
{
    public function getList($request)
    {
        $formulaPolinomica   = new FormulaPolinomica();
        return $formulaPolinomica->getList($request);
    }

    public function getListIndices($request)
    {
        $formulaPolinomica   = new FormulaPolinomica();
        return $formulaPolinomica->getListIndices($request);
    }

    public function updateSimbol($request)
    {
        $FormulaPolinomica   = new FormulaPolinomica();
        return $FormulaPolinomica->updateSimbol($request);
    }

    public function updateMonomio($request)
    {
        $FormulaPolinomica   = new FormulaPolinomica();
        return $FormulaPolinomica->updateMonomio($request);
    }

    public function updateIndice($request)
    {
        $FormulaPolinomica   = new FormulaPolinomica();
        return $FormulaPolinomica->updateIndice($request);
    }
}
