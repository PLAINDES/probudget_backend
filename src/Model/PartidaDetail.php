<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;

class PartidaDetail extends Mysql
{
    public function getSave($param)
    {
        if ($param['master_partidas_id']) {
            $sql = 'SELECT id, cuadrilla, cantidad, insumo_id, unidad_medidas_id FROM apus_partidas WHERE partida_id = :partida_id';
            $apus = self::fetchAllObj($sql, ['partida_id' => $param['master_partidas_id']]);
            if (!empty($apus)) {
                foreach ($apus as $apu) {
                    $insumoId = $this->findOrCreateInsumo($apu->insumo_id, $param['proyecto_generales_id']);
                    $var = array(
                        'insumo_id' => $insumoId,
                        'unidad_medidas_id' => $apu->unidad_medidas_id,
                        'proyectos_generales_id' => $param['proyecto_generales_id'],
                        'presupuestos_id' => $param['id'],
                        'subpresupuestos_id' => $param['subpresupuestos_id']
                    );
                    if ($apu->cuadrilla) {
                        $var['cuadrilla'] = number_format($apu->cuadrilla, 2, '.', '');
                    }
                    if ($apu->cantidad) {
                        $var['cantidad'] = number_format($apu->cantidad, 4, '.', '');
                    }
                    $lastInsert = self::insert("apus_partida_presupuestos", $var);
                }
            }
        }
    }

    private function findOrCreateInsumo($masterInsumoId, $proyectos_generales_id)
    {
        $sql = 'SELECT id FROM insumos_proyecto WHERE master_insumo_id = :insumoId AND proyectos_generales_id = :proyectos_generales_id';
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
            return $lastInsert['lastInsertId'];
        } else {
            return $insumo->id;
        }
    }
}
