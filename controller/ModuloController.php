<?php

require_once(__DIR__ . '/../model/src/Modulo.php');

class ModuloController
{
    /**
     * Crear o actualizar un módulo
     */
    public function getSave($request)
    {
        $modulo = new Modulo($request);
        return $modulo->getSave();
    }

    /**
     * Listar módulos por presupuesto
     */
    public function getListByPresupuesto($request)
    {
        $modulo = new Modulo($request);
        return $modulo->getByPresupuesto($request->presupuesto_id);
    }

    /**
     * Obtener un módulo por ID
     */
    public function getById($request)
    {
        return Modulo::getById($request->id);
    }

    /**
     * Listar todos los módulos
     */
    public function getList($request)
    {
        return Modulo::getAll();
    }

    /**
     * Eliminar módulo
     */
    public function getDelete($request)
    {
        return Modulo::deleteModulo($request->id);
    }
}
