<?php

//require_once(__DIR__ . '/../model/src/Partidas.php');

namespace App\Controllers;

use App\Model\Partidas;

class PartidasController
{
    public function getList($request)
    {
        $partidas   = new Partidas();
        return $partidas->getListPartida($request);
    }
}
