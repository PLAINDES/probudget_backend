<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/PartidaDetail.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\PartidaDetail;
use stdClass;

class SubpartidaProyecto extends Mysql
{
    public function save($request)
    {
        $response = [];
        try {
            $partida_id = 0;
            $master_partidas_id = 0;
            if ($request->id == '0' && $request->masterid == '0') {
                $var = [
                    'partida' => $request->partida,
                    'rendimiento_unid' => $request->rendimiento_unid,
                    'unidad_medidas_id' => $request->unidad_medidas_id,
                    'proyectos_generales_id' => $request->proyectos_generales_id,
                ];
                if ($request->rendimiento) {
                    $rend = $var['rendimiento'] = number_format($request->rendimiento, 2, '.', '');
                }
                $lastInsert = self::insert("partidas_proyecto", $var);
                $partida_id = $lastInsert["lastInsertId"];
            } else {
                if ($request->id == '0' && $request->masterid != '0') {
                    $sql = 'SELECT id, partida, rendimiento, rendimiento_unid, unidad_medidas_id 
                            FROM partidas_proyecto WHERE master_partida_id = :id AND proyectos_generales_id = :proyectos_generales_id';
                    $partida = self::fetchObj($sql, ['id' => $request->masterid, 'proyectos_generales_id' => $request->proyectos_generales_id]);
                    $rend =  number_format(0, 2, '.', '');
                    if (empty($partida)) {
                        $sql = 'SELECT id, partida, rendimiento, rendimiento_unid, unidad_medidas_id FROM partidas WHERE id = :id';
                        $partida = self::fetchObj($sql, ['id' => $request->masterid]);
                        if ($partida) {
                            $rend = number_format($partida->rendimiento, 2, '.', '');
                            $lastInsert = self::insert("partidas_proyecto", array(
                                'partida' => $partida->partida,
                                'rendimiento' => $rend,
                                'rendimiento_unid' => $partida->rendimiento_unid,
                                'unidad_medidas_id' => $partida->unidad_medidas_id,
                                'proyectos_generales_id' => $request->proyectos_generales_id,
                                'master_partida_id' => $partida->id
                            ));
                            $partida_id = $lastInsert["lastInsertId"];
                            $master_partidas_id = $partida->id;
                        }
                    } else {
                        $partida_id = $partida->id;
                        $master_partidas_id = $request->masterid;
                    }
                    $request->rendimiento_unid = $partida->rendimiento_unid;
                    $request->unidad_medidas_id = $partida->unidad_medidas_id;
                    $request->rendimiento = $partida->rendimiento;
                } else {
                    $sql = 'SELECT id, rendimiento, rendimiento_unid, unidad_medidas_id FROM partidas_proyecto WHERE id = :id';
                    $partida = self::fetchObj($sql, ['id' => $request->id]);
                    if ($partida) {
                        $partida_id = $partida->id;
                        $request->unidad_medidas_id = $partida->unidad_medidas_id;
                        $request->rendimiento_unid = $partida->rendimiento_unid;
                        $request->rendimiento = $partida->rendimiento;
                    }
                }
            }
            if ($partida_id) {
                $exist = null;
                if ($request->subpartida_id) {
                    $sql = "SELECT id FROM apus_partida_presupuestos WHERE partida_id = :partida_id AND subpartida_id = :uuid AND deleted_at IS NULL";
                    $exist = self::fetchObj($sql, ['partida_id' => $partida_id, 'uuid' => $request->subpartida_id]);
                    if (!$exist) {
                        $sql = "SELECT id,partida_id FROM apus_partida_presupuestos WHERE id = :uuid AND deleted_at IS NULL";
                        $exist = self::fetchObj($sql, ['uuid' => $request->subpartida_id]);
                        if ($exist && $exist->partida_id == $partida_id) {
                            $response['success'] = false;
                            $response['message'] = 'Error la partida no puede incluirse';
                            return $response;
                        }
                        $exist = null;
                    }
                } else {
                    $sql = "SELECT id FROM apus_partida_presupuestos WHERE partida_id = :partida_id AND presupuestos_id = :uuid AND subpartida_id IS NULL AND deleted_at IS NULL";
                    $exist = self::fetchObj($sql, ['partida_id' => $partida_id, 'uuid' => $request->presupuestos_id]);
                    if (!$exist) {
                        $sql = "SELECT id FROM presupuestos WHERE partidas_id = :partida_id AND id = :uuid AND deleted_at IS NULL";
                        $exist = self::fetchObj($sql, ['partida_id' => $partida_id, 'uuid' => $request->presupuestos_id]);
                    }
                }
                if ($exist) {
                    $response['success'] = false;
                    $response['message'] = 'La partida ya esta asignada';
                    return $response;
                }
                $args = new stdClass();
                $args->cantidad = number_format(0, 2, '.', '');
                $args->partida_proyecto_id = $partida_id;
                $args->unidad_medidas_id = $request->unidad_medidas_id;
                $args->proyectos_generales_id = $request->proyectos_generales_id;
                $args->presupuestos_id = $request->presupuestos_id;
                $args->subpresupuestos_id = $request->subpresupuestos_id;
                $args->rendimiento_unid = $request->rendimiento_unid;
                $args->rendimiento = $request->rendimiento;
                $args->parent_subpartida_id = $request->subpartida_id;
                $subpartida_id = $this->saveSubpartida($args);
                if ($master_partidas_id) {
                    $args->master_partidas_id = $master_partidas_id;
                    $args->subpartida_id = $subpartida_id;
                    $this->saveApusPartida($args);
                }
                $response['success'] = true;
                $response['message'] = 'Partida guardada';
                $response['data'] = $args;
            } else {
                $response['success'] = false;
                $response['message'] = 'Error al guardar partida';
            }

            return $response;
        } catch (\Throwable $th) {
            $response['success'] = false;
            $response['message'] = 'Error al guardar partida';
        }
        return $response;
    }

