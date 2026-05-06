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

//require_once(__DIR__ . '/../persistence/Mysql.php');
//require_once (__DIR__ . '/../persistence/Mariadb.php');
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/Subcategoria.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\Subcategoria;

class RecalculoPrespuesto extends Mysql
{
    public function getRecalculoPrespuesto($presupuestos_proyecto_generales_id)
    {
        try {
            do {
                $sql_presupuestos = "SELECT id,presupuestos_proyecto_generales_id
                                    FROM presupuestos WHERE id = :presupuestos_proyecto_generales_id";
                $presupuestos_h = self::fetchObj($sql_presupuestos, ['presupuestos_proyecto_generales_id' => $presupuestos_proyecto_generales_id]);

                if ($presupuestos_h) {
                    $presupuestos_proyecto_generales_id = $presupuestos_h->presupuestos_proyecto_generales_id;
                    $sql = "SELECT   SUM(apu_mo) AS 'sum_apu_mo', 
                                     SUM(apu_mat) AS 'sum_apu_mat',
                                     SUM(apu_eq) AS 'sum_apu_eq',  
                                     SUM(metrado) AS 'sum_metrado_parcial',
                                     SUM(parcial) AS 'sum_parcial',
                                     SUM(apu_cu) AS 'sum_apu_cu' 
                            FROM presupuestos WHERE presupuestos_proyecto_generales_id = :id";
                    $presupuestos = self::fetchObj($sql, ['id' => $presupuestos_proyecto_generales_id]);
                    if ($presupuestos->sum_parcial) {
                        $parcial = $presupuestos->sum_parcial;
                    } else {
                        $parcial = (($presupuestos->sum_metrado_parcial) ? $presupuestos->sum_metrado_parcial : 0) * (($presupuestos->sum_apu_cu) ? $presupuestos->sum_apu_cu : 0);
                    }

                    if ($presupuestos->sum_apu_eq) {
                        if (is_nan($presupuestos->sum_apu_eq)) {
                            $presupuestos->sum_apu_eq =  0.00;
                        }
                    } else {
                        $presupuestos->sum_apu_eq = 0.00;
                    }

                    if ($presupuestos->sum_apu_mat) {
                        if (is_nan($presupuestos->sum_apu_mat)) {
                            $presupuestos->sum_apu_mat =  0.00;
                        }
                    } else {
                        $presupuestos->sum_apu_mat = 0.00;
                    }


                    if ($presupuestos->sum_apu_mo) {
                        if (is_nan($presupuestos->sum_apu_mo)) {
                            $presupuestos->sum_apu_mo =  0.00;
                        }
                    } else {
                        $presupuestos->sum_apu_mo = 0.00;
                    }


                    if ($presupuestos->sum_metrado_parcial) {
                        if (is_nan($presupuestos->sum_metrado_parcial)) {
                            $presupuestos->sum_metrado_parcial =  0.00;
                        }
                    } else {
                        $presupuestos->sum_metrado_parcial = 0.00;
                    }

                    if ($presupuestos->sum_apu_cu) {
                        if (is_nan($presupuestos->sum_apu_cu)) {
                            $presupuestos->sum_apu_cu =  0.00;
                        }
                    } else {
                        $presupuestos->sum_apu_cu = 0.00;
                    }

                    if ($parcial) {
                        if (is_nan($parcial)) {
                            $parcial =  0.00;
                        }
                    } else {
                        $parcial = 0.00;
                    }

                    if ($presupuestos->sum_parcial) {
                        $value = [
                                "parcial" => number_format($parcial, 2, '.', ''),
                            ];
                    } else {
                        $value = [
                            "apu_mo"  => number_format($presupuestos->sum_apu_mo, 2, '.', ''),
                            "apu_mat" => number_format($presupuestos->sum_apu_mat, 2, '.', ''),
                           "apu_eq" => number_format($presupuestos->sum_apu_eq, 2, '.', ''),
                           "metrado" => number_format($presupuestos->sum_metrado_parcial, 2, '.', ''),
                            "apu_cu" => number_format($presupuestos->sum_apu_cu, 2, '.', ''),
                            "parcial" => number_format($parcial, 2, '.', ''),
                        ];
                    }
                    self::update("presupuestos", $value, ['id' => $presupuestos_proyecto_generales_id ]);
                } else {
                    $presupuestos_proyecto_generales_id = 0;
                }
            } while ($presupuestos_proyecto_generales_id != 0);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getBudgetFooter($array = [])
    {
        try {
            if (array_key_exists('id', $array)) {
                $id = $array['id'];
                $sql_general = "SELECT metrado, cu, mo, mt, eq
                                    FROM presupuestos 
                                    WHERE proyecto_generales_id = :id AND type_item = 3 AND deleted_at is NULL";

                $presupuestos_general = self::fetchAllObj($sql_general, ['id' => $id]);

                $suma_ppa = 0;
                foreach ($presupuestos_general as $item) {
                    $metrado = $item->metrado ? $item->metrado : 0;
                    $cu = $item->cu ? $item->cu : 0;
                    $suma_ppa += ($metrado * $cu);
                }

                $proceso_calculo = $this->getFormulaPiePresupuesto($suma_ppa, $id);
                return $proceso_calculo;
            } else {
                return ["success" => false, "message" => "Especificar el proyecto"];
            }
        } catch (\Throwable $th) {
            return ["success" => false, "message" => "Ocurrio un problema"];
        }
    }

    public function getPiePresupuesto($array = [])
    {
        try {
            if (array_key_exists('id', $array)) {
                $response = [];
                $id = $array['id'];
                $sql_general = "SELECT metrado, cu, mo, mt, eq, sc
                                    FROM presupuestos 
                                    WHERE proyecto_generales_id = :id AND type_item = 3 AND deleted_at is NULL";

                $presupuestos_general = self::fetchAllObj($sql_general, ['id' => $id]);

                $suma_ppa = 0;
                foreach ($presupuestos_general as $item) {
                    $metrado = $item->metrado ? $item->metrado : 0;
                    $cu = $item->cu ? $item->cu : 0;
                    $suma_ppa += ($metrado * $cu);
                }

                $proceso_calculo = $this->getFormulaPiePresupuesto($suma_ppa, $id);
                $subcategoria = new Subcategoria(['id' => $id]);
                $subpresupuestos = $subcategoria->getSubPresupuestoParcial();

                $response['success'] = true;
                $response['message'] = 'List';
                $response['data'] = array(
                    'subpresupuestos' => $subpresupuestos,
                    'pie' => $proceso_calculo
                );

                return $response;
            } else {
                return ["success" => false, "message" => "Especificar el proyecto"];
            }
        } catch (\Throwable $th) {
            return ["success" => false, "message" => "Ocurrio un problema"];
        }
    }

    public function getFormulaPiePresupuesto($monto, $id)
    {
        $sql = "SELECT        
                ppo.id,
                ppo.variable,
                ppo.descripcion,
                ppo.formula,
                ppo.valor,
                ppo.iu,
                ppp.percentage
        FROM pie_presupuesto ppo
        LEFT JOIN proyecto_pie_presupuesto ppp 
        ON ppo.id = ppp.pie_presupuesto_id AND ppp.proyectos_generales_id = :id AND ppp.type_percentage = 'PIE'
        ORDER BY ppo.posicion ASC";
        $priPresupuesto = self::fetchAllObj($sql, ['id' => $id]);
        $ppa = [];

        foreach ($priPresupuesto as $key => $value) {
            if ($value->id == 1) {
                $array["descripcion"]  = $value->descripcion;
                $array["monto"]  = $monto;
                $array["variable"]  = $value->variable;
                $array["percentage"]  = $value->percentage;
                $array["id"]  = $value->id;
                $array["proyectos_generales_id"]  = $id;
            } else {
                $porciones = explode(";", $value->formula);
                if (count($porciones) == 3) {
                    if ($porciones[0] == "CD") {
                        $calculo_monto = $monto;
                    } else {
                        $found_key = array_search($porciones[0], array_column($ppa, 'variable'));
                        $calculo_monto = $ppa[$found_key]['monto'];
                    }

                    if (floatval($porciones[2]) || is_int($porciones[2])) {
                        $porcion_dos = $porciones[2];
                    } else {
                        $found_key = array_search($porciones[2], array_column($ppa, 'variable'));
                        $porcion_dos = $ppa[$found_key]['monto'];
                    }

                    switch ($porciones[1]) {
                        case '+':
                            // $array["monto"]  = (float) number_format(($calculo_monto + floatval($porcion_dos)),2,'.','');
                            $array["monto"]  = (float) ($calculo_monto + floatval($porcion_dos));
                            break;
                        case '-':
                            // $array["monto"]  = (float) number_format(($calculo_monto - floatval($porcion_dos) ),2,'.','');
                            $array["monto"]  = (float) ($calculo_monto - floatval($porcion_dos));
                            break;
                        case '*':
                            // $array["monto"]  = (float) number_format(($calculo_monto * floatval($porcion_dos)),2,'.','');
                            $array["monto"]  = (float) ($calculo_monto * floatval($porcion_dos));
                            break;
                        default:
                            // $array["monto"]  = (float) number_format(( $calculo_monto / floatval($porcion_dos)),2,'.','');
                            $array["monto"]  = (float) ($calculo_monto / floatval($porcion_dos));
                            break;
                    }
                } elseif (count($porciones) == 5) {
                    if ($porciones[0] == "CD") {
                        $calculo_monto = $monto;
                    } else {
                        $found_key = array_search($porciones[0], array_column($ppa, 'variable'));
                        $calculo_monto = $ppa[$found_key]['monto'];
                    }


                    if (floatval($porciones[2]) || is_int($porciones[2])) {
                        $porcion_dos = $porciones[2];
                    } else {
                        $found_key = array_search($porciones[2], array_column($ppa, 'variable'));
                        $porcion_dos = $ppa[$found_key]['monto'];
                    }

                    if (floatval($porciones[4]) || is_int($porciones[4])) {
                        $porcion_tres = $porciones[4];
                    } else {
                        $found_key = array_search($porciones[4], array_column($ppa, 'variable'));
                        $porcion_tres = $ppa[$found_key]['monto'];
                    }

                    switch ($porciones[1]) {
                        case '+':
                            // $array["monto"]  = (float) number_format(($calculo_monto + floatval($porcion_dos) + floatval($porcion_tres)),2);
                            $array["monto"]  = (float) ($calculo_monto + floatval($porcion_dos) + floatval($porcion_tres));
                            break;
                        case '-':
                            // $array["monto"]  = (float) number_format(($calculo_monto - floatval($porcion_dos) + floatval($porcion_tres)),2);
                            $array["monto"]  = (float) ($calculo_monto - floatval($porcion_dos) + floatval($porcion_tres));
                            break;
                        case '*':
                            // $array["monto"]  = (float) number_format(($calculo_monto * floatval($porcion_dos) + floatval($porcion_tres)),2);
                            $array["monto"]  = (float) ($calculo_monto * floatval($porcion_dos) + floatval($porcion_tres));
                            break;
                    }
                }
                $array["descripcion"]  = $value->descripcion;
                $array["variable"]  = $value->variable;
                $array["percentage"]  = $value->percentage;
                $array["id"]  = $value->id;
                $array["proyectos_generales_id"]  = $id;
            }
            array_push($ppa, $array);
        }
        return $ppa;
    }
}
