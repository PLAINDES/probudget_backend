<?php

//require_once(__DIR__ . '/../model/src/SubpartidaProyecto.php');

namespace App\Controllers;

use App\Model\SubpartidaProyecto;

class SubpartidaProyectoController
{
    public function getSave($request)
    {
        $subpartidaProyecto = new SubpartidaProyecto();
        return $subpartidaProyecto->save($request);
    }
}
