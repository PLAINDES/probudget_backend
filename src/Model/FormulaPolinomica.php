<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../src/DetalleInsumos.php');
//require_once(__DIR__ . '/../src/RecalculoPrespuesto.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\DetalleInsumos;
use App\Model\RecalculoPrespuesto;
use stdClass;

class FormulaPolinomica extends Mysql
{
    private $proyecto_general_id;
    private $subpresupuesto_id;

    public function getListIndices($request)
    {
        $resp = [];
        try {
            $datainsumos = $this->getListInsumos($request);
            $list = $datainsumos->list;
            $sublist = $datainsumos->sublist;
            $list_unif = $datainsumos->list_unif;
            $ppresupuesto = $datainsumos->ppresupuesto;

            $this->proyecto_general_id = $request->proyecto_general_id;
            $this->subpresupuesto_id = $request->subpresupuesto_id;

            $result = $this->assembleInsumos($list, $sublist, $list_unif, $ppresupuesto);
            $resp['success'] = true;
            $resp['message'] = 'Lista de insumos unificados';
            $resp['data'] = $result;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Ocurrió un erro al listar insumos';
        }
        return $resp;
    }

    public function getList($request)
    {
        $sql = 'SELECT * FROM formula_polinomica 
                WHERE subpresupuesto_id =:subpresupuesto_id AND proyecto_general_id =:proyecto_general_id';

        $formula = self::fetchAllObj($sql, [
            'subpresupuesto_id' => $request->subpresupuesto_id,
            'proyecto_general_id' => $request->proyecto_general_id
        ]);

        $mapformula = [];
        foreach ($formula as $e) {
            $mapformula[$e->monomio] = $e;
        }

        $resp = $this->getListInsumos($request);
        $list = $resp->list;
        $sublist = $resp->sublist;
        $list_unif = $resp->list_unif;
        $ppresupuesto = $resp->ppresupuesto;

        $sql = 'SELECT iu, monomio FROM pie_presupuesto_grupo 
                WHERE subpresupuestos_id =:subpresupuestos_id 
                AND proyectos_generales_id =:proyectos_generales_id';
        $pie_p_grupo = self::fetchObj($sql, [
            'subpresupuestos_id' => $request->subpresupuesto_id,
            'proyectos_generales_id' => $request->proyecto_general_id
        ]);

        $ius = [];
        foreach ($list_unif as $item) {
            $ius[$item->iu] = $item;
        }

        $result = $this->performCalculationsInsumos($list, $sublist);
        $filter = $result['data'];
        $direct_cost = $result['direct_cost'];
        $costs = ['GG' => 0, 'UT' => 0];

        foreach ($ppresupuesto as $item) {
            if ($item->pie_presupuesto_id == 2) {
                $costs['UT'] = $item->percentage;
            }
            if ($item->pie_presupuesto_id == 3) {
                $costs['GG'] = $item->percentage;
            }
        }

        $gg = round(($direct_cost * $costs['GG']), 2);
        $ut = round(($direct_cost * $costs['UT']), 2);
        $subtotal = $direct_cost + $gg + $ut;
        $insumoggu = new stdClass();
        $insumoggu->coeficiente = number_format(round((($gg + $ut) / $subtotal), 3), 3, '.', '');
        $insumoggu->add = true;
        $detail = [];
        $groups_iu = [];

        foreach ($filter as $e) {
            $coeficiente = 0;
            if ($subtotal) {
                $coeficiente = round(($e->monto_parcial_ppto / $subtotal), 3);
            }
            if ($e->iu) {
                if (isset($groups_iu[$e->iu])) {
                    $groups_iu[$e->iu]->coeficiente = $groups_iu[$e->iu]->coeficiente + $coeficiente;
                } else {
                    $e->coeficiente = $coeficiente;
                    $e->indice_unificado = '';
                    if (isset($ius[$e->iu])) {
                        $iu = $ius[$e->iu];
                        $e->indice_unificado = $iu->descripcion;
                    }
                    $groups_iu[$e->iu] = $e;
                }
                if (!$pie_p_grupo && $e->iuinsumo = 39 && $insumoggu->add) {
                    $coef = $groups_iu[$e->iu]->coeficiente + $insumoggu->coeficiente;
                    $groups_iu[$e->iu]->coeficiente = $coef;
                    $insumoggu->add = false;
                }
            }
        }

        if ($insumoggu->add) {
            /*$insumoggu->iu = NULL;
            $insumoggu->monomio = NULL;*/
            if ($pie_p_grupo && $pie_p_grupo->iu && isset($groups_iu[$pie_p_grupo->iu])) {
                $ggu_iu = $pie_p_grupo->iu;
                $coef = $groups_iu[$ggu_iu]->coeficiente + $insumoggu->coeficiente;
                $groups_iu[$ggu_iu]->coeficiente = $coef;
                //$insumoggu->iu = $pie_p_grupo->iu;
                //$insumoggu->monomio = $pie_p_grupo->monomio;
            }
            /*$insumoggu->id = 'ggu';
            $insumoggu->iuinsumo = 39;
            $insumoggu->indice_unificado = 'Gastos generales y utilidad';
            $groups_iu[39] = $insumoggu;*/
        }

        $groups_monomio = [];
        $factor_monomio = [];
        foreach ($groups_iu as $e) {
            if ($e->monomio) {
                if (isset($groups_monomio[$e->monomio])) {
                    $groups_monomio[$e->monomio] = $groups_monomio[$e->monomio] + 1;
                    $factor_monomio[$e->monomio] = $factor_monomio[$e->monomio] + $e->coeficiente;
                } else {
                    $groups_monomio[$e->monomio] = 1;
                    $factor_monomio[$e->monomio] = $e->coeficiente;
                }
            }
            array_push($detail, $e);
        }

        $formula = [];
        $fsimbol = [];
        $advertencia = false;
        foreach ($detail as $key => $e) {
            if ($e->monomio) {
                $detail[$key]->factor =  number_format($factor_monomio[$e->monomio], 3, '.', '');
                if ($detail[$key]->factor < 0.05) {
                    $advertencia = true;
                }
                if (!isset($fsimbol[$e->monomio])) {
                    array_push($formula, array(
                        'factor' => $detail[$key]->factor,
                        'simbolo' => $mapformula[$e->monomio]->simbolo,
                        'advertencia' => ($detail[$key]->factor < 0.05),
                        'monomio' => $e->monomio
                    ));
                    $fsimbol[$e->monomio] = $key;
                }
                $detail[$key]->simbolo = $mapformula[$e->monomio]->simbolo;
                $detail[$key]->advertencia = ($detail[$key]->factor < 0.05);
            }
            $detail[$key]->porcentaje = number_format(0, 3, '.', '');
            if (isset($detail[$key]->factor) && $detail[$key]->factor > 0) {
                $detail[$key]->porcentaje = round((($e->coeficiente / $detail[$key]->factor) * 100), 3);
                $detail[$key]->porcentaje = number_format($detail[$key]->porcentaje, 3, '.', '');
            }
            $detail[$key]->coeficiente = number_format($detail[$key]->coeficiente, 3, '.', '');
        }

        usort($detail, function ($a, $b) {
            return $a->monomio > $b->monomio;
        });

        usort($formula, function ($a, $b) {
            return $a->monomio > $b->monomio;
        });

        $response = [];
        $response['success'] = true;
        $response['message'] = 'Lista de insumos';
        $response['data'] = array(
            'detail' => $detail,
            'groups' => $groups_monomio,
            'formula' => $formula,
            'advertencia' => $advertencia
        );

        return $response;
    }

