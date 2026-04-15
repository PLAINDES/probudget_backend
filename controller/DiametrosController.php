<?php

require_once(__DIR__ . '/../model/src/Diametros.php');

class DiametrosController
{
    public function getList()
    {
        $diametros   = new Diametros();
        return $diametros->getList();
    }
}
