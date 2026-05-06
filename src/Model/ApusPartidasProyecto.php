<?php

//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/EspecificacionesTecnicas.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\EspecificacionesTecnicas;
use stdClass;

class ApusPartidasProyecto extends Mysql
{
    public function getSave($param)
    {
        $resp = ['success' => true, 'message' => 'Apu guardado'];
        try {
            $nullable = [];
            $var = [];

            // Procesar cuadrilla
            if ($param->cuadrilla) {
                $var['cuadrilla'] = number_format($param->cuadrilla, 2, '.', '');
            } else {
                $nullable['cuadrilla'] = 'NULL';
            }

            // Procesar cantidad
            if (is_numeric($param->cantidad)) {
                $var['cantidad'] = number_format($param->cantidad, 4, '.', '');
            } else {
                $nullable['cantidad'] = 'NULL';
            }

            // Procesar rendimiento
            if (isset($param->rendimiento) && is_numeric($param->rendimiento)) {
                $rendimientoVar['rendimiento'] = number_format($param->rendimiento, 2, '.', '');
            }
            if (!empty($param->rendimiento_unid)) {
                $rendimientoVar['rendimiento_unid'] = $param->rendimiento_unid;
            }

            // Si hay datos de rendimiento y hay id, actualizar presupuestos_partida
            if (!empty($rendimientoVar) && $param->id) {
                self::update("presupuestos_partida", $rendimientoVar, ['id' => $param->id]);
                $resp['data'] = array_merge(['id' => $param->id], $rendimientoVar);
                return $resp;
            }


            // *** FIN NUEVO ***

            $insumo = null;
            if ($param->master_insumo_id) {
                $insumo = $this->findOrCreateInsumo($param->master_insumo_id, $param->proyectos_generales_id);
            } elseif ($param->proyecto_insumo_id) {
                $insumo = $this->findInsumo($param->proyecto_insumo_id);
            }

            if ($param->id) {
                // UPDATE - Actualizar registro existente
                if ($insumo) {
                    $var['insumo_id'] = $insumo->id;
                }
                if ($insumo) {
                    $var['unidad_medidas_id'] = $insumo->unidad_medidas_id;
                }

                self::update("apus_partida_presupuestos", $var, ['id' => $param->id]);

                $valueSets = [];

                // Actualizar precio
                if ($param->precio && $param->insumo_id) {
                    $sql = 'SELECT partida_id FROM apus_partida_presupuestos WHERE id = :id AND deleted_at IS NULL';
                    $insu = self::fetchObj($sql, ['id' => $param->id]);
                    if ($insu && $insu->partida_id) {
                        $precio = $param->precio;
                        $valueSets[] = "precio = {$precio}";
                    } else {
                        $precio = $param->precio;
                        self::update("insumos_proyecto", array('precio' => $precio), array('id' => $param->insumo_id));
                    }
                }

                // Procesar campos nullable
                foreach ($nullable as $key => $value) {
                    $valueSets[] = "$key = {$value}";
                }

                $nullable = implode(", ", $valueSets);
                if ($nullable) {
                    self::ex("UPDATE apus_partida_presupuestos SET {$nullable} WHERE id = " . $param->id);
                }

                $var['id'] = $param->id;
            } else {
                // INSERT - Crear nuevo registro
                if (!empty($insumo)) {
                    $insu = null;
                    if ($param->subpartida_id) {
                        $sql = 'SELECT id, unidad_medidas_id FROM apus_partida_presupuestos WHERE insumo_id = :insumo_id AND subpartida_id = :subpartida_id AND deleted_at IS NULL';
                        $insu = self::fetchObj($sql, ['insumo_id' => $insumo->id, 'subpartida_id' => $param->subpartida_id]);
                    } else {
                        $sql = 'SELECT id, unidad_medidas_id FROM apus_partida_presupuestos WHERE insumo_id = :insumo_id AND presupuestos_id = :presupuestos_id AND subpartida_id IS NULL AND deleted_at IS NULL';
                        $insu = self::fetchObj($sql, ['insumo_id' => $insumo->id, 'presupuestos_id' => $param->presupuestos_id]);
                    }

                    if ($insu) {
                        // Ya existe, actualizar
                        self::update("apus_partida_presupuestos", array('unidad_medidas_id' => $insu->unidad_medidas_id), array('id' => $insu->id));
                        $var['id'] = $insu->id;
                    } else {
                        // No existe, insertar nuevo
                        $var['insumo_id'] = $insumo->id;
                        $var['unidad_medidas_id'] = $insumo->unidad_medidas_id;
                        $var['proyectos_generales_id'] = $param->proyectos_generales_id;
                        $var['presupuestos_id'] = $param->presupuestos_id;
                        $var['subpresupuestos_id'] = $param->subpresupuestos_id;
                        if ($param->subpartida_id) {
                            $var['subpartida_id'] = $param->subpartida_id;
                        }

                        $lastInsert = self::insert("apus_partida_presupuestos", $var);
                        $var['id'] = $lastInsert['lastInsertId'];
                    }
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'El insumo no existe';
                    return $resp;
                }
            }

            $resp['data'] = $var;
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getSaveNewinsumo($request)
    {
        $resp = ['success' => true, 'message' => 'Apu guardado'];
        try {
            $var = [];
            if ($request->cuadrilla) {
                $var['cuadrilla'] = number_format($request->cuadrilla, 2, '.', '');
            }
            if ($request->cantidad) {
                $cantidad = $request->cantidad;
                $sql = 'SELECT apu_cantidad FROM unidad_medidas WHERE id = :id';
                $resp = self::fetchObj($sql, ['id' => $request->unidad_medidas_id]);
                if ($resp && $resp->apu_cantidad) {
                    $cantidad = $cantidad / 100;
                }
                $var['cantidad'] = number_format($cantidad, 4, '.', '');
            }
            $codigo = $this->getCodeSupply($request->proyectos_generales_id);
            $insumo = array(
                'codigo' => $codigo,
                'iu' => $request->iu,
                'tipo' => $request->tipo,
                'insumos' => $request->insumos,
                'precio' => $request->precio,
                'unidad_medidas_id' => $request->unidad_medidas_id,
                'proyectos_generales_id' => $request->proyectos_generales_id
            );
            $lastInsert = self::insert("insumos_proyecto", $insumo);
            $insumo['id'] = $lastInsert['lastInsertId'];
            $var['insumo_id'] = $insumo['id'];
            $var['unidad_medidas_id'] = $insumo['unidad_medidas_id'];
            $var['proyectos_generales_id'] = $request->proyectos_generales_id;
            $var['presupuestos_id'] = $request->presupuestos_id;
            $var['subpresupuestos_id'] = $request->subpresupuestos_id;
            if ($request->subpartida_id) {
                $var['subpartida_id'] = $request->subpartida_id;
            }
            $lastInsert = self::insert("apus_partida_presupuestos", $var);
            $var['id'] = $lastInsert['lastInsertId'];
            $var['insumo'] = $insumo;
            $resp['data'] = $var;
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    private function findOrCreateInsumo($masterInsumoId, $proyectos_generales_id)
    {
        $sql = 'SELECT id, unidad_medidas_id FROM insumos_proyecto WHERE master_insumo_id = :insumoId AND proyectos_generales_id = :proyectos_generales_id';
        $insumo = self::fetchObj($sql, ['insumoId' => $masterInsumoId, 'proyectos_generales_id' => $proyectos_generales_id]);
        if (empty($insumo)) {
            $resp = new stdClass();
            $sql = 'SELECT id, codigo, iu, indice_unificado, tipo, insumos, precio, unidad_medidas_id FROM insumos WHERE id = :insumoId';
            $insumo = self::fetchObj($sql, ['insumoId' => $masterInsumoId]);
            $lastInsert = self::insert("insumos_proyecto", array(
                'codigo' => $insumo->codigo,
                'iu' => $insumo->iu,
                'indice_unificado' => $insumo->indice_unificado,
                'tipo' => $insumo->tipo,
                'insumos' => $insumo->insumos,
                'precio' => $insumo->precio,
                'unidad_medidas_id' => $insumo->unidad_medidas_id,
                'master_insumo_id' => $insumo->id,
                'proyectos_generales_id' => $proyectos_generales_id
            ));
            $resp->id = $lastInsert['lastInsertId'];
            $resp->unidad_medidas_id = $insumo->unidad_medidas_id;
            return $resp;
        }
        return $insumo;
    }

    private function findInsumo($id)
    {
        $sql = 'SELECT id, codigo, iu, indice_unificado, tipo, insumos, precio, unidad_medidas_id FROM insumos_proyecto WHERE id = :insumoId';
        $insumo = self::fetchObj($sql, ['insumoId' => $id]);
        return $insumo;
    }

    public function getListApus($request)
    {
        $resp = new stdClass();
        $subpartida_id = $request->subpartida_id;
        $presupuestos_id = $request->presupuestos_id;
        error_log('presupuestos_id: ' . $presupuestos_id);
        error_log('subpartida_id: ' . $subpartida_id);

        $result = $this->getApusPartida($presupuestos_id, $subpartida_id);
        if ($result->success) {
            $resp = $this->performCalculations($result->cabecera, $result->apus);
            $this->updateHeaderApuPartida($resp, $presupuestos_id, $subpartida_id);
            $resp->mano_obra = FG::numberFormat($resp->mano_obra); // number_format($resp->mano_obra, 2, '.', '');
            $resp->materiales = FG::numberFormat($resp->materiales); // number_format($resp->materiales, 2, '.', '');
            $resp->herramienta_equipos = FG::numberFormat($resp->herramienta_equipos); // number_format($resp->herramienta_equipos, 2, '.', '');
            $resp->subcontrato = FG::numberFormat($resp->subcontrato); // number_format($resp->subcontrato, 2, '.', '');
            $resp->subpartida = FG::numberFormat($resp->subpartida); // number_format($resp->subpartida, 2, '.', '');
            foreach ($resp->detalle as $k => $o) {
                $resp->detalle[$k]->parcial = FG::numberFormat($o->parcial);
                $resp->detalle[$k]->precio = FG::numberFormat($o->precio);
            }
        } else {
            $resp->success = false;
            $resp->message = 'Error no hay datos de partida';
        }
        return $resp;
    }

    public function getListAllApus($request)
    {
        $resp = new stdClass();
        $proyectos_generales_id = $request->proyectos_generales_id;
        $subpresupuestos_id = $request->subpresupuestos_id;
        $result = $this->getApusAllPartida($proyectos_generales_id);
        $items = array();
        $presupuestos = array();
        if ($result->success) {
            $especificacionesTecnicas = new EspecificacionesTecnicas($request);
            $presupuestos = $especificacionesTecnicas->getItemsPresupuestos($proyectos_generales_id, $subpresupuestos_id);
            $resp->success = true;
            $keys_apus = array();
            foreach (@$result->apus as $key => $value) {
                $keys_apus[$value->presupuestos_id][] = $value;
            }
            $keys_items = [];
            foreach (@$result->cabeceras as $key => $cabecera) {
                if (isset($keys_apus[$cabecera->presupuestos_id])) {
                    $apus = $keys_apus[$cabecera->presupuestos_id];
                    $rs = $this->performCalculations($cabecera, $apus);
                    $keys_items[$cabecera->presupuestos_id] = $rs;
                    $rs->mano_obra = FG::numberFormat($rs->mano_obra); // number_format($rs->mano_obra, 2, '.', '');
                    $rs->materiales = FG::numberFormat($rs->materiales); // number_format($rs->materiales, 2, '.', '');
                    $rs->herramienta_equipos = FG::numberFormat($rs->herramienta_equipos); // number_format($rs->herramienta_equipos, 2, '.', '');
                    $rs->subcontrato = FG::numberFormat($rs->subcontrato); // number_format($rs->subcontrato, 2, '.', '');
                    $rs->subpartida = FG::numberFormat($rs->subpartida); // number_format($rs->subpartida, 2, '.', '');
                }
            }
            foreach (@$presupuestos as $key => $item) {
                if (isset($keys_items[$item->id])) {
                    $presupuestos[$key]->apu = $keys_items[$item->id];
                }
            }
        } else {
            $resp->success = false;
            $resp->message = 'Error no hay datos de partida';
        }
        $resp->presupuestos = $presupuestos;
        $resp->items = $items;
        return $resp;
    }

    public function getListSubpartidas($request)
    {
        $resp = new stdClass();
        $proyectos_generales_id = $request->proyectos_generales_id;
        $subpresupuestos_id = $request->subpresupuestos_id;
        $result = $this->getApusAllSubPartida($proyectos_generales_id);
        $items = array();
        $presupuestos = array();
        if ($result->success) {
            $especificacionesTecnicas = new EspecificacionesTecnicas($request);
            $presupuestos = $especificacionesTecnicas->getItemsPresupuestos($proyectos_generales_id, $subpresupuestos_id);
            $resp->success = true;
            $keys_apus = array();
            foreach (@$result->apus as $key => $value) {
                $keys_apus[$value->presupuestos_id][] = $value;
            }
            $keys_items = [];
            foreach (@$result->cabeceras as $key => $cabecera) {
                if (isset($keys_apus[$cabecera->presupuestos_id])) {
                    $apus = $keys_apus[$cabecera->presupuestos_id];
                    $rs = $this->performCalculations($cabecera, $apus);
                    $rs->mano_obra = FG::numberFormat($rs->mano_obra); // number_format($rs->mano_obra, 2, '.', '');
                    $rs->materiales = FG::numberFormat($rs->materiales); // number_format($rs->materiales, 2, '.', '');
                    $rs->herramienta_equipos = FG::numberFormat($rs->herramienta_equipos); // number_format($rs->herramienta_equipos, 2, '.', '');
                    $rs->subcontrato = FG::numberFormat($rs->subcontrato); // number_format($rs->subcontrato, 2, '.', '');
                    $rs->subpartida = FG::numberFormat($rs->subpartida); // number_format($rs->subpartida, 2, '.', '');
                    $keys_items[$cabecera->presupuestos_id] = $rs;
                }
            }
            $mypresupuestos = array();
            foreach (@$presupuestos as $key => $item) {
                if (isset($keys_items[$item->id])) {
                    $presupuestos[$key]->apu = $keys_items[$item->id];
                    array_push($mypresupuestos, $presupuestos[$key]);
                }
            }
            $presupuestos = $mypresupuestos;
        } else {
            $resp->success = false;
            $resp->message = 'Error no hay datos de partida';
        }
        $resp->presupuestos = $presupuestos;
        $resp->items = $items;
        return $resp;
    }

    public function getUpdateGames($request)
    {
        $subpartida_id = $request->subpartida_id;
        $presupuestos_id = $request->presupuestos_id;
        $where = 'WHERE pp.presupuestos_id = :id AND pp.subpartida_id IS NULL';
        $id = $presupuestos_id;
        if ($subpartida_id) {
            $where = 'WHERE pp.subpartida_id = :id';
            $id = $subpartida_id;
        }
        $sql = "SELECT
                    pp.id,
                    pp.rendimiento,
                    pp.rendimiento_unid,
                    pp.presupuestos_id,
                    pp.proyectos_generales_id,
                    pp.subpartida_id,
                    pg.jornada_laboral
                FROM presupuestos_partida pp
                INNER JOIN proyecto_generales pg ON pp.proyectos_generales_id = pg.id
                {$where}";
        $cabecera = self::fetchObj($sql, ['id' => $id]);
        if ($cabecera) {
            $sql = "SELECT pp.id,
                    pp.cuadrilla,
                    pp.cantidad,
                    pp.unidad_medidas_id,                        
                    pp.presupuestos_id,
                    pp.partida_id,
                    pp.subpartida_id,
                    pp.precio AS punit,
                    ip.id AS insumo_id,
                    ip.tipo, ip.insumos, ip.precio,
                    um.alias, um.apu_cantidad
                FROM apus_partida_presupuestos pp
                LEFT JOIN insumos_proyecto ip ON pp.insumo_id = ip.id
                INNER JOIN unidad_medidas um ON um.id = pp.unidad_medidas_id
                {$where} AND pp.deleted_at IS NULL";
            $apus = self::fetchAllObj($sql, ['id' => $id]);
            $resp = $this->performCalculations($cabecera, $apus, false);
            $this->updateHeaderApuPartida($resp, $presupuestos_id, $subpartida_id);
        }
    }

    private function performCalculations($cabecera, $apus, $isdata = true)
    {
        $resp = new stdClass();
        $presupuesto_id = $cabecera->presupuestos_id;
        $rend = $cabecera->rendimiento ? $cabecera->rendimiento : 0;
        $jorn = $cabecera->jornada_laboral;

        $mototal = 0;
        foreach ($apus as $key => $e) {
            if (strtolower($e->tipo) == 'mo') {
                $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                $precio = $e->precio ? $e->precio : 0;
                $apus[$key]->cantidad = $rend ? number_format((($cuadrilla * $jorn) / $rend), 4, '.', '') : 0.0000;
                $parcial = ($apus[$key]->cantidad * $precio);
                $apus[$key]->parcial = $parcial; // number_format($parcial, 2, '.', '');
                $mototal += $parcial;
            }
        }

        $eqtotal = 0;
        foreach ($apus as $key => $e) {
            if (strtolower($e->tipo) == 'eq') {
                if ($e->apu_cantidad) {
                    $apus[$key]->precio = $mototal;
                    $apus[$key]->cuadrilla = '';
                } else {
                    $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                    $apus[$key]->cantidad = $rend ? number_format((($cuadrilla * $jorn) / $rend), 4, '.', '') : 0.0000;
                }
                $cantidad = $apus[$key]->cantidad ? $apus[$key]->cantidad : 0.0000;
                $precio = $apus[$key]->precio ? $apus[$key]->precio : 0;
                $parcial = ($cantidad * $precio);
                $apus[$key]->parcial = $parcial; // number_format($parcial, 2, '.', '');
                $apus[$key]->precio = $precio; // number_format($precio, 2, '.', '');
                $eqtotal += $parcial;
            }
        }

        $sptotal = 0;
        $sctotal = 0;
        $mttotal = 0;

        foreach ($apus as $key => $e) {
            if ($e->partida_id) {
                $precio = $e->punit ? $e->punit : 0.00;
                $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                $parcial = ($cantidad * $precio);
                $apus[$key]->parcial = $parcial; // number_format($parcial, 2, '.', '');
                $sptotal += $parcial;
                $apus[$key]->tipo = 'SP';
            }
            if (strtolower($e->tipo) == 'sc') {
                $precio = $e->precio ? $e->precio : 0.00;
                $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                if ($e->apu_cantidad) {
                    $alias = strtolower($e->alias);
                    $precio = $alias == '%mo' ? $mototal : $eqtotal;
                }
                $parcial = ($cantidad * $precio);
                $apus[$key]->parcial = $parcial; // number_format($parcial, 2, '.', '');
                $apus[$key]->precio = $precio;
                $sctotal += $parcial;
            }
            if (strtolower($e->tipo) == 'mt') {
                $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                $precio = $e->precio ? $e->precio : 0;
                if ($e->apu_cantidad) {
                    $alias = strtolower($e->alias);
                    $precio = $alias == '%mo' ? $mototal : $eqtotal;
                }
                $parcial = ($cantidad * $precio);
                $apus[$key]->parcial = $parcial; // number_format($parcial, 2, '.', '');
                $apus[$key]->cuadrilla = '';
                $mttotal += $parcial;
            }
        }

        $resp->id = $cabecera->id;
        $resp->rendimiento = $rend;
        $resp->rendimiento_unid = $cabecera->rendimiento_unid;
        $resp->presupuestos_id = $presupuesto_id;
        if (isset($cabecera->partida)) {
            $resp->partida = $cabecera->partida;
        }
        $resp->mano_obra = $mototal;
        $resp->materiales = $mttotal;
        $resp->herramienta_equipos = $eqtotal;
        $resp->subcontrato = $sctotal;
        $resp->subpartida = $sptotal;
        if ($isdata) {
            $resp->detalle = $apus;
        }
        $resp->success = true;
        return $resp;
    }

    private function getApusPartida($presupuestos_id, $subpartida_id)
    {
        $resp = new stdClass();
        try {
            $where = 'WHERE pp.presupuestos_id = :id AND pp.subpartida_id IS NULL';
            $id = $presupuestos_id;
            if ($subpartida_id) {
                $where = 'WHERE pp.subpartida_id = :id';
                $id = $subpartida_id;
            }
            $sql = "SELECT
                        pp.id,
                        pp.rendimiento,
	                    pp.rendimiento_unid,
                        pp.presupuestos_id,
                        pp.proyectos_generales_id,
                        pp.subpartida_id,
                        pg.jornada_laboral,
                        pt.partida
                    FROM presupuestos_partida pp
                    INNER JOIN proyecto_generales pg ON pp.proyectos_generales_id = pg.id
                    LEFT JOIN partidas_proyecto pt ON pt.id = pp.partida_id
                    {$where}";
            $cabecera = self::fetchObj($sql, ['id' => $id]);
            if ($cabecera) {
                $sql = "SELECT pp.id,
                        pp.iu,
                        pp.cuadrilla,
                        pp.cantidad,
                        pp.unidad_medidas_id,                        
                        pp.presupuestos_id,
                        pp.partida_id,
                        pp.subpartida_id,
                        pp.precio AS punit,
                        ip.id AS insumo_id,
                        ip.tipo, ip.insumos, ip.precio,
                        um.alias, um.apu_cantidad, pt.partida
                    FROM apus_partida_presupuestos pp
                    LEFT JOIN insumos_proyecto ip ON pp.insumo_id = ip.id
                    LEFT JOIN partidas_proyecto pt ON pp.partida_id = pt.id
                    INNER JOIN unidad_medidas um ON um.id = pp.unidad_medidas_id
                    {$where} AND pp.deleted_at IS NULL";
                $apus = self::fetchAllObj($sql, ['id' => $id]);
                $resp->apus = $apus;
                $resp->cabecera = $cabecera;
                $resp->success = true;
            } else {
                $resp->success = false;
            }
        } catch (\Throwable $th) {
            $resp->success = false;
        }
        return $resp;
    }

    private function getApusAllSubPartida($proyectos_generales_id)
    {
        $resp = new stdClass();
        try {
            $sql = "SELECT
                        pp.id,
                        pp.rendimiento,
	                    pp.rendimiento_unid,
                        pp.presupuestos_id,
                        pp.proyectos_generales_id,
                        pp.subpartida_id,
                        pg.jornada_laboral,
                        pt.partida
                    FROM presupuestos_partida pp
                    INNER JOIN proyecto_generales pg ON pp.proyectos_generales_id = pg.id
                    LEFT JOIN partidas_proyecto pt ON pt.id = pp.partida_id
                    WHERE pp.proyectos_generales_id = :id AND pp.subpartida_id IS NULL";
            $cabeceras = self::fetchAllObj($sql, ['id' => $proyectos_generales_id]);
            if (count($cabeceras)) {
                $presupuestos_ids = array();
                foreach ($cabeceras as $key => $item) {
                    $presupuestos_ids[] = $item->presupuestos_id;
                }
                $queryIn = implode(',', $presupuestos_ids);
                $sql = "SELECT pp.id,
                        pp.cuadrilla,
                        pp.cantidad,
                        pp.unidad_medidas_id,                        
                        pp.presupuestos_id,
                        pp.partida_id,
                        pp.subpartida_id,
                        pp.precio AS punit,
                        ip.id AS insumo_id,
                        ip.tipo, ip.insumos, ip.precio,
                        um.alias, um.apu_cantidad, pt.partida
                    FROM apus_partida_presupuestos pp
                    LEFT JOIN insumos_proyecto ip ON pp.insumo_id = ip.id
                    LEFT JOIN partidas_proyecto pt ON pp.partida_id = pt.id
                    INNER JOIN unidad_medidas um ON um.id = pp.unidad_medidas_id
                    WHERE pp.presupuestos_id IN ({$queryIn}) AND pp.subpartida_id IS NULL
                    AND ip.tipo IS NULL
                    AND pp.deleted_at IS NULL
                    GROUP BY pp.partida_id";
                $apus = self::fetchAllObj($sql);

                foreach ($apus as $key => $item) {
                    $result = $this->getApusPartida($item->presupuestos_id, $item->id);
                    if ($result->success) {
                        $rs = $this->performCalculations($result->cabecera, $result->apus);
                        $rs->mano_obra = FG::numberFormat($rs->mano_obra); // number_format($rs->mano_obra, 2, '.', '');
                        $rs->materiales = FG::numberFormat($rs->materiales); // number_format($rs->materiales, 2, '.', '');
                        $rs->herramienta_equipos = FG::numberFormat($rs->herramienta_equipos); // number_format($rs->herramienta_equipos, 2, '.', '');
                        $rs->subcontrato = FG::numberFormat($rs->subcontrato); // number_format($rs->subcontrato, 2, '.', '');
                        $rs->subpartida = FG::numberFormat($rs->subpartida); // number_format($rs->subpartida, 2, '.', '');
                        $apus[$key]->children = $rs;
                    }
                }

                $resp->apus = $apus;
                $resp->cabeceras = $cabeceras;
                $resp->success = true;
            } else {
                $resp->success = false;
            }
        } catch (\Throwable $th) {
            $resp->success = false;
        }
        return $resp;
    }

    private function getApusAllPartida($proyectos_generales_id)
    {
        $resp = new stdClass();
        try {
            $sql = "SELECT
                        pp.id,
                        pp.rendimiento,
	                    pp.rendimiento_unid,
                        pp.presupuestos_id,
                        pp.proyectos_generales_id,
                        pp.subpartida_id,
                        pg.jornada_laboral,
                        pt.partida
                    FROM presupuestos_partida pp
                    INNER JOIN proyecto_generales pg ON pp.proyectos_generales_id = pg.id
                    LEFT JOIN partidas_proyecto pt ON pt.id = pp.partida_id
                    WHERE pp.proyectos_generales_id = :id AND pp.subpartida_id IS NULL";
            $cabeceras = self::fetchAllObj($sql, ['id' => $proyectos_generales_id]);
            if (count($cabeceras)) {
                $presupuestos_ids = array();
                foreach ($cabeceras as $key => $item) {
                    $presupuestos_ids[] = $item->presupuestos_id;
                }
                $queryIn = implode(',', $presupuestos_ids);
                $sql = "SELECT pp.id,
                        pp.cuadrilla,
                        pp.cantidad,
                        pp.unidad_medidas_id,                        
                        pp.presupuestos_id,
                        pp.partida_id,
                        pp.subpartida_id,
                        pp.precio AS punit,
                        ip.id AS insumo_id,
                        ip.tipo, ip.insumos, ip.precio,
                        um.alias, um.apu_cantidad, pt.partida
                    FROM apus_partida_presupuestos pp
                    LEFT JOIN insumos_proyecto ip ON pp.insumo_id = ip.id
                    LEFT JOIN partidas_proyecto pt ON pp.partida_id = pt.id
                    INNER JOIN unidad_medidas um ON um.id = pp.unidad_medidas_id
                    WHERE pp.presupuestos_id IN ({$queryIn}) AND pp.subpartida_id IS NULL
                    AND pp.deleted_at IS NULL";
                $apus = self::fetchAllObj($sql);
                $resp->apus = $apus;
                $resp->cabeceras = $cabeceras;
                $resp->success = true;
            } else {
                $resp->success = false;
            }
        } catch (\Throwable $th) {
            $resp->success = false;
        }
        return $resp;
    }

    public function getCabSave($request)
    {
        try {
            if ($request->id) {
                $var = [];
                $medida = [];
                if ($request->rendimiento) {
                    $var['rendimiento'] = $request->rendimiento;
                }
                if ($request->rendimiento_unid) {
                    $unid = str_replace("/DÍA", "", $request->rendimiento_unid);
                    $unid = strtoupper($unid);
                    $medida = $this->findOrCreateUnid($unid);
                    if ($request->subpartida_id) {
                        self::update("apus_partida_presupuestos", array(
                            'unidad_medidas_id' => $medida->id
                        ), array('id' => $request->subpartida_id));
                        $var['rendimiento_unid'] = "$unid/DÍA";
                    } else {
                        self::update("presupuestos", array(
                            'unidad_medidas_id' => $medida->id
                        ), array('id' => $request->presupuestos_id));
                        $var['rendimiento_unid'] = "$unid/DÍA";
                    }
                }
                if (!empty($var)) {
                    self::update("presupuestos_partida", $var, ['id' => $request->id]);
                }
                $var['medida'] = $medida;
                $resp['success'] = true;
                $resp['message'] = 'Datos actualizados';
                $resp['data'] = $var;
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Presupusto no encontrado';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    private function updateHeaderApuPartida($resp, $presupuestos_id, $subpartida_id)
    {
        $cu = $resp->mano_obra + $resp->materiales + $resp->herramienta_equipos + $resp->subcontrato + $resp->subpartida;
        if ($subpartida_id) {
            self::update("apus_partida_presupuestos", array(
                'precio' => $cu, // number_format($cu, 2, '.', ''),
            ), array(
                'id' => $subpartida_id
            ));
            $sql = 'SELECT subpartida_id FROM apus_partida_presupuestos WHERE id = :id AND deleted_at IS NULL';
            $result = self::fetchObj($sql, ['id' => $subpartida_id]);
            if ($result && $result->subpartida_id) {
                $request = new stdClass();
                $request->subpartida_id = $result->subpartida_id;
                $request->presupuestos_id = $presupuestos_id;
                $this->getUpdateGames($request);
            }
        } else {
            self::update("presupuestos", array(
                'cu' => $cu, // number_format($cu, 2, '.', ''),
                'mo' => $resp->mano_obra, // number_format($resp->mano_obra, 2, '.', ''),
                'mt' => $resp->materiales, // number_format($resp->materiales, 2, '.', ''),
                'eq' => $resp->herramienta_equipos, // number_format($resp->herramienta_equipos, 2, '.', ''),
                'sc' => $resp->subcontrato, // number_format($resp->subcontrato, 2, '.', ''),
                'sp' => $resp->subpartida, // number_format($resp->subpartida, 2, '.', ''),
            ), array(
                'id' => $presupuestos_id
            ));
        }
    }

    public function getDelete($request)
    {
        $resp = [];
        try {
            $sql = 'SELECT presupuestos_id, subpartida_id, partida_id FROM apus_partida_presupuestos WHERE id = :id AND deleted_at IS NULL';
            $detalleInsumos = self::fetchObj($sql, ['id' => $request->id]);
            if ($detalleInsumos) {
                $this->removerRecursive($request->id, $detalleInsumos->partida_id);
                $args = new stdClass();
                $args->subpartida_id = $detalleInsumos->subpartida_id;
                $args->presupuestos_id = $detalleInsumos->presupuestos_id;
                $this->getUpdateGames($args);
                $resp['success'] = true;
                $resp['message'] = 'Apu eliminado';
                $resp['data'] = $request->id;
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Apu no existe';
            }
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th;
        }
        return $resp;
    }

    private function findOrCreateUnid($unid)
    {
        $sql = 'SELECT id, descripcion, alias, apu_cantidad FROM unidad_medidas WHERE LOWER(alias) = LOWER(:unid) OR LOWER(descripcion) = LOWER(:unid)';
        $resp = self::fetchObj($sql, ['unid' => $unid]);
        if (empty($resp)) {
            $var_un = [
                "descripcion" => $unid,
                "alias" => $unid,
            ];
            $insert = self::insert("unidad_medidas", $var_un);
            $resp = new stdClass();
            $resp->id = $insert['lastInsertId'];
            $resp->descripcion = $unid;
            $resp->alias = $unid;
        }
        return $resp;
    }

    private function getCodeSupply($proyectos_generales_id)
    {
        $code = '0001';
        $sql = "SELECT codigo, CONVERT(codigo, SIGNED INTEGER) AS maximo
        FROM insumos_proyecto
        WHERE proyectos_generales_id = :proyecto_id AND codigo REGEXP '^[0-9]+$'
        ORDER BY maximo DESC LIMIT 1";
        $resp = self::fetchObj($sql, ['proyecto_id' => $proyectos_generales_id]);
        if ($resp) {
            $code = ($resp->codigo * 1) + 1;
            $code = sprintf("%'.04d", $code);
        }
        return $code;
    }

    private function removerRecursive($id, $partida_id)
    {
        $deleted_at = FG::getFechaHora();
        self::ex("UPDATE apus_partida_presupuestos SET deleted_at = '{$deleted_at}' WHERE id = {$id} OR subpartida_id = {$id}");
        if ($partida_id) {
            $sql = 'SELECT id, partida_id FROM apus_partida_presupuestos WHERE subpartida_id = :id AND partida_id IS NOT NULL';
            $detalleInsumos = self::fetchAllObj($sql, ['id' => $id]);
            foreach ($detalleInsumos as $key => $value) {
                $this->removerRecursive($value->id, $value->partida_id);
            }
        }
    }
}