    private function saveSubpartida($request)
    {
        $var = [
            'cantidad' => $request->cantidad,
            'partida_id' => $request->partida_proyecto_id,
            'unidad_medidas_id' => $request->unidad_medidas_id,
            'proyectos_generales_id' => $request->proyectos_generales_id,
            'presupuestos_id' => $request->presupuestos_id,
            'subpresupuestos_id' => $request->subpresupuestos_id
        ];
        if ($request->parent_subpartida_id) {
            $var['subpartida_id'] = $request->parent_subpartida_id;
        }
        $lastInsert = self::insert("apus_partida_presupuestos", $var);
        $request->subpartida_id = $lastInsert["lastInsertId"];
        $this->savePresupuestoPartida($request);
        return $lastInsert["lastInsertId"];
    }

    private function saveApusPartida($param)
    {
        $insumos = [];
        $sql = 'SELECT id, cuadrilla, cantidad, insumo_id, unidad_medidas_id FROM apus_partidas WHERE partida_id = :partida_id';
        $apus = self::fetchAllObj($sql, ['partida_id' => $param->master_partidas_id]);
        if (!empty($apus)) {
            foreach ($apus as $apu) {
                $insumoId = $this->findOrCreateInsumo($apu->insumo_id, $param->proyectos_generales_id);
                $var = array(
                    'insumo_id' => $insumoId->id,
                    'subpartida_id' => $param->subpartida_id,
                    'unidad_medidas_id' => $apu->unidad_medidas_id,
                    'proyectos_generales_id' => $param->proyectos_generales_id,
                    'presupuestos_id' => $param->presupuestos_id,
                    'subpresupuestos_id' => $param->subpresupuestos_id
                );
                if ($apu->cantidad) {
                    $var['cantidad'] = number_format($apu->cantidad, 4, '.', '');
                }
                if ($apu->cuadrilla) {
                    $var['cuadrilla'] = number_format($apu->cuadrilla, 2, '.', '');
                }
                self::insert("apus_partida_presupuestos", $var);
                $insumos[$apu->insumo_id] = $insumoId->insumo;
            }
            $sql = 'SELECT jornada_laboral FROM proyecto_generales WHERE id = :pgid';
            $pgeneral = self::fetchObj($sql, ['pgid' => $param->proyectos_generales_id]);

            $umedidas = [];
            $sql_um = "SELECT id, descripcion, alias, apu_cantidad FROM unidad_medidas";
            $unidadMedida = self::fetchAllObj($sql_um);
            foreach ($unidadMedida as $item) {
                $umedidas[$item->id] = $item;
            }
            $this->performCalculations($pgeneral, $apus, $insumos, $umedidas, $param);
        }
    }

