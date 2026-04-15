<?php

require_once(__DIR__ . '/../model/src/SubpartidaProyecto.php');

class SubpartidaProyectoController
{
    public function getSave($request)
    {
        $subpartidaProyecto = new SubpartidaProyecto();
        return $subpartidaProyecto->save($request);
    }
}
