<?php

namespace App\Model;

use App\Model\Persistence\Mysql;
use App\Model\GastosFinancieros;
use App\Model\DetalleInsumos;
use App\Model\GastosGenerales;

class Seguros extends Mysql
{
    public function __construct()
    {
        $this->gastosFinancieros = new GastosFinancieros();
    }

    public function save($request)
    {
        try {
            // Buscar el tipo_seguro_id según el código enviado
            $sql = 'SELECT id FROM tipos_seguro WHERE codigo = :codigo';
            $tipoSeguro = self::fetchObj($sql, [
                'codigo' => strtoupper($request->codigo)
            ]);

            if (!$tipoSeguro) {
                return ['success' => false, 'message' => 'Tipo de seguro no encontrado'];
            }

            // Ver si ya existe un registro para este proyecto y tipo
            $sql = 'SELECT id FROM seguros
                    WHERE proyecto_generales_id = :proyecto_generales_id
                    AND tipo_seguro_id = :tipo_seguro_id';
            $existing = self::fetchObj($sql, [
                'proyecto_generales_id' => $request->proyecto_generales_id,
                'tipo_seguro_id'        => $tipoSeguro->id
            ]);

            // Armar los datos según el código
            $datos = [];

            switch (strtoupper($request->codigo)) {
                case 'SCTR':
                    $datos = [
                        'tasaSaludObreros'   => $request->tasaSaludObreros   ?? null,
                        'tasaSaludEmpleados' => $request->tasaSaludEmpleados ?? null,
                        'tasaPension'        => $request->tasaPension        ?? null,
                        'periodoMeses'       => $request->periodoMeses       ?? null,
                    ];
                    break;

                case 'VIDA_LEY':
                    $datos = [
                        'tasa'         => $request->tasa         ?? null,
                        'periodoMeses' => $request->periodoMeses ?? null,
                    ];
                    break;

                case 'CAR':
                    $datos = [
                        'tasa'                => $request->tasa                ?? null,
                        'porcentajeAplicable' => $request->porcentajeAplicable ?? null,
                        'periodoMeses'        => $request->periodoMeses        ?? null,
                    ];
                    break;

                default:
                    return ['success' => false, 'message' => 'Código de seguro no válido'];
            }

            if ($existing) {
                // UPDATE
                self::update(
                    'seguros',
                    ['datos' => json_encode($datos)],
                    ['id'    => $existing->id]
                );
            } else {
                // INSERT
                self::insert('seguros', [
                    'proyecto_generales_id' => $request->proyecto_generales_id,
                    'tipo_seguro_id'        => $tipoSeguro->id,
                    'datos'                 => json_encode($datos),
                ]);
            }

            // Retornar el getList actualizado para que el frontend refresque los costos
            $updated = $this->getList($request);

            return [
                'success'      => true,
                'message'      => 'Guardado correctamente',
                'sctr'         => $updated['sctr']         ?? null,
                'vidaLey'      => $updated['vidaLey']      ?? null,
                'car'          => $updated['car']           ?? null,
                'totalSeguros' => $updated['totalSeguros']  ?? 0,
            ];
        } catch (\Throwable $th) {
            error_log('Error en Seguros::save: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    public function getList($request)
    {
        try {
            $sql = 'SELECT
                    ts.nombre as nombre_seguro,
                    ts.codigo as codigo_seguro,
                    s.id as id_seguro,
                    s.datos as datos
                FROM tipos_seguro ts
                LEFT JOIN seguros s
                    ON s.tipo_seguro_id = ts.id
                    AND s.proyecto_generales_id = :proyecto_generales_id';

            $seguros = self::fetchAllObj($sql, [
                'proyecto_generales_id' => $request->proyecto_generales_id
            ]);

            $costoDirecto = $this->gastosFinancieros->getCostoDirecto($request->proyecto_generales_id);

            $sql = 'SELECT id
                    FROM gastos_generales
                    WHERE proyecto_generales_id = :proyecto_generales_id
                    AND deleted_at IS NULL
                    AND grupos_id = 1
                    AND descripcion = "PERSONAL DE OBRA"
                    AND gastos_generales_id IS NULL';
            $gasto = self::fetchObj($sql, [
                'proyecto_generales_id' => $request->proyecto_generales_id
            ]);

            // ── Monto aplicable (mano de obra CD + remuneración variable) ──
            $requestGastos = (object) [
                'id' => $request->proyecto_generales_id
            ];
            $gastosGenerales = new GastosGenerales($requestGastos);
            $getListGastosGenerales = $gastosGenerales->getListGastosGenerales();

            $gastosVariables = 0;

            foreach ($getListGastosGenerales['data']['detail'] as $grupo) {
                if ($grupo->id == '1') {
                    $gastosVariables = $grupo->partial;
                    break;
                }
            }
            $remuneracionPersonalDeObraVariable = $gastosVariables;

            $sql = 'SELECT id
                    FROM subcategorias_proyecto_general
                    WHERE proyecto_generales_id = :proyecto_generales_id';
            $subcategorias = self::fetchAllObj($sql, [
                'proyecto_generales_id' => $request->proyecto_generales_id
            ]);

            $ids = implode(',', array_column($subcategorias, 'id'));

            $requestInsumos = (object) [
                'insumos_id'            => 'MO',
                'subpresupuestos_id'    => $ids,
                'proyecto_generales_id' => $request->proyecto_generales_id
            ];

            $detalleInsumos = new DetalleInsumos($requestInsumos);
            $insumos        = $detalleInsumos->getListadoAcumulacion();

            $insumos47 = array_filter($insumos, fn($i) => $i->iu == '47');

            $manoObraCd = array_reduce($insumos47, function ($carry, $insumo) {
                return $carry + (float) str_replace(',', '', $insumo->parcial);
            }, 0);

            $montoAplicable = (float) $manoObraCd + $remuneracionPersonalDeObraVariable;

            // ── Procesar cada seguro ───────────────────────────────────────
            $data = [];

            foreach ($seguros as $seguro) {
                $seguro->datos = json_decode($seguro->datos ?? '{}');
                $codigo        = strtoupper($seguro->codigo_seguro);

                error_log("procesando seguro: {$codigo}");

                switch ($codigo) {
                    case 'SCTR':
                        $tasaSaludObreros   = (float) ($seguro->datos->tasaSaludObreros   ?? 0);
                        $tasaSaludEmpleados = (float) ($seguro->datos->tasaSaludEmpleados ?? 0);
                        $tasaPension        = (float) ($seguro->datos->tasaPension        ?? 0);
                        $periodoMeses       = (float) ($seguro->datos->periodoMeses       ?? 0);

                        $costoFinanciero = $periodoMeses > 0
                            ? (($tasaSaludObreros + $tasaSaludEmpleados + $tasaPension) / 100)
                                * $montoAplicable
                                * ($periodoMeses / 12)
                            : 0;

                        $data['sctr'] = [
                            'nombre'             => $seguro->nombre_seguro,
                            'tasaSaludObreros'   => $tasaSaludObreros,
                            'tasaSaludEmpleados' => $tasaSaludEmpleados,
                            'tasaPension'        => $tasaPension,
                            'montoAplicable'     => $montoAplicable,
                            'periodoMeses'       => $periodoMeses,
                            'costoFinanciero'    => $costoFinanciero,
                        ];

                        error_log('sctr: ' . json_encode($data['sctr']));
                        break;

                    case 'VIDA_LEY':
                        $tasa         = (float) ($seguro->datos->tasa         ?? 0);
                        $periodoMeses = (float) ($seguro->datos->periodoMeses ?? 0);

                        $costoFinanciero = $periodoMeses > 0
                            ? ($tasa / 100) * $montoAplicable * ($periodoMeses / 12)
                            : 0;

                        $data['vidaLey'] = [
                            'nombre'          => $seguro->nombre_seguro,
                            'tasa'            => $tasa,
                            'montoAplicable'  => $montoAplicable,
                            'periodoMeses'    => $periodoMeses,
                            'costoFinanciero' => $costoFinanciero,
                        ];
                        break;

                    case 'CAR':
                        $tasa                = (float) ($seguro->datos->tasa                ?? 0);
                        $porcentajeAplicable = (float) ($seguro->datos->porcentajeAplicable ?? 0);
                        $periodoMeses        = (float) ($seguro->datos->periodoMeses        ?? 0);

                        $costoFinanciero = $periodoMeses > 0
                            ? ($tasa / 100) * $costoDirecto * ($porcentajeAplicable / 100) * ($periodoMeses / 12)
                            : 0;

                        $data['car'] = [
                            'nombre'              => $seguro->nombre_seguro,
                            'tasa'                => $tasa,
                            'montoContrato'       => $costoDirecto,
                            'cobertura'           => $costoDirecto,
                            'porcentajeAplicable' => $porcentajeAplicable,
                            'periodoMeses'        => $periodoMeses,
                            'costoFinanciero'     => $costoFinanciero,
                        ];
                        break;

                    default:
                        error_log("código de seguro no reconocido: {$codigo}");
                        break;
                }
            }

            $data['totalSeguros'] = array_sum(array_column($data, 'costoFinanciero'));

            return $data;
        } catch (\Throwable $th) {
            error_log('Error en Seguros::getList: ' . $th->getMessage());
            error_log('Stack trace: ' . $th->getTraceAsString());
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }
}
