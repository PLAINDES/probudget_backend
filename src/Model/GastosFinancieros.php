<?php

namespace App\Model;

use App\Model\Persistence\Mysql;
use App\Model\RecalculoPrespuesto;
use App\Validators\GastosFinancierosValidator;

class GastosFinancieros extends Mysql
{
    public function __construct()
    {
        $this->recalculoPresupuesto = new RecalculoPrespuesto();
    }

    public function save($request)
    {
        try {
            $validation = GastosFinancierosValidator::validateSave(
                $request->paramsPost()->all()
            );
            if (!$validation['success']) {
                return $validation;
            }

            $tasa = (float) $request->tasa;
            $comisionBanco = (float) $request->comisionBanco;
            $periodoMeses = (float) $request->periodoMeses;
            $garantiaBancaria = (float) $request->garantiaBancaria;

            $proyectoGeneralesId = (int) $request->proyectoGeneralesId;
            $tipoGarantiaId = (int) $request->tipoGarantiaId;

            if (
                !$proyectoGeneralesId ||
                !$tipoGarantiaId
            ) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos'
                ];
            }

            $sql = 'SELECT id
                FROM gastos_financieros
                WHERE proyecto_generales_id = :proyecto_generales_id
                AND tipo_garantia_id = :tipo_garantia_id
                LIMIT 1';

            $registro = self::fetchObj($sql, [
                'proyecto_generales_id' => $proyectoGeneralesId,
                'tipo_garantia_id' => $tipoGarantiaId
            ]);

            $data = [
                'tasa' => $tasa,
                'comision_banco' => $comisionBanco,
                'periodo_meses' => $periodoMeses,
                'garantia_bancaria' => $garantiaBancaria,
            ];

            if ($registro) {
                self::update(
                    'gastos_financieros',
                    $data,
                    ['id' => $registro->id]
                );
            } else {
                $data['proyecto_generales_id'] = $proyectoGeneralesId;
                $data['tipo_garantia_id'] = $tipoGarantiaId;

                self::insert(
                    'gastos_financieros',
                    $data
                );
            }

            return [
                'success' => true,
                'message' => 'Cambios guardados correctamente'
            ];
        } catch (\Throwable $th) {
            error_log("Error al guardar gastos financieros: " . $th->getMessage());
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    public function getList($request)
    {
        try {
            $pie = $this->
                        recalculoPresupuesto->getPiePresupuesto(['id' => $request->proyectoGeneralesId])['data']['pie'];

            $costoDirecto = null;

            foreach ($pie as $item) {
                if ($item['descripcion'] === 'Costo Directo') {
                    $costoDirecto = $item['monto'];
                    break;
                }
            }

            // buscar gasto
            $sql = 'SELECT
                        tg.id AS tipo_garantia_id,
                        tg.name AS garantia,
                        tg.sort_order AS garantia_order,

                        gf.id,
                        gf.tasa,
                        gf.comision_banco,
                        gf.periodo_meses,
                        gf.garantia_bancaria,
                        gf.proyecto_generales_id

                    FROM tipo_garantia tg

                    LEFT JOIN gastos_financieros gf
                        ON gf.tipo_garantia_id = tg.id
                        AND gf.proyecto_generales_id = :proyecto_generales_id

                    ORDER BY tg.sort_order';

            $gastosFinancieros = self::fetchAllObj($sql, [
                'proyecto_generales_id' => $request->proyectoGeneralesId,
            ]);

            $data = [];
            $items = [];

            foreach ($gastosFinancieros as $gasto) {
                $tasa = (float) $gasto->tasa / 100;
                $comisionBanco = (float) $gasto->comision_banco / 100;
                $garantiaBancaria = (float) $gasto->garantia_bancaria / 100;
                $periodoMeses = (float) $gasto->periodo_meses;

                $montoCartaFianza = $costoDirecto * $tasa;

                $garantiaBancariaTotal =
                    $montoCartaFianza * $garantiaBancaria;

                $costoFinanciero =
                    $montoCartaFianza *
                    $comisionBanco *
                    ($periodoMeses / 12);

                $items[] = [
                    'gasto' => $gasto->garantia,
                    'garantia_order' => (int) $gasto->garantia_order,
                    'monto_carta_fianza' => round($montoCartaFianza, 2),
                    'garantia_bancaria_total' => round($garantiaBancariaTotal, 2),
                    'monto_aplicable' => round($costoDirecto, 2),
                    'costo_financiero' => round($costoFinanciero, 2),
                    'periodo_meses' => $periodoMeses,
                    'tasa' => (float) $gasto->tasa,
                    'comision_banco' => (float) $gasto->comision_banco,
                    'garantia_bancaria' => (float) $gasto->garantia_bancaria,
                    'proyecto_generales_id' => (int) $gasto->proyecto_generales_id,
                    'tipo_garantia_id' => (int) $gasto->tipo_garantia_id,
                ];
            }

            $data['items'] = $items;

            $totalGastosFinancieros = array_sum(
                array_column($data['items'], 'costo_financiero')
            );

            $data['total_gastos_financieros'] = $totalGastosFinancieros;

            return [
                'message' => '',
                'success' => true,
                'data' => $data
            ];
        } catch (\Throwable $th) {
            error_log("Error al obtener gastos financieros: " . $th->getMessage());
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }
}
