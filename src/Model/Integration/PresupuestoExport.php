<?php

//require_once(__DIR__ . '/../../persistence/Mysql.php');
//require_once(__DIR__ . '/../../utilitarian/FG.php');

namespace App\Model\Integration;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;

class PresupuestoExport extends Mysql
{
    private $_proyecto_generales_id;
    private $_subpresupuestos_id;

    public function __construct($request = null)
    {
        parent::__construct();

        if ($request) {
            if (isset($request->proyecto_generales_id)) {
                $this->_proyecto_generales_id = $request->proyecto_generales_id;
            }
            if (isset($request->subpresupuestos_id)) {
                $this->_subpresupuestos_id = $request->subpresupuestos_id;
            }
        }
    }

    public function setParams($proyecto_id, $subpresupuesto_id = '1')
    {
        $this->_proyecto_generales_id = $proyecto_id;
        $this->_subpresupuestos_id = $subpresupuesto_id;
    }

    public function getExportJSONComplete()
    {
        try {
            $proyecto = $this->getProyectoInfo();
            if (!$proyecto) {
                return [
                    'success' => false,
                    'message' => 'Proyecto no encontrado'
                ];
            }

            // Obtener todos los subpresupuestos del proyecto
            $subpresupuestos = $this->getSubpresupuestosList();

            $subpresupuestos_data = [];
            $total_general = 0;

            foreach ($subpresupuestos as $sub) {
                // Temporalmente cambiar el subpresupuesto_id
                $original_id = $this->_subpresupuestos_id;
                $this->_subpresupuestos_id = $sub->id;

                // Obtener datos del subpresupuesto
                $presupuestos = $this->getPresupuestosData();

                $subtotal = floatval(str_replace(',', '', $presupuestos['total']));
                $total_general += $subtotal;

                $subpresupuestos_data[] = [
                    'id' => $sub->id,
                    'nombre' => $sub->descripcion,
                    'orden' => $sub->orden,
                    'items' => $presupuestos['items'],
                    'subtotal' => number_format($subtotal, 2, '.', ',')
                ];

                // Restaurar el ID original
                $this->_subpresupuestos_id = $original_id;
            }

            $data = [
                'proyecto' => [
                    'nombre' => $proyecto->proyecto,
                    'cliente' => $proyecto->cliente,
                    'ubicacion' => $this->formatUbicacion($proyecto),
                    'fecha_base' => $this->formatFecha($proyecto->fecha_base),
                    'moneda' => $proyecto->moneda
                ],
                'subpresupuestos' => $subpresupuestos_data,
                'resumen' => [
                    'total_general' => number_format($total_general, 2, '.', ',')
                ]
            ];

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    public function getExportJSONCompleteFlat()
    {
        try {
            $proyecto = $this->getProyectoInfo();
            if (!$proyecto) {
                return [
                    'success' => false,
                    'message' => 'Proyecto no encontrado'
                ];
            }

            // Obtener todos los subpresupuestos del proyecto
            $subpresupuestos = $this->getSubpresupuestosList();

            $subpresupuestos_data = [];
            $total_general = 0;

            foreach ($subpresupuestos as $sub) {
                // Temporalmente cambiar el subpresupuesto_id
                $original_id = $this->_subpresupuestos_id;
                $this->_subpresupuestos_id = $sub->id;

                // Obtener items del subpresupuesto
                $sql = "SELECT        
                            p.partidas_id,
                            p.descripcion AS partida,
                            um.descripcion AS unidad,
                            p.metrado,
                            p.cu,
                            p.type_item,
                            p.nro_orden
                        FROM presupuestos p
                        LEFT JOIN unidad_medidas um ON um.id = p.unidad_medidas_id
                        WHERE p.proyecto_generales_id = :id 
                        AND p.deleted_at IS NULL
                        AND p.subpresupuestos_id = :sub_id
                        ORDER BY p.nro_orden ASC";

                $presupuestos = self::fetchAllObj($sql, [
                    'id' => $this->_proyecto_generales_id,
                    'sub_id' => $sub->id
                ]);

                $items = [];
                $subtotal = 0;

                foreach ($presupuestos as $p) {
                    $metrado = $p->metrado ?: 0;
                    $cu = $p->cu ?: 0;
                    $parcial = $metrado * $cu;

                    if ($p->type_item == '3') {
                        $subtotal += $parcial;
                    }

                    $items[] = [
                        'item' => $p->partidas_id ?: '',
                        'partida' => $p->partida,
                        'unidad' => $p->unidad ?: '',
                        'metrado' => number_format($metrado, 2, '.', ','),
                        'cu' => number_format($cu, 2, '.', ','),
                        'parcial' => number_format($parcial, 2, '.', ',')
                    ];
                }

                $total_general += $subtotal;

                $subpresupuestos_data[] = [
                    'id' => $sub->id,
                    'nombre' => $sub->descripcion,
                    'orden' => $sub->orden,
                    'items' => $items,
                    'subtotal' => number_format($subtotal, 2, '.', ',')
                ];

                // Restaurar el ID original
                $this->_subpresupuestos_id = $original_id;
            }

            $data = [
                'encabezado' => [
                    'proyecto' => $proyecto->proyecto,
                    'cliente' => $proyecto->cliente,
                    'ubicacion' => $this->formatUbicacion($proyecto),
                    'fecha_base' => $this->formatFecha($proyecto->fecha_base),
                    'moneda' => $proyecto->moneda
                ],
                'subpresupuestos' => $subpresupuestos_data,
                'resumen' => [
                    'total_general' => number_format($total_general, 2, '.', ',')
                ]
            ];

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    //Obtener lista de subpresupuestos del proyecto
    private function getSubpresupuestosList()
    {
        $sql = "SELECT 
                    spg.id,
                    spg.descripcion,
                    spg.orden
                FROM subcategorias_proyecto_general spg
                WHERE spg.proyecto_generales_id = :id
                ORDER BY spg.orden ASC";

        return self::fetchAllObj($sql, ['id' => $this->_proyecto_generales_id]);
    }

    public function getExportJSON()
    {
        try {
            $proyecto = $this->getProyectoInfo();
            if (!$proyecto) {
                return [
                    'success' => false,
                    'message' => 'Proyecto no encontrado'
                ];
            }

            $presupuestos = $this->getPresupuestosData();

            $data = [
                'proyecto' => [
                    'nombre' => $proyecto->proyecto,
                    'cliente' => $proyecto->cliente,
                    'ubicacion' => $this->formatUbicacion($proyecto),
                    'fecha_base' => $this->formatFecha($proyecto->fecha_base),
                    'moneda' => $proyecto->moneda
                ],
                'presupuesto' => [
                    'titulo' => $this->getPresupuestoTitulo(),
                    'items' => $presupuestos['items'],
                    'costo_directo' => $presupuestos['total']
                ]
            ];

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    private function getProyectoInfo()
    {
        $sql = "SELECT 
                    pg.id,
                    pg.proyecto,
                    pg.cliente,
                    pg.direccion,
                    pg.distrito,
                    pg.provincia,
                    pg.departamento,
                    pg.fecha_base,
                    pg.moneda,
                    d.descripcion AS distrito_nombre,
                    p.descripcion AS provincia_nombre,
                    dep.descripcion AS departamento_nombre
                FROM proyecto_generales pg
                LEFT JOIN ub_distritos d ON d.id = pg.distrito
                LEFT JOIN ub_provincias p ON p.id = pg.provincia
                LEFT JOIN ub_departamentos dep ON dep.id = pg.departamento
                WHERE pg.id = :id AND pg.deleted_at IS NULL";

        return self::fetchObj($sql, ['id' => $this->_proyecto_generales_id]);
    }

    private function formatUbicacion($proyecto)
    {
        $partes = array_filter([
            $proyecto->direccion,
            $proyecto->distrito_nombre,
            $proyecto->provincia_nombre,
            $proyecto->departamento_nombre
        ]);
        return implode(' ', $partes);
    }

    private function formatFecha($fecha)
    {
        if (!$fecha) {
            return '';
        }
        $date = new DateTime($fecha);
        return $date->format('d-m-Y');
    }

    private function getPresupuestoTitulo()
    {
        $sql = "SELECT descripcion 
                FROM presupuestos 
                WHERE proyecto_generales_id = :id 
                AND presupuestos_proyecto_generales_id IS NULL 
                AND deleted_at IS NULL 
                AND subpresupuestos_id IN ({$this->_subpresupuestos_id})
                ORDER BY nro_orden ASC 
                LIMIT 1";

        $result = self::fetchObj($sql, ['id' => $this->_proyecto_generales_id]);
        return $result ? $result->descripcion : 'PRESUPUESTO';
    }

    private function getPresupuestosData()
    {
        $sql = "SELECT        
                    p.id,
                    p.partidas_id,
                    p.descripcion,
                    p.unidad_medidas_id,
                    um.descripcion AS unidad_nombre,
                    p.metrado,
                    p.cu,
                    p.mo,
                    p.mt,
                    p.eq,
                    p.sc,
                    p.sp,
                    p.presupuestos_proyecto_generales_id,
                    p.nro_orden,
                    p.type_item,
                    p.presupuestos_title_id
                FROM presupuestos p
                LEFT JOIN unidad_medidas um ON um.id = p.unidad_medidas_id
                WHERE p.proyecto_generales_id = :id 
                AND p.deleted_at IS NULL
                AND p.subpresupuestos_id IN ({$this->_subpresupuestos_id})
                ORDER BY p.nro_orden ASC";

        $presupuestos = self::fetchAllObj($sql, ['id' => $this->_proyecto_generales_id]);

        $items = $this->buildHierarchy($presupuestos);
        $total = $this->calculateTotal($items);

        return [
            'items' => $items,
            'total' => number_format($total, 2, '.', '')
        ];
    }

    private function buildHierarchy($presupuestos)
    {
        $items = [];
        $lookup = [];

        foreach ($presupuestos as $p) {
            $lookup[$p->id] = [
                'item' => $p->partidas_id ?: '',
                'partida' => $p->descripcion,
                'unidad' => $p->unidad_nombre ?: '',
                'metrado' => $this->formatNumber($p->metrado),
                'cu' => $this->formatNumber($p->cu),
                'parcial' => $this->calculateParcial($p),
                'type_item' => $p->type_item,
                'level' => $p->nro_orden,
                'children' => []
            ];
        }

        foreach ($presupuestos as $p) {
            $item = &$lookup[$p->id];

            if (
                $p->presupuestos_proyecto_generales_id === null ||
                $p->presupuestos_proyecto_generales_id == 0
            ) {
                $items[] = &$item;
            } elseif (isset($lookup[$p->presupuestos_proyecto_generales_id])) {
                $lookup[$p->presupuestos_proyecto_generales_id]['children'][] = &$item;
            }
        }

        $this->calculateParentTotals($items);

        return $items;
    }

    private function calculateParcial($presupuesto)
    {
        if ($presupuesto->type_item == '3') {
            $metrado = $presupuesto->metrado ?: 0;
            $cu = $presupuesto->cu ?: 0;
            return $this->formatNumber($metrado * $cu);
        }
        return '0.00';
    }

    private function calculateParentTotals(&$items)
    {
        foreach ($items as &$item) {
            if (!empty($item['children'])) {
                $this->calculateParentTotals($item['children']);

                $total = 0;
                foreach ($item['children'] as $child) {
                    $total += floatval(str_replace(',', '', $child['parcial']));
                }
                $item['parcial'] = $this->formatNumber($total);
            }
        }
    }

    private function calculateTotal($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += floatval(str_replace(',', '', $item['parcial']));
        }
        return $total;
    }

    private function formatNumber($number)
    {
        if ($number === null || $number === '') {
            return '0.00';
        }
        return number_format(floatval($number), 2, '.', ',');
    }

    public function getExportJSONFlat()
    {
        try {
            $proyecto = $this->getProyectoInfo();
            if (!$proyecto) {
                return [
                    'success' => false,
                    'message' => 'Proyecto no encontrado'
                ];
            }

            $sql = "SELECT        
                        p.partidas_id,
                        p.descripcion AS partida,
                        um.descripcion AS unidad,
                        p.metrado,
                        p.cu,
                        p.type_item,
                        p.nro_orden
                    FROM presupuestos p
                    LEFT JOIN unidad_medidas um ON um.id = p.unidad_medidas_id
                    WHERE p.proyecto_generales_id = :id 
                    AND p.deleted_at IS NULL
                    AND p.subpresupuestos_id IN ({$this->_subpresupuestos_id})
                    ORDER BY p.nro_orden ASC";

            $presupuestos = self::fetchAllObj($sql, ['id' => $this->_proyecto_generales_id]);

            $items = [];
            $total = 0;

            foreach ($presupuestos as $p) {
                $metrado = $p->metrado ?: 0;
                $cu = $p->cu ?: 0;
                $parcial = $metrado * $cu;

                if ($p->type_item == '3') {
                    $total += $parcial;
                }

                $items[] = [
                    'item' => $p->partidas_id ?: '',
                    'partida' => $p->partida,
                    'unidad' => $p->unidad ?: '',
                    'metrado' => number_format($metrado, 2, '.', ','),
                    'cu' => number_format($cu, 2, '.', ','),
                    'parcial' => number_format($parcial, 2, '.', ',')
                ];
            }

            $data = [
                'encabezado' => [
                    'proyecto' => $proyecto->proyecto,
                    'cliente' => $proyecto->cliente,
                    'ubicacion' => $this->formatUbicacion($proyecto),
                    'fecha_base' => $this->formatFecha($proyecto->fecha_base),
                    'moneda' => $proyecto->moneda,
                    'presupuesto_titulo' => $this->getPresupuestoTitulo()
                ],
                'items' => $items,
                'resumen' => [
                    'costo_directo' => number_format($total, 2, '.', ',')
                ]
            ];

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }
}
