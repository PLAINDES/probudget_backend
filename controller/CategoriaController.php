<?php

require_once(__DIR__ . '/../model/src/Categoria.php');

class CategoriaController
{
    
    public function getList()
    {   
        $categoria   = new Categoria();
        return $categoria->getList();
    }
    
    public function getListProyectoGeneral($request)
    {   
        $categoria   = new Categoria();
        return $categoria->getListProyectoGeneral($request->categoriaId);
    }

    public function saveCategory($request)
    {
        $categoria   = new Categoria();
        return $categoria->getSave($request);
    }

    public function setCategoryToBudget($request)
    {
        $categoria   = new Categoria();
        return $categoria->setCategoryToBudget($request);
    }

    public function deleteCategory($request)
    {   
        $categoria   = new Categoria();
        return $categoria->getDelete($request);
    }

}