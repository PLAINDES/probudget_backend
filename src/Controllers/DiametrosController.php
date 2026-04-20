<?php

//require_once(__DIR__ . '/../model/src/Diametros.php');

namespace App\Controllers;

use App\Model\Diametros;

class DiametrosController
{
    public function getList()
    {
        $diametros   = new Diametros();
        return $diametros->getList();
    }
}
