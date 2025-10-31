<?php

require_once(__DIR__ . '/../model/src/Archivo.php');

class ArchivoController
{
    public function getArchivoUploadStorage() {   
        $archivo = new Archivo();
        return $archivo->getArchivoUploadStorage($_POST);
    }
}