<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../src/ApusPartidasProyecto.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\ApusPartidasProyecto;
use stdClass;

class DetalleInsumos extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_unidad_medidas_id;
    public function getUnidadMedidasId()
    {
        return $this->_unidad_medidas_id;
    }
    private $_monto_iu;
    public function getMontoIu()
    {
        return $this->_monto_iu;
    }
    private $_cantidad;
    public function getcantidad()
    {
        return $this->_cantidad;
    }
    private $_precio;
    public function getprecio()
    {
        return $this->_precio;
    }
    private $_parcial;
    private $_insumos_id;
    public function getInsumosId()
    {
        return $this->_insumos_id;
    }
    private $_proyecto_generales_id;
    private $_subpresupuestos_id;
    public function getProyectoGeneralesId()
    {
        return $this->_proyecto_generales_id;
    }
    private $_values;
    public function getValue()
    {
        return $this->_values;
    }

    public function __construct($request, $array = [])
    {
        if ($request) {
            $column = [
                'id',
                'unidad_medidas_id',
                'iu',
                'cantidad',
                'precio',
                'parcial',
                'insumos_id',
                'proyecto_generales_id',
                'subpresupuestos_id',
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    if ($value == 'precio') {
                        $this->_precio = number_format($request->{$value}, 2, '.', '');
                    } else {
                        $this->{"_$value"} = $request->{$value};
                    }
                }
            }
        }
    }

    public function getSave()
    {
        try {
            if ($this->_id) {
                $sql = 'SELECT proyectos_generales_id FROM insumos_proyecto WHERE id = :id';
                $detalleInsumos = self::fetchObj($sql, ['id' => $this->_id]);
                if ($detalleInsumos) {
                    self::update("insumos_proyecto", [
                        'precio' => $this->_precio
                    ], ['id' => $this->_id]);
                    if ($this->_iu) {
                        self::update("apus_partida_presupuestos", [
                            'iu' => $this->_iu
                        ], [
                            'insumo_id' => $this->_id,
                            'proyectos_generales_id' => $detalleInsumos->proyectos_generales_id,
                            'subpresupuestos_id' => $this->_subpresupuestos_id,
                        ]);
                    }
                    $this->actualizarPartidas($detalleInsumos->proyectos_generales_id);
                    $resp['success'] = true;
                    $resp['message'] = 'Insumo actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Debe enviar el ID para actualizar del detalle';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function deleteinsumo($request)
    {
        try {
            $sql = 'SELECT proyectos_generales_id FROM insumos_proyecto WHERE id = :id AND deleted_at IS NULL';
            $detalleInsumos = self::fetchObj($sql, ['id' => $request->id]);
            if ($detalleInsumos) {
                self::update("insumos_proyecto", ['deleted_at' => FG::getFechaHora()], ['id' => $request->id]);
                self::update("apus_partida_presupuestos", ['deleted_at' => FG::getFechaHora()], ['insumo_id' => $request->id]);
                $this->_id = $request->id;
                $this->actualizarPartidas($detalleInsumos->proyectos_generales_id, true);
                $resp['success'] = true;
                $resp['message'] = 'Insumo eliminado';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Insumo no existe';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getListadoAcumulacion()
    {
        try {
            $sql = "SELECT  ip.id,
                            app.unidad_medidas_id,
                            ip.iu,
                            app.cantidad,
                            app.cuadrilla,
                            ip.precio,
                            app.proyectos_generales_id,
                            ip.tipo,
                            ip.insumos,
                            um.alias,
                            um.apu_cantidad,
                            app.subpresupuestos_id,
                            app.presupuestos_id,
                            pp.rendimiento,
                            pg.jornada_laboral,
                            pt.metrado
                    FROM apus_partida_presupuestos app
                    INNER JOIN insumos_proyecto ip ON app.insumo_id = ip.id
                    INNER JOIN unidad_medidas um  ON ip.unidad_medidas_id = um.id
                    INNER JOIN presupuestos_partida pp ON pp.presupuestos_id = app.presupuestos_id AND pp.subpartida_id IS NULL
                    INNER JOIN presupuestos pt ON pp.presupuestos_id = pt.id
                    INNER JOIN proyecto_generales pg ON app.proyectos_generales_id = pg.id
                    WHERE app.proyectos_generales_id = :proyecto_generales_id AND app.subpresupuestos_id IN ({$this->_subpresupuestos_id}) 
                    AND app.partida_id IS NULL AND app.subpartida_id IS NULL AND app.deleted_at IS NULL";
            $list = self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_proyecto_generales_id]);

            $sql = "SELECT  app.id AS apuid,
                            ip.id,
                            app.unidad_medidas_id,
                            ip.iu,
                            app.cantidad,
                            app.cuadrilla,
                            app.precio AS sprecio,
                            ip.precio,
                            app.proyectos_generales_id,
                            ip.tipo,
                            ip.insumos,
                            um.alias,
                            um.apu_cantidad,
                            app.subpresupuestos_id,
                            app.partida_id,
                            app.subpartida_id,
                            app.presupuestos_id,
                            pp.rendimiento,
                            pg.jornada_laboral,
                            pt.metrado
                    FROM apus_partida_presupuestos app
                    LEFT JOIN insumos_proyecto ip ON app.insumo_id = ip.id
                    LEFT JOIN unidad_medidas um  ON ip.unidad_medidas_id = um.id
                    LEFT JOIN presupuestos_partida pp ON pp.subpartida_id = app.id OR pp.subpartida_id = app.subpartida_id
                    INNER JOIN presupuestos pt ON app.presupuestos_id = pt.id
                    INNER JOIN proyecto_generales pg ON app.proyectos_generales_id = pg.id
                    WHERE app.proyectos_generales_id = :proyecto_generales_id AND app.subpresupuestos_id IN ({$this->_subpresupuestos_id}) 
                    AND (app.partida_id IS NOT NULL OR app.subpartida_id IS NOT NULL) AND app.deleted_at IS NULL";
            $sublist = self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_proyecto_generales_id]);

            $insumos = $this->assembleInsumos($list, $sublist);
            return $insumos;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Ocurrió un erro al listar insumos';
            return $resp;
        }
    }

    public function assembleInsumos($list, $sublist)
    {
        $filter = $this->performCalculations($list, 'presupuestos_id');
        $sublist = array_reduce($sublist, function ($carry, $item) {
            if ($item->partida_id) {
                $carry['subpartidas'][$item->apuid] = $item;
            } else {
                if (!isset($carry['apus'])) {
                    $carry['apus'] = [];
                }
                array_push($carry['apus'], $item);
            }
            return $carry;
        });
        $auxfilter = [];
        if (isset($sublist['apus'])) {
            $apus = $sublist['apus'];
            $subpartidas = $sublist['subpartidas'];
            $subfilter = $this->performCalculations($apus, 'subpartida_id');
            foreach ($subpartidas as $sp => $value) {
                if (isset($subfilter[$sp])) {
                    $pid = $value->presupuestos_id;
                    foreach ($subfilter[$sp] as $key => $item) {
                        /*if(isset($filter[$pid][$key])) {
                            $metered = $filter[$pid][$key]->metrado ? $filter[$pid][$key]->metrado : 0.00;
                            if($filter[$pid][$key]->apu_cantidad) { // Porcentaje
                                $cantidad = ($value->cantidad * $item->parcial);
                                // $cantidad = ($filter[$pid][$key]->parcial + $cantidad) * $metered;
                                $filter[$pid][$key]->cantidad = number_format(round($cantidad, 4), 4,'.','');
                                // $parcial = $filter[$pid][$key]->cantidad * $filter[$pid][$key]->precio;
                                // $filter[$pid][$key]->parcial = number_format(round($parcial, 2), 2,'.','');
                            } else {
                                $cantidad = ($value->cantidad * $item->cantidad);
                                // $cantidad = ($filter[$pid][$key]->cantidad + $cantidad) * $metered;
                                $filter[$pid][$key]->cantidad = number_format(round($cantidad, 4), 4,'.','');
                                // $parcial = $filter[$pid][$key]->cantidad * $filter[$pid][$key]->precio;
                                // $filter[$pid][$key]->parcial = number_format(round($parcial, 2), 2,'.','');
                            }
                        } else {
                            $item->cantidad = $item->cantidad * $value->cantidad;
                            $filter[$pid][$key] = $item;
                        }*/
                        /*if($filter[$pid][$key]->apu_cantidad) {
                            FG::debug($item->parcial);
                            $item->cantidad = ($item->parcial * $value->cantidad) + $filter[$pid][$key]->parcial;
                        } else {
                            $item->cantidad = $item->cantidad * $value->cantidad;
                        }*/
                        $item->cantidad = $item->cantidad * $value->cantidad;
                        $item->partida_cantidad = $value->cantidad;
                        $item->sp = 1;
                        $auxfilter[$pid][$key] = $item;
                    }
                }
            }
        }

        $keys = [];
        foreach ($filter as $k => $items) {
            foreach ($items as $k2 => $val) {
                $keys[$k2][] = $val;
            }
        }

        foreach ($auxfilter as $k => $items) {
            foreach ($items as $k2 => $val) {
                $keys[$k2][] = $val;
            }
        }

        $moKeys = [];
        foreach ($keys as $k => $values) {
            if (count($values) > 0) {
                $item = null;
                foreach ($values as $k2 => $val) {
                    if ($k2 == 0) {
                        $item = $val;
                    }
                    if (strtolower($val->tipo) == 'mo' && strtolower($val->alias) != '%mo') {
                        if ($val->presupuestos_id > 0 && !isset($val->sp)) {
                            $moKeys[$val->presupuestos_id][] = ($val->cantidad * $item->precio);
                        }
                    }
                }
                foreach ($values as $k2 => $val) {
                    if (strtolower($val->alias) == '%mo') {
                        if ($val->presupuestos_id > 0 && isset($moKeys[$val->presupuestos_id])) {
                            if ($val->sp) {
                                $keys[$k][$k2]->precio = $keys[$k][$k2]->partida_cantidad * $keys[$k][$k2]->metrado * $keys[$k][$k2]->parcial; // precio sera su parcial para este tipo %mo
                            } else {
                                $moParcials = $moKeys[$val->presupuestos_id];
                                $moTotal = 0;
                                foreach ($moParcials as $mok => $moParcial) {
                                    $moTotal = $moTotal + $moParcial;
                                }
                                $keys[$k][$k2]->precio = ($keys[$k][$k2]->cantidad * $keys[$k][$k2]->metrado) * $moTotal; // precio sera su parcial para este tipo %mo
                            }
                        }
                    }
                }
            }
        }

        $supplies = [];
        $keys_supplies = [];
        foreach ($keys as $k => $values) {
            $countValues = count($values);
            if ($countValues > 0) {
                $item = null;
                foreach ($values as $k2 => $val) {
                    if ($k2 == 0) {
                        $item = $val;
                        $cantidad = 0;
                        $parcial = 0;
                        $precio = $item->precio;
                    }
                    if (strtolower($val->alias) == '%mo') {
                        $parcial = $parcial + $val->precio;
                    } else {
                        $cantidad = $cantidad + ($val->metrado * $val->cantidad);
                    }
                    if ($k2 == $countValues - 1) {
                        if (strtolower($item->alias) != '%mo') {
                            $parcial = $cantidad * $precio;
                        }
                        $item->cantidad = $cantidad;
                        $item->parcial = $parcial;
                        $keys_supplies[$item->tipo][] = $parcial;
                    }
                }
                if ($item) {
                    $supplies[] = $item;
                }
            }
        }

        foreach ($supplies as $k => $o) {
            if (strtolower($o->alias) == '%mo') {
                $supplies[$k]->cantidad = "-";
                $supplies[$k]->precio = "-";
            } else {
                $supplies[$k]->precio = FG::numberFormat($o->precio);
            }
            $supplies[$k]->parcial = FG::numberFormat($o->parcial);
            $supplies[$k]->parcialNumber = $o->parcial;
            $supplies[$k]->total = 0;
            foreach ($keys_supplies as $k2 => $o2) {
                if ($k2 == $o->tipo) {
                    $total = 0;
                    foreach ($o2 as $k3 => $o3) {
                        $total = $total + $o3;
                    }
                    $o->total = $total;
                }
            }
            $supplies[$k]->total = FG::numberFormat($o->total);
        }

        return $supplies;

        $filter = array_reduce($filter, function ($carry, $value) {
            foreach ($value as $key => $item) {
                if (isset($carry[$key])) {
                    $cantidad = $carry[$key]->cantidad + $item->cantidad;
                    $precio = $carry[$key]->precio;
                    $carry[$key]->cantidad = number_format($cantidad, 4, '.', '');
                    $carry[$key]->parcial = number_format(($cantidad * $precio), 2, '.', '');
                } else {
                    $carry[$key] = $item;
                }
            }
            return $carry;
        }, []);

        $filter = array_reduce($filter, function ($carry, $value) {
            array_push($carry, $value);
            return $carry;
        }, []);

        return $filter;
    }

    public function performCalculations($apus, $field)
    {
        $filter = [];
        $apus = array_reduce($apus, function ($carry, $item) use ($field) {
            if ($item->{$field}) {
                $itype = strtolower($item->tipo);
                if (isset($carry[$item->{$field}])) {
                    if (isset($carry[$item->{$field}][$itype])) {
                        array_push($carry[$item->{$field}][$itype], $item);
                    } else {
                        $array_type = [];
                        array_push($array_type, $item);
                        $carry[$item->{$field}][$itype] = $array_type;
                    }
                } else {
                    $array_type = [];
                    array_push($array_type, $item);
                    $carry[$item->{$field}][$itype] = $array_type;
                }
            }
            return $carry;
        });

        if ($apus) {
            foreach ($apus as $p => $item) {
                $mototal = 0;
                if (isset($item['mo'])) {
                    foreach ($item['mo'] as $key => $e) {
                        $jorn = $e->jornada_laboral;
                        $rend = $e->rendimiento;
                        $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                        $precio = $e->precio ? $e->precio : 0;
                        $cantidad = $rend ? (($cuadrilla * $jorn) / $rend) : 0;
                        $parcial = $cantidad * $precio;
                        $parcial = number_format(round($parcial, 2), 2, '.', '');
                        $cantidad = number_format(round($cantidad, 4), 4, '.', '');
                        if (isset($filter[$p])) {
                            if (isset($filter[$p][$e->id])) {
                                $filter[$p][$e->id]->cantidad = $cantidad;
                                $filter[$p][$e->id]->parcial = $parcial;
                            } else {
                                $e->cantidad = $cantidad;
                                $e->parcial = $parcial;
                                $filter[$p][$e->id] = $e;
                            }
                        } else {
                            $e->cantidad = $cantidad;
                            $e->parcial = $parcial;
                            $filter[$p][$e->id] = $e;
                        }
                        $mototal += $parcial;
                    }
                }

                $eqtotal = 0;
                if (isset($item['eq'])) {
                    foreach ($item['eq'] as $key => $e) {
                        $jorn = $e->jornada_laboral;
                        $rend = $e->rendimiento;
                        $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                        $precio = $e->precio ? $e->precio : 0;
                        if ($e->apu_cantidad) {
                            $precio = $mototal;
                        } else {
                            $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                            $cantidad = $rend ? (($cuadrilla * $jorn) / $rend) : 0;
                        }
                        $cantidad = number_format(round($cantidad, 4), 4, '.', '');
                        $parcial = ($cantidad * $precio);
                        $parcial = number_format(round($parcial, 2), 2, '.', '');
                        if (isset($filter[$p])) {
                            if (isset($filter[$p][$e->id])) {
                                $filter[$p][$e->id]->cantidad = $cantidad;
                                $filter[$p][$e->id]->parcial = $parcial;
                            } else {
                                $e->cantidad = $cantidad;
                                $e->parcial = $parcial;
                                $filter[$p][$e->id] = $e;
                            }
                        } else {
                            $e->cantidad = $cantidad;
                            $e->parcial = $parcial;
                            $filter[$p][$e->id] = $e;
                        }
                        $eqtotal += $parcial;
                    }
                }

                if (isset($item['mt'])) {
                    foreach ($item['mt'] as $key => $e) {
                        $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                        $precio = $e->precio ? $e->precio : 0;
                        if ($e->apu_cantidad) {
                            $alias = strtolower($e->alias);
                            $precio = $alias == '%mo' ? $mototal : $eqtotal;
                        }
                        $parcial = ($cantidad * $precio);
                        $cuadrilla = '';
                        $cantidad = number_format(round($cantidad, 4), 4, '.', '');
                        $parcial = number_format(round($parcial, 2), 2, '.', '');
                        if (isset($filter[$p])) {
                            if (isset($filter[$p][$e->id])) {
                                $filter[$p][$e->id]->cantidad = $cantidad;
                                $filter[$p][$e->id]->parcial = $parcial;
                            } else {
                                $e->cantidad = $cantidad;
                                $e->parcial = $parcial;
                                $filter[$p][$e->id] = $e;
                            }
                        } else {
                            $e->cantidad = $cantidad;
                            $e->parcial = $parcial;
                            $filter[$p][$e->id] = $e;
                        }
                    }
                }

                if (isset($item['sc'])) {
                    foreach ($item['sc'] as $key => $e) {
                        $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                        $precio = $e->precio ? $e->precio : 0;
                        if ($e->apu_cantidad) {
                            $alias = strtolower($e->alias);
                            $precio = $alias == '%mo' ? $mototal : $eqtotal;
                        }
                        $parcial = ($cantidad * $precio);
                        $parcial = number_format(round($parcial, 2), 2, '.', '');
                        $cantidad = number_format(round($cantidad, 4), 4, '.', '');
                        if (isset($filter[$p])) {
                            if (isset($filter[$p][$e->id])) {
                                $filter[$p][$e->id]->cantidad = $cantidad;
                                $filter[$p][$e->id]->parcial = $parcial;
                            } else {
                                $e->cantidad = $cantidad;
                                $e->parcial = $parcial;
                                $filter[$p][$e->id] = $e;
                            }
                        } else {
                            $e->cantidad = $cantidad;
                            $e->parcial = $parcial;
                            $filter[$p][$e->id] = $e;
                        }
                    }
                }
            }
        }

        return $filter;
    }

    public function actualizarPartidas($proyectos_generales_id, $isdel = false)
    {
        $del = 'AND app.deleted_at IS NULL';
        if ($isdel) {
            $del = '';
        }
        $sql = "SELECT app.presupuestos_id, app.subpartida_id FROM apus_partida_presupuestos app
        WHERE app.proyectos_generales_id = :proyecto_generales_id AND app.insumo_id = :insumo_id {$del}";

        $list = self::fetchAllObj($sql, ['proyecto_generales_id' => $proyectos_generales_id, 'insumo_id' => $this->_id]);

        $apusPartidasProyecto = new ApusPartidasProyecto();
        $request = new stdClass();
        foreach ($list as $key => $item) {
            $request->subpartida_id = $item->subpartida_id;
            $request->presupuestos_id = $item->presupuestos_id;
            $apusPartidasProyecto->getUpdateGames($request);
        }
    }
}