    public function getListInsumos($request)
    {
        $result = new stdClass();
        $sql = "SELECT id,iu,descripcion FROM indice_unificado";
        $list_unif = self::fetchAllObj($sql);
        $result->list_unif = $list_unif;

        $sql = "SELECT  ip.id, app.unidad_medidas_id, app.cantidad,
                        app.cuadrilla, ip.precio, ip.tipo, ip.insumos, ip.iu AS iuinsumo, um.alias,
                        um.apu_cantidad, app.presupuestos_id, pp.rendimiento,
                        pg.jornada_laboral, pt.metrado, app.iu, app.monomio
                FROM apus_partida_presupuestos app
                INNER JOIN insumos_proyecto ip ON app.insumo_id = ip.id
                INNER JOIN unidad_medidas um  ON ip.unidad_medidas_id = um.id
                INNER JOIN presupuestos_partida pp ON pp.presupuestos_id = app.presupuestos_id AND pp.subpartida_id IS NULL
                INNER JOIN presupuestos pt ON pp.presupuestos_id = pt.id
                INNER JOIN proyecto_generales pg ON app.proyectos_generales_id = pg.id
                WHERE app.proyectos_generales_id = :proyecto_generales_id AND app.subpresupuestos_id IN ({$request->subpresupuesto_id}) 
                AND app.partida_id IS NULL AND app.subpartida_id IS NULL AND app.deleted_at IS NULL";
        $list = self::fetchAllObj($sql, ['proyecto_generales_id' => $request->proyecto_general_id]);
        $result->list = $list;

        $sql = "SELECT  app.id AS apuid, ip.id, app.unidad_medidas_id,
                        app.cantidad, app.cuadrilla, app.precio AS sprecio,
                        ip.precio, ip.tipo, ip.insumos, ip.iu AS iuinsumo, um.alias, um.apu_cantidad,
                        app.presupuestos_id, app.partida_id, app.subpartida_id,pp.rendimiento, pg.jornada_laboral, 
                        app.iu, app.monomio, pt.metrado
                FROM apus_partida_presupuestos app
                LEFT JOIN insumos_proyecto ip ON app.insumo_id = ip.id
                LEFT JOIN unidad_medidas um  ON ip.unidad_medidas_id = um.id
                LEFT JOIN presupuestos_partida pp ON pp.subpartida_id = app.id OR pp.subpartida_id = app.subpartida_id
                INNER JOIN presupuestos pt ON app.presupuestos_id = pt.id
                INNER JOIN proyecto_generales pg ON app.proyectos_generales_id = pg.id
                WHERE app.proyectos_generales_id = :proyecto_generales_id AND app.subpresupuestos_id IN ({$request->subpresupuesto_id}) 
                AND (app.partida_id IS NOT NULL OR app.subpartida_id IS NOT NULL) AND app.deleted_at IS NULL";
        $sublist = self::fetchAllObj($sql, ['proyecto_generales_id' => $request->proyecto_general_id]);
        $result->sublist = $sublist;

        $sql = "SELECT pie_presupuesto_id, percentage FROM proyecto_pie_presupuesto 
                WHERE pie_presupuesto_id IN(2, 3) AND type_percentage = 'PIE' AND proyectos_generales_id =:proyecto_general_id";
        $ppresupuesto = self::fetchAllObj($sql, ['proyecto_general_id' => $request->proyecto_general_id]);

        $result->ppresupuesto = $ppresupuesto;

        return $result;
    }