    private function findOrCreateInsumo($masterInsumoId, $proyectos_generales_id)
    {
        $args = new stdClass();
        $sql = 'SELECT id, tipo, insumos, precio, unidad_medidas_id FROM insumos_proyecto 
        WHERE master_insumo_id = :insumoId AND proyectos_generales_id = :proyectos_generales_id';
        $insumo = self::fetchObj($sql, ['insumoId' => $masterInsumoId, 'proyectos_generales_id' => $proyectos_generales_id]);
        if (empty($insumo)) {
            $sql = 'SELECT id, codigo, iu, indice_unificado, tipo, insumos, precio, unidad_medidas_id FROM insumos WHERE id = :insumoId';
            $insumo = self::fetchObj($sql, ['insumoId' => $masterInsumoId]);
            $var = array(
                'codigo' => $insumo->codigo,
                'iu' => $insumo->iu,
                'indice_unificado' => $insumo->indice_unificado,
                'tipo' => $insumo->tipo,
                'insumos' => $insumo->insumos,
                'unidad_medidas_id' => $insumo->unidad_medidas_id,
                'master_insumo_id' => $insumo->id,
                'proyectos_generales_id' => $proyectos_generales_id
            );
            if ($insumo->precio) {
                $var['precio'] = number_format($insumo->precio, 2, '.', '');
            }
            $lastInsert = self::insert("insumos_proyecto", $var);
            $args->id = $lastInsert['lastInsertId'];
            $args->insumo = $insumo;
            return $args;
        } else {
            $args->id = $insumo->id;
            $args->insumo = $insumo;
            return $args;
        }
    }

    private function savePresupuestoPartida($request)
    {
        $var = [];
        $sql_a = 'SELECT id FROM presupuestos_partida 
                WHERE subpartida_id = :subpartida_id AND proyectos_generales_id = :proyectos_generales_id';
        $val_a = self::fetchObj($sql_a, ['subpartida_id' => $request->subpartida_id, 'proyectos_generales_id' => $request->proyectos_generales_id]);
        if ($request->rendimiento) {
            $var['rendimiento'] = $request->rendimiento;
        }
        if ($request->rendimiento_unid) {
            $var['rendimiento_unid'] = $request->rendimiento_unid;
        }
        if ($request->partida_proyecto_id) {
            $var['partida_id'] = $request->partida_proyecto_id;
        }
        if ($val_a) {
            if (!empty($var)) {
                self::update(
                    "presupuestos_partida",
                    $var,
                    ['subpartida_id' => $request->subpartida_id, 'proyectos_generales_id' => $request->proyectos_generales_id]
                );
            }
        } else {
            $var['subpartida_id'] = $request->subpartida_id;
            $var['presupuestos_id'] = $request->presupuestos_id;
            $var['proyectos_generales_id'] = $request->proyectos_generales_id;
            self::insert("presupuestos_partida", $var);
        }
    }

    private function performCalculations($pgeneral, $apus, $insumos, $unidadMedida, $request)
    {
        $resp = new stdClass();
        $rend = $request->rendimiento ? $request->rendimiento : 0;
        $jorn = $pgeneral->jornada_laboral;

        $mototal = 0;
        foreach ($apus as $key => $e) {
            if (!$insumos[$e->insumo_id]) {
                continue;
            }
            $insumo = $insumos[$e->insumo_id];
            if (strtolower($insumo->tipo) == 'mo') {
                $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                $precio = $insumo->precio ? $insumo->precio : 0;
                $cantidad = $rend ? number_format((($cuadrilla * $jorn) / $rend), 4, '.', '') : 0.0000;
                $parcial = ($cantidad * $precio);
                $mototal += $parcial;
            }
        }

        $sptotal = 0;
        $sctotal = 0;
        $mttotal = 0;
        $eqtotal = 0;

        foreach ($apus as $key => $e) {
            if (!$insumos[$e->insumo_id]) {
                continue;
            }
            $insumo = $insumos[$e->insumo_id];
            if (strtolower($insumo->tipo) == 'sc') {
                $precio = $insumo->precio ? $insumo->precio : 0.00;
                $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                $parcial = ($cantidad * $precio);
                $sctotal += $parcial;
            }
            if (strtolower($insumo->tipo) == 'mt') {
                $cantidad = $e->cantidad ? $e->cantidad : 0.0000;
                $precio = $insumo->precio ? $insumo->precio : 0;
                $parcial = ($cantidad * $precio);
                $mttotal += $parcial;
            }
            if (strtolower($insumo->tipo) == 'eq') {
                $um = $unidadMedida[$e->unidad_medidas_id];
                $precio = 0;
                $cantidad = 0;
                if ($um->apu_cantidad) {
                    $precio = $mototal;
                    $cantidad = $apus[$key]->cantidad ? $apus[$key]->cantidad : 0.0000;
                } else {
                    $cuadrilla = $e->cuadrilla ? $e->cuadrilla : 0;
                    $cantidad = $rend ? number_format((($cuadrilla * $jorn) / $rend), 4, '.', '') : 0.0000;
                    $precio = $insumo->precio ? $insumo->precio : 0;
                }
                $parcial = ($cantidad * $precio);
                $eqtotal += $parcial;
            }
        }
        $cu = $mototal + $mttotal + $eqtotal + $sctotal;
        self::update("apus_partida_presupuestos", array(
            'precio' => number_format($cu, 2, '.', ''),
        ), array(
            'id' => $request->subpartida_id
        ));
    }
}
