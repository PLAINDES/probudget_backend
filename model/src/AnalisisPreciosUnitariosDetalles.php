<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../src/AnalisisPreciosUnitariosDetalles.php');


class AnalisisPreciosUnitariosDetalles extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_unidad_medidas_id;
    public function getunidad_medidas_id()
    {
        return $this->_unidad_medidas_id;
    }
    private $_cuadrilla;
    public function getcuadrilla()
    {
        return $this->_cuadrilla;
    }
    private $_subpresupuestos_id;
    private $_cantidad;
    public function getcantidad()
    {
        $sql_ppg = "SELECT id, presupuestos_id,rendimiento  FROM analisis_precios_unitarios WHERE id = :id";
        $ppg = self::fetchObj($sql_ppg, ['id' => $this->_analisis_precios_unitarios_id]);

        $sql_presupuesto = "SELECT id, proyecto_generales_id, metrado  FROM presupuestos WHERE id = :id";
        $presupuesto = self::fetchObj($sql_presupuesto, ['id' => $ppg->presupuestos_id]);

        $sql_proyecto_general = "SELECT id, jornada_laboral FROM proyecto_generales WHERE id = :id";
        $proyecto_general = self::fetchObj($sql_proyecto_general, ['id' => $presupuesto->proyecto_generales_id]);

        $this->_cantidad = $this->_cuadrilla *  $proyecto_general->jornada_laboral / $ppg->rendimiento;

        if ($this->_cantidad) {
            if (is_nan($this->_cantidad)) {
                $this->_cantidad =  0.00;
            } else {
                return $this->_cantidad;
            }
        } else {
            $this->_cantidad =  0.00;
        }

        return $this->_cantidad;
    }
    private $_precio;
    public function getprecio()
    {
        if ($this->_tipo_insumo == 'EQ') {
            $sql_unidad_medida = "SELECT id, descripcion, apu_cantidad FROM unidad_medidas WHERE id = :id";
            $unidad_medida = self::fetchObj($sql_unidad_medida, ['id' => $this->_unidad_medidas_id]);

            if ($unidad_medida->apu_cantidad == 1) {
                $sql_ppg = "SELECT mano_obra  FROM analisis_precios_unitarios WHERE id = :id";
                $ppg = self::fetchObj($sql_ppg, ['id' => $this->_analisis_precios_unitarios_id]);
                $this->_precio = number_format($ppg->mano_obra, 2, '.', '');
            }
        }

        if ($this->_precio) {
            return  $this->_precio;
        } else {
            return 0.00;
        }
        // return $this->_precio; 
    }
    private $_parcial;
    public function getparcial()
    {

        $this->_parcial = $this->_cantidad * $this->_precio;

        if ($this->_parcial) {
            if (is_nan($this->_parcial)) {
                $this->_parcial =  0.00;
            } else {
                return $this->_parcial;
            }
        } else {
            $this->_parcial = 0.00;
        }
        return  $this->_parcial;
    }
    private $_analisis_precios_unitarios_id;
    public function getanalisis_precios_unitarios_id()
    {
        return $this->_analisis_precios_unitarios_id;
    }
    private $_monto_parcial_ppto;
    public function getmonto_parcial_ppto()
    {
        $sql_ppg = "SELECT presupuestos_id  FROM analisis_precios_unitarios WHERE id = :id";
        $ppg = self::fetchObj($sql_ppg, ['id' => $this->_analisis_precios_unitarios_id]);

        $sql_presupuesto = "SELECT id, proyecto_generales_id, metrado  FROM presupuestos WHERE id = :id";
        $presupuesto = self::fetchObj($sql_presupuesto, ['id' => $ppg->presupuestos_id]);

        $this->_monto_parcial_ppto = $presupuesto->metrado * $this->_parcial;
        return number_format($this->_monto_parcial_ppto, 2, '.', '');
    }
    private $_insumos_id;
    public function getinsumos_id()
    {
        if ($this->_insumos_id) {
            // $this->_unidad_medidas_id = FG::validateMatrizKey('unidad_medidas_id',$array); // (array_key_exists('unidad_medidas_id',$array))?$array['unidad_medidas_id']:"" ;        
            $sql = 'SELECT id,precio FROM insumos WHERE id=:insumos_id  ';
            $insumos = self::fetchObj($sql, ["insumos_id" => $this->_insumos_id]);
            if ($insumos) {

                return (float)$insumos->precio;
            }
        }
        return 0.00;
    }
    private $_tipo_insumo;
    public function gettipo_insumo()
    {
        return $this->_tipo_insumo;
    }
    private $_values;
    public function getValue()
    {
        return $this->_values;
    }

    public function __construct($request = NULL)
    {

        if ($request) {
            $column = [
                'id',
                'unidad_medidas_id',
                'cuadrilla',
                'cantidad',
                'precio',
                'subpresupuestos_id',
                'parcial',
                'analisis_precios_unitarios_id',
                'monto_parcial_ppto',
                'insumos_id',
                'tipo_insumo',
            ];

            foreach ($column as  $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }
        }
    }

    public function getDelete()
    {
        try {
            $sql = 'SELECT COUNT(id) AS idapud FROM analisis_precios_unitarios_detalles 
                    WHERE id = :id  ';
            $apud = self::fetchObj($sql, ['id' => $this->_id]);
            if (count($apud)) {
                self::delete('analisis_precios_unitarios_detalles', ['id' => $this->_id]);
                //self::update('presupuestos',['deleted_at'=>date("Y-m-d H:i:s")  ],['id'=>$this->_id]);
                $resp['success'] = true;
                $resp['message'] = 'Se elimino el registro';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'No se puede eliminar el registro';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar el registro';
            return $resp;
        }
    }


    public function getSave()
    {
        try {

            if ($this->_cuadrilla == 0 && $this->_cantidad == 0 && $this->_precio == 0) {
                $this->_values["precio"] = number_format($this->getinsumos_id(), 2, '.', '');
                $this->_values["cantidad"] = number_format(0.00);
                $this->_values["parcial"] = number_format(0.00);
                $this->_values["monto_parcial_ppto"] = number_format(0.00);
            } else {
                $this->_values["precio"] = number_format($this->getprecio(), 2, '.', '');
                $this->_values["cantidad"] = ($this->_cantidad) ? number_format($this->_cantidad, 2, '.', '') : number_format($this->getcantidad(), 2, '.', '');
                $this->_values["parcial"] = number_format($this->getparcial(), 2, '.', '');
                $this->_values["monto_parcial_ppto"] = number_format($this->getmonto_parcial_ppto(), 2, '.', '');
            }

            //   var_dump($this->_values);exit;          

            if ($this->_id) {
                $sql = 'SELECT id,analisis_precios_unitarios_id FROM analisis_precios_unitarios_detalles WHERE id = :id';
                $apud = self::fetchObj($sql, ['id' => $this->_id]);
                if ($apud) {
                    self::update("analisis_precios_unitarios_detalles", $this->_values, ['id' => $this->_id]);
                    $analisisPreciosUnitarios = new AnalisisPreciosUnitarios();
                    $analisisPreciosUnitarios->getUpdateCabMont($apud->analisis_precios_unitarios_id);
                    $this->getAcumulacionInsumo();
                    $resp['success'] = true;
                    $resp['message'] = 'se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $insert = self::insert("analisis_precios_unitarios_detalles", $this->_values);
                $analisisPreciosUnitarios = new AnalisisPreciosUnitarios();
                $analisisPreciosUnitarios->getUpdateCabMont($this->_analisis_precios_unitarios_id);
                $this->getAcumulacionInsumo();
                if ($insert && $insert["lastInsertId"]) {
                    $resp['success'] = true;
                    $resp['message'] = 'se registro correctamente ';
                    $resp['data'] = $insert["lastInsertId"];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Ocurrió un erro al registrar';
                }
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getAcumulacionInsumo()
    {

        try {
            $sql_apu = 'SELECT presupuestos_id FROM analisis_precios_unitarios WHERE id = :id';
            $apu = self::fetchObj($sql_apu, ['id' => $this->_analisis_precios_unitarios_id]);

            $sql_presupuesto = 'SELECT proyecto_generales_id FROM presupuestos WHERE id = :id';
            $presupuestos = self::fetchObj($sql_presupuesto, ['id' => $apu->presupuestos_id]);

            $sql = 'SELECT id,cantidad FROM detalle_insumos 
                    WHERE insumos_id = :insumos_id AND proyecto_generales_id =:proyecto_generales_id ';
            $detalle_insumos = self::fetchObj($sql, ['insumos_id' => $this->_insumos_id, 'proyecto_generales_id' => $presupuestos->proyecto_generales_id]);

            $sql_apud = 'SELECT SUM(cantidad) AS sum_insumos FROM analisis_precios_unitarios_detalles WHERE insumos_id = :insumos_id';
            $apud = self::fetchObj($sql_apud, ['insumos_id' => $this->_insumos_id]);

            $var = [
                "unidad_medidas_id" => $this->_unidad_medidas_id,
                "insumos_id" => $this->_insumos_id,
                "subpresupuestos_id" => $this->_subpresupuestos_id,
                "proyecto_generales_id" => $presupuestos->proyecto_generales_id,
                "cantidad" => $apud->sum_insumos,
            ];

            if ($detalle_insumos) {
                self::update("detalle_insumos", $var, ['id' => $detalle_insumos->id]);
            } else {
                self::insert("detalle_insumos", $var);
            }

            $resp['success'] = true;
            $resp['message'] = 'se registro correctamente ';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }
}
