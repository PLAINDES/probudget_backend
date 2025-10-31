<?php

require_once(__DIR__ . '/../model/src/Proyectogeneral.php');
require_once(__DIR__ . '/../model/src/Presupuesto.php');
require_once(__DIR__ . '/../model/src/RecalculoPrespuesto.php');
require_once(__DIR__ . '/../model/src/PresupuestosTitulos.php');

class PresupuestoController     
{
    public function getSave($request)
    {   

       $presupuesto   = new Presupuesto($request);        
       return $presupuesto->getSave();
    }    

    public function getDelete($request){
       $presupuesto   = new Presupuesto($request);
       return $presupuesto->getDelete();
    }    

    public function getListPresupuesto($request)
    {     
        $presupuesto   = new Presupuesto($request);        
        return $presupuesto->getListPresupuesto();
    }

    public function getListMetrado($request)
    {
        $presupuesto   = new Presupuesto($request);        
        return $presupuesto->getListMetrado();
        
    }

    public function getMatrix($request){
        $value = [
            "id" => $request->id,
            // "partidas_id" => $request->partidas_id,
            "item_partida" => $request->item_partida,
            // "descripcion" => $request->descripcion,         
            // "proyecto_generales_id" => $request->proyecto_generales_id,           
            "presupuestos_proyecto_generales_id" => $request->presupuestos_proyecto_generales_id,
            "nro_orden" => $request->nro_orden,
            "tipo" => $request->tipo, 
        ];

       $presupuesto   = new Presupuesto($value);        
       return $presupuesto->getMatrixCutPaste();
    }

    public function getPiePresupuesto($request)
    {
        $presupuesto   = new RecalculoPrespuesto();        
        return $presupuesto->getPiePresupuesto(['id'=>$request->id]);
        
    }

    public function getListTitle($request)
    {
        $presupuestosTitulos   = new PresupuestosTitulos([]);        
        return $presupuestosTitulos->getListTitle($request);
    }

    public function updateBudgetFooter($request)
    {
        $presupuestos = new Presupuesto(false);
        return $presupuestos->updateBudgetFooter($request);
    }
    

    public function updateDescription($request)
    {
        $presupuestos = new Presupuesto(false);
        return $presupuestos->updateDescription($request);
    }
    
}