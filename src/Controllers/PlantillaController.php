<?php

//require_once(__DIR__ . '/../model/src/Plantilla.php');

namespace App\Controllers;

use App\Model\Plantilla;

class PlantillaController
{
    public function generateBudget($request)
    {
        $plantilla   = new Plantilla();
        return $plantilla->getGenerarPlantilla($request);
    }
}
