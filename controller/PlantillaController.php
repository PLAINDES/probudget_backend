<?php

require_once(__DIR__ . '/../model/src/Plantilla.php');

class PlantillaController
{
    public function generateBudget($request)
    {
        $plantilla   = new Plantilla();
        return $plantilla->getGenerarPlantilla($request);
    }
}
