<?php

namespace App\Controllers;

use App\Model\GastosFinancieros;

class GastosFinancierosController
{
    public function __construct()
    {
        $this->gastosFinancieros = new GastosFinancieros();
    }

    public function save($request)
    {
        return $this->gastosFinancieros->save($request);
    }

    public function getList($request)
    {
        return $this->gastosFinancieros->getList($request);
    }
}
