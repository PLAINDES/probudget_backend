<?php

//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/RecalculoPrespuesto.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\RecalculoPrespuesto;

class AnalisisPreciosUnitarios extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_rendimiento;
    public function getrendimiento()
    {

        $sql_presupuesto = "SELECT id, proyecto_generales_id, metrado  FROM presupuestos WHERE id = :id";
        $presupuesto = self::fetchObj($sql_presupuesto, ['id' => $this->_presupuestos_id]);

        $sql_proyecto_general = "SELECT id, jornada_laboral FROM proyecto_generales WHERE id = :id";
        $proyecto_general = self::fetchObj($sql_proyecto_general, ['id' => $presupuesto->proyecto_generales_id]);


        $sql = " SELECT id, cantidad,cuadrilla, precio
                     FROM analisis_precios_unitarios_detalles 
                     WHERE analisis_precios_unitarios_id = :id ";
        $detalle = self::fetchAllObj($sql, ['id' => $this->_id]);

        foreach ($detalle as $key => $value) {
            if ($value->cuadrilla) {
                $cantidad = $value->cuadrilla *  $proyecto_general->jornada_laboral / $this->_rendimiento;
                $parcial = $cantidad * $value->precio;
                self::update("analisis_precios_unitarios_detalles", ["cantidad" => $cantidad, "parcial" => $parcial], ['id' => $value->id]);
            }
        }
    }
    private $_presupuestos_id;
    public function getPresupuestosId()
    {
        return $this->_presupuestos_id;
    }

    private $_mano_obra;
    public function getmanoObra()
    {
        return $this->_mano_obra;
    }
    private $_materiales;
    public function getMateriales()
    {
        return $this->_materiales;
    }
    private $_herramienta_equipos;
    public function getherramientaEquipos()
    {
        return $this->_herramienta_equipos;
    }
    private $_rendimiento_unid;
    public function getrendimientoUnid()
    {
        return $this->rendimiento_unid;
    }
    private $_values;
    public function getValue()
    {
        return $this->_values;
    }

    public function __construct($request = null)
    {
        if ($request) {
            $column = [
                'id',
                'rendimiento',
                'presupuestos_id',
                'mano_obra',
                'materiales',
                'herramienta_equipos',
                'rendimiento_unid',
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }
        }

        // $this->_id = (array_key_exists('id',$array))?$array['id']:0;
        // $this->_rendimiento = (array_key_exists('rendimiento',$array))?$array['rendimiento']:"";
        // $this->_presupuestos_id = (array_key_exists('presupuestos_id',$array))?$array['presupuestos_id']:"";
        // $this->_mano_obra = (array_key_exists('mano_obra',$array))?$array['mano_obra']:"";
        // $this->_materiales = (array_key_exists('materiales',$array))?$array['materiales']:"";
        // $this->_herramienta_equipos = (array_key_exists('herramienta_equipos',$array))?$array['herramienta_equipos']:"";
    }

    public function getSave()
    {
        try {
            if ($this->_id) {
                $sql = 'SELECT COUNT(id) FROM analisis_precios_unitarios WHERE id = :id';
                $analisisPreciosUnitarios = self::fetchObj($sql, ['id' => $this->_id]);
                if ($analisisPreciosUnitarios) {
                    $update = self::update("analisis_precios_unitarios", $this->_values, ['id' => $this->_id]);
                    $this->getrendimiento();
                    $this->getUpdateCabMont();

                    $resp['success'] = true;
                    $resp['message'] = 'se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $insert = self::insert("analisis_precios_unitarios", $this->_values);
                if ($insert && $insert["lastInsertId"]) {
                    $id = $insert["lastInsertId"];
                    $resp['success'] = true;
                    $resp['message'] = 'se registro correctamente ';
                    $resp['data'] = compact('id');
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


    public function getUpdateCabMont($id = null)
    {

        try {
            $this->_id = ($this->_id) ? $this->_id : $id;

            $sql_pre = 'SELECT id,  presupuestos_id
            FROM analisis_precios_unitarios
            WHERE id = :id';
            $apud_pre = self::fetchObj($sql_pre, ['id' => $this->_id]);

            $sql = 'SELECT id, parcial,tipo_insumo
            FROM analisis_precios_unitarios_detalles
            WHERE analisis_precios_unitarios_id = :id';
            $apud = self::fetchAllObj($sql, ['id' => $this->_id]);

            $array_tipo = ['MO', 'MT', 'EQ'];

            foreach ($array_tipo as $key => $value) {
                $object =   array_filter(
                    $apud,
                    function ($e) use ($value) {
                        return $e->tipo_insumo == $value;
                    }
                );

                $sum_parcial = array_sum(array_column($object, 'parcial'));
                switch ($value) {
                    case 'MO':
                        $value = ['mano_obra' => number_format($sum_parcial, 2, '.', '')];
                        $value_p = ['apu_mo' => number_format($sum_parcial, 2, '.', '')];
                        break;
                    case 'MT':
                        $value = ['materiales' => number_format($sum_parcial, 2, '.', '')];
                        $value_p = ['apu_mat' => number_format($sum_parcial, 2, '.', '')];
                        break;

                    default:
                        $value = ['herramienta_equipos' =>  number_format($sum_parcial, 2, '.', '')];
                        $value_p = ['apu_eq' => number_format($sum_parcial, 2, '.', '')];
                        break;
                }
                self::update("analisis_precios_unitarios", $value, ['id' => $this->_id]);
                self::update("presupuestos", $value_p, ['id' => $apud_pre->presupuestos_id]);
            }

            $sql_apu = 'SELECT id, presupuestos_id,mano_obra,materiales,herramienta_equipos
                    FROM analisis_precios_unitarios
                    WHERE id = :id';
            $apu = self::fetchObj($sql_apu, ['id' => $this->_id]);

            $suma_apu =  $apu->mano_obra + $apu->materiales + $apu->herramienta_equipos;
            $sql = "SELECT SUM(metrado_parcial) AS 'sum_metrado_parcial' 
                    FROM presupuestos 
                    WHERE presupuestos_proyecto_generales_id = :id  AND deleted_at is NULL";
            $presupuestos = self::fetchObj($sql, ['id' => $apu->presupuestos_id]);
            $parcial = (($presupuestos->sum_metrado_parcial) ? $presupuestos->sum_metrado_parcial : 0) * $suma_apu;


            if ($parcial) {
                if (is_nan($parcial)) {
                    $parcial =  0.00;
                }
            } else {
                $parcial = 0.00;
            }

            if ($suma_apu) {
                if (is_nan($suma_apu)) {
                    $suma_apu =  0.00;
                }
            } else {
                $suma_apu = 0.00;
            }


            $value = [
                "apu_cu" => number_format($suma_apu, 2, '.', ''),
                "parcial" => number_format($parcial, 2, '.', '')
            ];

            self::update("presupuestos", $value, ['id' => $apu->presupuestos_id]);

            $recalculo = new RecalculoPrespuesto();
            $recalculo->getRecalculoPrespuesto($apu->presupuestos_id);

            $resp['success'] = true;
            $resp['message'] = 'se actualizo la cabecera';

            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getListAnalisisPreciosUnitarios()
    {
        try {
            $sql = "SELECT 
                        id,
                        rendimiento,
                        presupuestos_id,
                        mano_obra,
                        materiales,
                        herramienta_equipos,
                        rendimiento_unid,
                        'detalle',
                        'success'
                    FROM analisis_precios_unitarios 
                    WHERE presupuestos_id = :id";
            $cabecera = self::fetchObj($sql, ['id' => $this->_presupuestos_id]);

            if ($cabecera) {
                $sql_detalle = "SELECT                           
                                    analisis_precios_unitarios_detalles.id,
                                    analisis_precios_unitarios_detalles.unidad_medidas_id,
                                    cuadrilla,
                                    cantidad,
                                    analisis_precios_unitarios_detalles.precio,
                                    parcial,
                                    analisis_precios_unitarios_id,
                                    monto_parcial_ppto,
                                    tipo_insumo,
                                    insumos_id,
                                    insumos.insumos,
                                    unidad_medidas.alias,
                                    unidad_medidas.apu_cantidad                         
                                FROM analisis_precios_unitarios_detalles 
                                INNER JOIN insumos ON insumos.id = insumos_id
                                INNER JOIN unidad_medidas ON unidad_medidas.id = insumos.unidad_medidas_id                        
                                WHERE analisis_precios_unitarios_id = :id";

                // foreach ($cabecera as $key => $value) {
                $cabecera->detalle = self::fetchAllObj($sql_detalle, ['id' => $cabecera->id]);
                $cabecera->success = true;
                // }
                return $cabecera;
            } else {
                return ["success" => false];
            }
        } catch (\Throwable $th) {
            return [];
        }
    }
}