    public function updateSimbol($request)
    {
        $resp = [];
        try {
            $sql = 'SELECT id FROM formula_polinomica WHERE subpresupuesto_id =:subpresupuesto_id AND proyecto_general_id =:proyecto_general_id AND monomio =:monomio';
            $result = self::fetchObj(
                $sql,
                ['subpresupuesto_id' => $request->subpresupuesto_id, 'proyecto_general_id' => $request->proyecto_generales_id, 'monomio' => $request->monomio]
            );

            if ($result) {
                $update = self::update('formula_polinomica', [
                    'simbolo' => $request->simbolo,
                ], ['id' => $result->id]);
            } else {
                $var = [
                    'monomio' => $request->monomio,
                    'proyecto_general_id' => $request->proyecto_generales_id,
                    'subpresupuesto_id' => $request->subpresupuesto_id
                ];
                if ($request->simbolo) {
                    $var['simbolo'] = $request->simbolo;
                }
                $insert = self::insert("formula_polinomica", $var);
            }
            $resp['success'] = true;
            $resp['message'] = 'Cambios asignados correctamente...';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function updateMonomio($request)
    {
        $resp = [];
        try {
            $sql = 'SELECT COUNT(1) AS valid FROM (SELECT iu FROM apus_partida_presupuestos
                WHERE subpresupuestos_id =:subpresupuestos_id AND proyectos_generales_id =:proyectos_generales_id AND monomio =:monomio
                GROUP BY iu) AS total';
            $result = self::fetchObj($sql, [
                'subpresupuestos_id' => $request->subpresupuesto_id,
                'proyectos_generales_id' => $request->proyecto_generales_id,
                'monomio' => $request->monomio
            ]);

            if ($result->valid >= 3) {
                $resp['success'] = false;
                $resp['message'] = 'Error máximo puede agrupar 3';
                return $resp;
            }

            /*$sql = 'SELECT COUNT(1) AS valid FROM (SELECT monomio FROM apus_partida_presupuestos
                WHERE subpresupuestos_id =:subpresupuestos_id AND proyectos_generales_id =:proyectos_generales_id AND monomio IS NOT NULL
                GROUP BY monomio) AS total';
            $result = self::fetchObj($sql,['subpresupuestos_id' => $request->subpresupuesto_id,
            'proyectos_generales_id' => $request->proyecto_generales_id]);

            if($result->valid >= 3) {
                $resp['success'] = false;
                $resp['message'] = 'Error máximo pueden haber 8 monomios';
                return $resp;
            }*/

            $update = self::update('apus_partida_presupuestos', [
                'monomio' => $request->monomio,
            ], [
                'subpresupuestos_id' => $request->subpresupuesto_id,
                'proyectos_generales_id' => $request->proyecto_generales_id,
                'iu' => $request->iu
            ]);

            $this->updateSimbol($request);

            $resp['success'] = true;
            $resp['message'] = 'Cambios asignados correctamente...';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function updateIndice($request)
    {
        $resp = [];
        try {
            $sql = "UPDATE apus_partida_presupuestos SET iu = {$request->iu} 
            WHERE subpresupuestos_id = {$request->subpresupuesto_id} AND proyectos_generales_id = {$request->proyecto_generales_id}";

            if ($request->insumo_id == 'ggu') {
                $sql .= " AND insumo_id = {$request->id}";
                self::ex($sql);
                $sql = 'SELECT id FROM pie_presupuesto_grupo WHERE subpresupuestos_id =:subpresupuestos_id AND proyectos_generales_id =:proyectos_generales_id';
                $pp_grupo = self::fetchObj($sql, [
                    'subpresupuestos_id' => $request->subpresupuesto_id,
                    'proyectos_generales_id' => $request->proyecto_generales_id
                ]);
                if ($pp_grupo) {
                    self::update("pie_presupuesto_grupo", ['iu' => $request->iu], ['id' => $pp_grupo->id]);
                } else {
                    $lastInsert = self::insert("pie_presupuesto_grupo", [
                        'iu' => $request->iu,
                        'subpresupuestos_id' => $request->subpresupuesto_id,
                        'proyectos_generales_id' => $request->proyecto_generales_id
                    ]);
                }
            } else {
                $sql .= " AND (insumo_id = {$request->insumo_id} OR insumo_id = {$request->id})";
                self::ex($sql);
            }
            $resp['success'] = true;
            $resp['message'] = 'Cambios asignados correctamente...';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    private function assembleInsumos($list, $sublist, $list_unif, $ppresupuesto)
    {

        $groupsKeys = [];
        $unifieds = [];
        $filters = [];
        $indiceUnificado39 = null;
        $siu = 39;
        $costosKeys = [];

        $detalleInsumos = new DetalleInsumos(false);
        $supplies = $detalleInsumos->assembleInsumos($list, $sublist);

        $presupuesto   = new RecalculoPrespuesto();
        $resultPie = $presupuesto->getPiePresupuesto(['id' => $this->proyecto_general_id]);
        $pies = $resultPie['data']['pie'];

        $pgg = 0;
        $put = 0;
        $pcd = 0;
        foreach ($pies as $key => $pie) {
            if (strtolower($pie['variable']) == 'gg') {
                $pgg = $pie['monto'] * 0.13;
            } elseif (strtolower($pie['variable']) == 'ut') {
                $put = $pie['monto'] * 0.12;
            } elseif (strtolower($pie['variable']) == 'cd') {
                $pcd = $pie['monto'];
            }
        }
        $subtotal = $pcd + ($pgg + $put);

        foreach ($supplies as $k => $o) {
            $groupsKeys[$o->iuinsumo][] = [
                'amount'    => $o->parcialNumber,
                'iu'        => $o->iu,
                'insumo_id' => $o->id
            ];
        }

        foreach ($list_unif as $k => $o) {
            if ($o->iu == $siu) {
                $indiceUnificado39 = $o;
            }
            if ($groupsKeys[$o->iu]) {
                $filters[] = $o;
                $monto = 0;
                $parent = null;
                foreach ($groupsKeys[$o->iu] as $k2 => $o2) {
                    $monto = $monto + $o2['amount'];
                    if ($o2['iu']) {
                        $parent = $o2['iu'];
                    }
                }
                if ($o->iu == $siu) {
                    $monto = $monto + ($pgg + $put);
                }
                $costo_inicial = $monto / $subtotal;
                if ($parent) {
                    $costosKeys[$parent][] = $costo_inicial;
                }
                $o->costo_inicial = $costo_inicial;
                $o->costo_final = $costo_inicial;
                $o->insumo_id = $groupsKeys[$o->iu][0]['insumo_id'];
                $o->parent = $parent;
                $o->monto = $monto;
                $o->grupo = 0;
                $unifieds[] = $o;
            }
        }

        if ($indiceUnificado39 && !isset($groupsKeys[$siu])) {
            $filters[] = $indiceUnificado39;
        }

        usort($filters, function ($a, $b) {
            return $a->iu > $b->iu;
        });

        $colors = range(0, 15);
        $index = 1;
        foreach ($filters as $k => $o) {
            $band = false;
            foreach ($unifieds as $k2 => $o2) {
                if ($o2->parent == $o->iu) {
                    $band = true;
                    $unifieds[$k2]->grupo = $index;
                }
            }
            if ($band) {
                $index = $index + 1;
            }
        }

        foreach ($unifieds as $k => $o) {
            if (isset($costosKeys[$o->parent])) {
                $costos = $costosKeys[$o->parent];
                $costo_final = 0;
                foreach ($costos as $k2 => $o2) {
                    $costo_final = $costo_final + $o2;
                }
                $o->costo_final = $costo_final;
            }
            $unifieds[$k]->costo_inicial = FG::numberFormat($o->costo_inicial, 3);
            $unifieds[$k]->costo_final = FG::numberFormat($o->costo_final, 3);
            $unifieds[$k]->monto = FG::numberFormat($o->monto);
        }

        usort($unifieds, function ($a, $b) {
            return $a->grupo > $b->grupo;
        });

        $response = new stdClass();
        $response->indices_unificados = $unifieds;
        return $response;
    }

    private function assembleInsumosOld($list, $sublist, $list_unif, $ppresupuesto)
    {
        $result = $this->performCalculationsInsumos($list, $sublist);
        $sql = 'SELECT iu FROM pie_presupuesto_grupo WHERE subpresupuestos_id =:subpresupuestos_id AND proyectos_generales_id =:proyectos_generales_id';
        $pie_p_grupo = self::fetchObj($sql, [
            'subpresupuestos_id' => $this->subpresupuesto_id,
            'proyectos_generales_id' => $this->proyecto_general_id
        ]);
        $filter = $result['data'];
        $direct_cost = $result['direct_cost'];
        $detail = [];
        $ius = [];
        $costs = ['GG' => 0, 'UT' => 0];

        foreach ($ppresupuesto as $item) {
            if ($item->pie_presupuesto_id == 2) {
                $costs['UT'] = $item->percentage;
            }
            if ($item->pie_presupuesto_id == 3) {
                $costs['GG'] = $item->percentage;
            }
        }

        foreach ($list_unif as $item) {
            $ius[$item->iu] = $item;
        }

        $groups = [];
        $cfinal = [];
        $listIU = [];
        $gg = round(($direct_cost * $costs['GG']), 2);
        $ut = round(($direct_cost * $costs['UT']), 2);
        $subtotal = $direct_cost + $gg + $ut;

        $insumoggu = new stdClass();
        $insumoggu->coef_initial = number_format(round((($gg + $ut) / $subtotal), 3), 3, '.', '');
        $insumoggu->monto_parcial_ppto = number_format(round(($gg + $ut), 2), 2, '.', '');
        $insumoggu->add = true;

        $indiceUnificado39 = self::fetchObj("SELECT E.* FROM indice_unificado E WHERE E.iu = :iu", ["iu" => 39]);

        foreach ($filter as $e) {
            if ($e->monto_parcial_ppto == 0) {
                continue;
            }
            $e->indice_unificado = '';
            $coef_initial = number_format(0, 3, '.', '');
            if ($subtotal) {
                $coef_initial = round(($e->monto_parcial_ppto / $subtotal), 3);
                $coef_initial = number_format($coef_initial, 3, '.', '');
            }
            $e->coef_initial = $coef_initial;
            $e->coef_final = $coef_initial;
            if (isset($ius[$e->iuinsumo])) {
                $e->indice_unificado = $ius[$e->iuinsumo]->descripcion;
            }
            $e->monto_parcial_ppto = number_format($e->monto_parcial_ppto, 2, '.', '');
            if ($e->iu) {
                if (isset($groups[$e->iu])) {
                    $groups[$e->iu] = $groups[$e->iu] + 1;
                    $cfinal[$e->iu] = $cfinal[$e->iu] + $e->coef_initial;
                } else {
                    $groups[$e->iu] = 1;
                    $cfinal[$e->iu] = $e->coef_initial;
                }
            }
            if (isset($detail[$e->iuinsumo])) {
                $detail[$e->iuinsumo]->coef_initial = $detail[$e->iuinsumo]->coef_initial + $e->coef_initial;
                $detail[$e->iuinsumo]->coef_final = $detail[$e->iuinsumo]->coef_initial;
                $detail[$e->iuinsumo]->monto_parcial_ppto = $detail[$e->iuinsumo]->monto_parcial_ppto + $e->monto_parcial_ppto;
            } else {
                $detail[$e->iuinsumo] = $e;
                if (isset($ius[$e->iuinsumo])) {
                    $iugroup = $ius[$e->iuinsumo];
                    $iugroup->insumo_id = $e->id;
                    array_push($listIU, $iugroup);
                }
            }
            if (!$pie_p_grupo && $e->iuinsumo == 39 && $insumoggu->add) {
                $detail[$e->iuinsumo]->coef_initial = $detail[$e->iuinsumo]->coef_initial + $insumoggu->coef_initial;
                $detail[$e->iuinsumo]->coef_final = $detail[$e->iuinsumo]->coef_initial;
                $detail[$e->iuinsumo]->monto_parcial_ppto = $detail[$e->iuinsumo]->monto_parcial_ppto + $insumoggu->monto_parcial_ppto;
                if ($e->iu) {
                    $groups[$e->iu] = $groups[$e->iu] + 1;
                    $cfinal[$e->iu] = $cfinal[$e->iu] + $insumoggu->coef_initial;
                }
                $insumoggu->add = false;
            }
        }

        if ($insumoggu->add) {
            $insumoggu->iu = null;
            if ($pie_p_grupo && $pie_p_grupo->iu && isset($groups[$pie_p_grupo->iu])) {
                $groups[$pie_p_grupo->iu] = $groups[$pie_p_grupo->iu] + 1;
                $cfinal[$pie_p_grupo->iu] = $cfinal[$pie_p_grupo->iu] + $insumoggu->coef_initial;
                $insumoggu->iu = $pie_p_grupo->iu;
            }
            $insumoggu->id = 'ggu';
            $insumoggu->iuinsumo = 39;
            $insumoggu->coef_final = $insumoggu->coef_initial;
            $insumoggu->indice_unificado = $indiceUnificado39->descripcion; // 'Gastos generales y utilidad';
            $detail[39] = $insumoggu;
        }

        foreach ($detail as $key => $item) {
            $detail[$key]->coef_initial = number_format($detail[$key]->coef_initial, 3, '.', '');
            $detail[$key]->coef_final = number_format($detail[$key]->coef_final, 3, '.', '');
            if ($item->iu) {
                $detail[$key]->coef_final = number_format($cfinal[$item->iu], 3, '.', '');
            }
        }

        if (isset($detail[39])) {
            $presupuesto   = new RecalculoPrespuesto();
            $resultPie = $presupuesto->getPiePresupuesto(['id' => $this->proyecto_general_id]);
            $pies = $resultPie['data']['pie'];
            $pgg = 0;
            $put = 0;
            foreach ($pies as $key => $pie) {
                if (strtolower($pie['variable']) == 'gg') {
                    $pgg = $pie['monto'] * 0.1312;
                } elseif (strtolower($pie['variable']) == 'ut') {
                    $put = $pie['monto'] * 0.12;
                }
            }
            $detail[39]->monto_parcial_ppto = $pgg + $put;
        }

        foreach ($detail as $k => $o) {
            $detail[$k]->monto_parcial_ppto = FG::numberFormat($o->monto_parcial_ppto);
        }

        $insert39 = true;
        foreach ($listIU as $k => $o) {
            if ($o->iu == $indiceUnificado39->iu) {
                $insert39 = false;
            }
        }
        if ($insert39) {
            array_push($listIU, $indiceUnificado39);
        }

        $response = new stdClass();
        usort($detail, function ($a, $b) {
            return $a->monto_parcial_ppto < $b->monto_parcial_ppto;
        });
        usort($detail, function ($a, $b) {
            return $a->iu > $b->iu;
        });
        $response->insumos = array(
            'detail' => array_values($detail),
            'groups' => $groups
        );
        usort($listIU, function ($a, $b) {
            return $a->iu > $b->iu;
        });
        $response->indices_unificados = $listIU;
        return $response;
    }

    private function performCalculationsInsumos($list, $sublist)
    {
        $direct_cost = 0;
        $result = [];

        $detalleInsumos = new DetalleInsumos(false);

        $filter = $detalleInsumos->performCalculations($list, 'presupuestos_id');

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

        if (isset($sublist['apus'])) {
            $apus = $sublist['apus'];
            $subpartidas = $sublist['subpartidas'];
            $subfilter = $detalleInsumos->performCalculations($apus, 'subpartida_id');
            foreach ($subpartidas as $sp => $value) {
                if (isset($subfilter[$sp])) {
                    $pid = $value->presupuestos_id;
                    foreach ($subfilter[$sp] as $key => $item) {
                        if (isset($filter[$pid][$key])) {
                            $metered = $filter[$pid][$key]->metrado ? $filter[$pid][$key]->metrado : 0.00;
                            if ($filter[$pid][$key]->apu_cantidad) {
                                $parcial = ($value->cantidad * $item->parcial);
                                $parcial = ($filter[$pid][$key]->parcial + $parcial) * $metered;
                                $filter[$pid][$key]->parcial = number_format(round($parcial, 2), 2, '.', '');
                            } else {
                                $cantidad = ($value->cantidad * $item->cantidad);
                                $cantidad = ($filter[$pid][$key]->cantidad + $cantidad) * $metered;
                                $filter[$pid][$key]->cantidad = number_format(round($cantidad, 4), 4, '.', '');
                                $parcial = $filter[$pid][$key]->cantidad * $filter[$pid][$key]->precio;
                                $filter[$pid][$key]->parcial = number_format(round($parcial, 2), 2, '.', '');
                            }
                        } else {
                            $filter[$pid][$key] = $item;
                        }
                    }
                }
            }
        }

        $filter = array_reduce($filter, function ($carry, $value) use (&$direct_cost) {
            foreach ($value as $key => $item) {
                $amount = $item->parcial * $item->metrado;
                if (isset($carry[$key])) {
                    $carry[$key]->monto_parcial_ppto = $carry[$key]->monto_parcial_ppto + $amount;
                } else {
                    $item->monto_parcial_ppto = $amount;
                    $carry[$key] = $item;
                }
                $direct_cost = $direct_cost + $amount;
            }
            return $carry;
        }, []);

        $result['data'] = $filter;
        $result['direct_cost'] = $direct_cost;

        return $result;
    }
}
