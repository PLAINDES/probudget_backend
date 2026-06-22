<?php

namespace App\Controllers;

use App\Model\Seguros;

class SegurosController
{
    public function save($request)
    {
        $seguros = new Seguros();
        return $seguros->save($request);
    }

    public function getList($request)
    {
        $seguros = new Seguros();
        return $seguros->getList($request);
    }

    public function delete($request)
    {
        $seguros = new Seguros($request);
        return $seguros->delete();
    }

    public function edit($request)
    {
        $seguros = new Seguros($request);
        return $seguros->edit();
    }
}
