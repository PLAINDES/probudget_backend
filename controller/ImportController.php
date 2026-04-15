<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CursoController
 *
 * @author wheredia
 */

require_once(__DIR__ . '/../model/src/Import.php');
/**
 *
 */

class ImportController
{
    public function getImportType($request)
    {
        $import = new Import($request, $_FILES['file']);
        return $import->getImportType();
    }

    public function getUpdateRegsitro($request)
    {

      //  var_dump(json_decode($request->data));exit;
        $import = new Import($request, "");
        return $import->getUpdateRegsitro(json_decode($request->data));
    }


    public function getInfoTransaccion($request)
    {

    //   //  var_dump(json_decode($request->data));exit;
        $import = new Import($request, "");
        return $import->getInfoTransaccion();
    }

    public function getUpdateProyectoGeneralInsumo($request)
    {

    //   //  var_dump(json_decode($request->data));exit;
        $import = new Import($request, "");
        return $import->getUpdateProyectoGeneralInsumo($request->proyecto_generales_id, json_decode($request->data));
    }
    //json_decode($request->data)
}
