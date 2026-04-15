<?php

//require_once(__DIR__ . '/../model/src/Subcategoria.php');

namespace App\Controllers;

use App\Model\Subcategoria;

class SubcategoriaController
{
    public function getList()
    {
        $subcategoria   = new Subcategoria();
        return $subcategoria->getList();
    }

    public function getSave($request)
    {
        $subcategoria   = new Subcategoria(['id' => $request->id,'descripcion' => $request->descripcion,]);
        return $subcategoria->getSave();
    }

    public function getListFilter($request)
    {
        $subcategoria   = new Subcategoria(['id' => $request->id]);
        return $subcategoria->getListFilter();
    }

    public function getSubPresupuestoParcial($request)
    {
        $subcategoria   = new Subcategoria(['id' => $request->id]);
        return $subcategoria->getSubPresupuestoParcial();
    }

    public function getDelete($request)
    {
        $subcategoria   = new Subcategoria(['id' => $request->id]);
        return $subcategoria->getDelete();
    }
}
