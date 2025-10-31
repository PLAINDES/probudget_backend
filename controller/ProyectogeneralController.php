<?php

require_once(__DIR__ . '/../model/src/Proyectogeneral.php');
require_once(__DIR__ . '/../model/src/Subcategoria.php');
class ProyectogeneralController     
{
    public function getSave($request)
    {   
        // $array = [
        //     "id" =>  $request->id,
        //     "users_id" =>  $request->users_id,
        //     "proyecto" =>  $request->proyecto,
        //     "cliente" =>  $request->cliente,
        //     "direccion" =>  $request->direccion,
        //     "distrito" =>  $request->distrito,
        //     "provincia" =>  $request->provincia,
        //     "departamento" =>  $request->departamento,
        //     "pais" =>  $request->pais,
        //     "area_geografica" =>  $request->area_geografica,
        //     "fecha_base" =>  $request->fecha_base,
        //     "jornada_laboral" =>  $request->jornada_laboral,
        //     "moneda" =>  $request->moneda,            
        //     "subcategorias" =>  $request->subcategorias,            
        // ];
        
        $proyectogeneral   = new Proyectogeneral($request);        
       return $proyectogeneral->getSave();
    }

    public function getProyectoGeneralId($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);        
        return $proyectogeneral->getProyectoGeneralId();        
    }

    public function getListProyectoGeneral($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);        
        return $proyectogeneral->getListProyectoGeneral();        
    }

    public function getDelete($request)
    {
        $proyectogeneral   = new Proyectogeneral($request);        
        return $proyectogeneral->getDelete();        
    }

    
    
    
}