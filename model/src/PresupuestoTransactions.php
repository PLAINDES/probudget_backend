<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of User
 *
 * @author AJAC
 */

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class PresupuestoTransactions extends Mysql
{
    private $_id;
    private $_nro_orden;
    private $_presupuestos_proyecto_generales_id;
    private $_proyecto_generales_id;

    public function __construct($array = [])
    {
        $this->_id = (array_key_exists('id', $array)) ? $array['id'] : "0";
        $this->_presupuestos_proyecto_generales_id = (array_key_exists('presupuestos_proyecto_generales_id', $array)) ? $array['presupuestos_proyecto_generales_id'] : "";
        $this->_nro_orden = (array_key_exists('nro_order', $array)) ? $array['nro_order'] : "";
        $this->_proyecto_generales_id = (array_key_exists('proyecto_generales_id', $array)) ? $array['proyecto_generales_id'] : "";
    }

    public function getMatrixUpdateItems($flag = false)
    {
        try {
            if ($this->_nro_orden) {
                $id = $this->_id;
                $ppgid = $this->_presupuestos_proyecto_generales_id;
                $nro_orden = $this->_nro_orden * 1;
                $iniorder = $nro_orden;
                if ($flag) {
                    $iniorder = $nro_orden - 1;
                }
                $sql = "SET @norder = {$iniorder};
                    UPDATE presupuestos SET nro_orden = (@norder:=@norder+1) WHERE presupuestos_proyecto_generales_id = {$ppgid} 
                    AND nro_orden >= {$nro_orden} AND id <> {$id} AND deleted_at IS NULL ORDER BY nro_orden ASC;";
                self::ex($sql);
            }
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
}
