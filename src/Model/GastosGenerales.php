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

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/RecalculoPrespuesto.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\RecalculoPrespuesto;
use stdClass;

class GastosGenerales extends Mysql
{
    const GRUPO_VARIABLES = 1;

    const SUBTITULO_PERSONAL_OBRA = 'PERSONAL DE OBRA';

    private $_id;
    public function getid()
    {
        return $this->_id;
    }


    private $_descripcion;
    public function getDescripcion()
    {
        return $this->_descripcion;
    }

    private $_tipo;
    public function getTipo()
    {
        return $this->_tipo;
    }

    private $_grupos_id;
    public function getGruposId()
    {
        return $this->_grupos_id;
    }

    private $_duracion;
    public function getduracion()
    {
        return $this->_duracion;
    }

    private $_cantidad;
    public function getcantidad()
    {
        return $this->_cantidad;
    }

    private $_porcentaje_partida;
    public function getPorcentajePartida()
    {
        return $this->_porcentaje_partida;
    }

    private $_precio;
    public function getprecio()
    {
        return $this->_precio;
    }

    private $_parcial;
    public function getparcial()
    {
        $matriz_parcial = [
            ($this->siNumeric($this->_duracion)),
            ($this->siNumeric($this->_cantidad)),
            ($this->siNumeric($this->_porcentaje_partida)),
            ($this->siNumeric($this->_precio)),
        ];
        $this->_parcial = array_product($matriz_parcial) / 100;
        return number_format($this->_parcial, 2, '.', '');
    }

    private $_type;
    public function getType()
    {
        return $this->_type;
    }

    public function siNumeric($nro)
    {
        return ($nro) ? $nro : 0;
    }

    private $_unidad_medidas_id;
    public function getUnidadMedidasId()
    {
        return $this->_unidad_medidas_id;
    }

    private $_proyecto_generales_id;
    public function getProyectoGeneralesId()
    {
        return $this->_proyecto_generales_id;
    }

    private $_values;
    public function getValue()
    {
        return $this->_values;
    }

    private $_gastos_generales_id;
    public function getGastosGenerales()
    {
        return $this->_gastos_generales_id;
    }

    private $_orden;
    public function getOrden()
    {
        return $this->_orden;
    }

    private $_disaggregated;
    private $_level;

    public function __construct($request = null)
    {
        if ($request) {
            $column = [
                'id',
                'descripcion',
                'tipo',
                'grupos_id',
                'duracion',
                'cantidad',
                'porcentaje_partida',
                'precio',
                'parcial',
                'unidad_medidas_id',
                'proyecto_generales_id',
                'gastos_generales_id',
                'orden',
                'disaggregated',
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && $request->{$value} !== '' && $request->{$value} !== null) {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }

            // 'tipo' llega siempre explícito desde el front (subtitulo|gasto).
            // Si no llega (compatibilidad), asumimos 'gasto' por defecto.
            if (!isset($this->_values['tipo'])) {
                $this->_tipo = 'gasto';
                $this->_values['tipo'] = 'gasto';
            }

            if (isset($request->level) && !empty($request->level)) {
                $this->_level = $request->level;
            }

            if (isset($request->type) && !empty($request->type)) {
                $this->_type = $request->type;
            }
        }
    }

    public function getSave()
    {
        $resp = [];

        try {
            if ($this->_tipo === 'gasto') {
                $this->_values['parcial'] = $this->getparcial();
            } else {
                // Un subtítulo nuevo arranca en 0; se recalculará al leer.
                $this->_values['parcial'] = $this->_values['parcial'] ?? 0;
            }

            if ($this->_id) {
                $sql = 'SELECT COUNT(id) AS total FROM gastos_generales WHERE id = :id';
                $existe = self::fetchObj($sql, ['id' => $this->_id]);

                if ($existe && $existe->total > 0) {
                    self::update('gastos_generales', $this->_values, ['id' => $this->_id]);

                    $resp['success'] = true;
                    $resp['message'] = 'Se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                // Si se está agregando un gasto SUELTO (sin subtítulo padre)
                // dentro de GASTOS GENERALES VARIABLES, se agrupa automáticamente
                // bajo un subtítulo "PERSONAL DE OBRA" (se crea si no existe aún).
                if (
                    $this->_tipo === 'gasto' &&
                    !$this->_gastos_generales_id &&
                    (int) $this->_grupos_id === self::GRUPO_VARIABLES
                ) {
                    $this->_gastos_generales_id = $this->getOrCrearPersonalDeObra();
                    $this->_values['gastos_generales_id'] = $this->_gastos_generales_id;
                }

                // Si no viene orden, lo calculamos como el último entre
                // los hermanos (mismo padre inmediato).
                if (!isset($this->_values['orden'])) {
                    $this->_values['orden'] = $this->getSiguienteOrden();
                }

                $insert = self::insert('gastos_generales', $this->_values);

                if ($insert && $insert['lastInsertId']) {
                    $id = $insert['lastInsertId'];

                    $resp['success'] = true;
                    $resp['message'] = 'Se registró correctamente';
                    $resp['data'] = compact('id');
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Ocurrió un error al registrar';
                }
            }

            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();

            return $resp;
        }
    }

    public function getListGastosGenerales()
    {
        try {
            $matrix = [];
            $sql = "SELECT id, percentage, active
                    FROM proyecto_pie_presupuesto
                    WHERE proyectos_generales_id = :id
                      AND type_percentage = 'TGG'";
            $totalgastogeneral = self::fetchObj($sql, ['id' => $this->_id]);

            if ($this->_disaggregated) {
                $result = $this->getDetailGeneralExpense();
                $args = new stdClass();
                $args->id = $this->_id;
                $args->disaggregated = '0';
                $this->changeDisaggregated($args);
                return $result;
            } else {
                if ($totalgastogeneral && $totalgastogeneral->active) {
                    $recalculoPrespuesto = new RecalculoPrespuesto();
                    $result = $recalculoPrespuesto->getBudgetFooter(['id' => $this->_id]);
                    $costo_directo = 0.00;
                    if ($result) {
                        if ($result[0]['variable'] == 'CD') {
                            $costo_directo = $result[0]['monto'];
                        }
                    }
                    $percentage = $totalgastogeneral->percentage ? $totalgastogeneral->percentage : 0;
                    $total_general_expense = $costo_directo * $percentage;
                    $matrix['success'] = true;
                    $matrix['disaggregated'] = 0;
                    $matrix['data'] = [
                        'total_percentage' => $percentage,
                        'direct_cost' => number_format($costo_directo, 2, '.', ''),
                        'total_general_expense' => number_format($total_general_expense, 2, '.', ''),
                    ];
                    return $matrix;
                } else {
                    $result = $this->getDetailGeneralExpense();
                    return $result;
                }
            }
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage()];
        }
    }

    private function getDetailGeneralExpense()
    {
        $sqlGrupos = "SELECT id, descripcion AS name FROM grupos ORDER BY id ASC";
        $grupos = self::fetchAllObj($sqlGrupos);

        $sql = "SELECT gg.id, gg.descripcion, gg.tipo, gg.grupos_id, gg.duracion,
                       gg.cantidad, gg.porcentaje_partida, gg.precio, gg.parcial,
                       gg.unidad_medidas_id, gg.gastos_generales_id, gg.orden
                FROM gastos_generales gg
                WHERE gg.proyecto_generales_id = :proyecto
                  AND gg.deleted_at IS NULL
                ORDER BY gg.grupos_id ASC, gg.orden ASC, gg.id ASC";

        $rows = self::fetchAllObj($sql, ['proyecto' => $this->_id]);

        // Agrupamos las filas por su padre inmediato para poder construir
        // el árbol en O(n) en lugar de recorrer todo el arreglo en cada nivel.
        $porPadre = [];
        foreach ($rows as $row) {
            $clavePadre = $row->gastos_generales_id ? (int) $row->gastos_generales_id : 'root_' . $row->grupos_id;
            $porPadre[$clavePadre][] = $row;
        }

        $data = [];
        foreach ($grupos as $grupo) {
            $items = $this->construirRama($porPadre, 'root_' . $grupo->id);

            if ((int) $grupo->id === self::GRUPO_VARIABLES) {
                $items = $this->asegurarPersonalDeObraVirtual($items, (int) $grupo->id);
            }

            $partial = $this->calcularParcialRama($items);

            $data[] = [
                'id' => (int) $grupo->id,
                'grupos_id' => (int) $grupo->id,
                'name' => $grupo->name,
                'partial' => $partial,
                'items' => $items,
            ];
        }

        return [
            'success' => true,
            'disaggregated' => 1,
            'data' => ['detail' => $data],
        ];
    }

    /**
     * Si el subtítulo "PERSONAL DE OBRA" todavía no existe como registro
     * real en GASTOS GENERALES VARIABLES, se inyecta un placeholder virtual
     * (id = null) al inicio de la lista, solo para mostrarlo en pantalla.
     * Se vuelve un registro real recién cuando se le agrega un gasto
     * (ver getOrCrearPersonalDeObra en getSave()).
     */
    private function asegurarPersonalDeObraVirtual(array $items, $grupoId)
    {
        foreach ($items as $item) {
            if ($item['tipo'] === 'subtitulo' && trim($item['name']) === self::SUBTITULO_PERSONAL_OBRA) {
                return $items;
            }
        }

        $placeholder = [
            'id' => null,
            'name' => self::SUBTITULO_PERSONAL_OBRA,
            'tipo' => 'subtitulo',
            'unit' => null,
            'duration' => null,
            'quantity' => null,
            'percentage' => null,
            'price' => null,
            'parcial' => '0.00',
            'grupos_id' => $grupoId,
            'gastos_generales_id' => null,
            'items' => [],
        ];

        array_unshift($items, $placeholder);

        return $items;
    }

    private function construirRama(array &$porPadre, $clave)
    {
        $rama = [];

        if (!isset($porPadre[$clave])) {
            return $rama;
        }

        foreach ($porPadre[$clave] as $row) {
            $nodo = [
                'id' => (int) $row->id,
                'name' => $row->descripcion,
                'tipo' => $row->tipo,
                'unit' => $row->unidad_medidas_id,
                'duration' => $row->duracion,
                'quantity' => $row->cantidad,
                'percentage' => $row->porcentaje_partida,
                'price' => $row->precio,
                'parcial' => $row->parcial,
                'grupos_id' => (int) $row->grupos_id,
                'gastos_generales_id' => $row->gastos_generales_id,
            ];

            if ($row->tipo === 'subtitulo') {
                $nodo['items'] = $this->construirRama($porPadre, (int) $row->id);
            }

            $rama[] = $nodo;
        }

        return $rama;
    }

    private function calcularParcialRama(array &$nodos)
    {
        $total = 0.0;

        foreach ($nodos as &$nodo) {
            if (isset($nodo['items'])) {
                $nodo['parcial'] = $this->calcularParcialRama($nodo['items']);
            }
            $total += (float) $nodo['parcial'];
        }
        unset($nodo);

        return number_format($total, 2, '.', '');
    }

    public function getDelete()
    {
        try {
            $sql = 'SELECT id FROM gastos_generales
                WHERE gastos_generales_id = :id AND gastos_generales_id != 0';
            $apus = self::fetchAllObj($sql, ['id' => $this->_id]);
            self::update('gastos_generales', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $this->_id]);
            foreach ($apus as $apu) {
                self::update('gastos_generales', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $apu->id]);
            }
            $resp['success'] = true;
            $resp['message'] = 'Se elimino el registro';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar el registro';
            return $resp;
        }
    }

    public function getSaveTotalGeneralExpense($request)
    {
        try {
            $sql = "SELECT id FROM proyecto_pie_presupuesto
                WHERE proyectos_generales_id = :id AND type_percentage = 'TGG'";
            $totalgastogeneral = self::fetchObj($sql, ['id' => $request->id]);
            $percentage = number_format(($request->percentage / 100), 2, '.', '');
            if ($totalgastogeneral) {
                self::update(
                    'proyecto_pie_presupuesto',
                    [
                        'percentage' => $percentage,
                        'active' => 1
                    ],
                    ['id' => $request->id]
                );
            } else {
                self::insert('proyecto_pie_presupuesto', [
                    'percentage' => $percentage,
                    'active' => 1,
                    'type_percentage' => 'TGG',
                    'proyectos_generales_id' => $request->id,
                ]);
            }
            $resp['data'] = array('percentage' => $percentage);
            $resp['success'] = true;
            $resp['message'] = 'Datos guardados';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Error al actualizar';
            return $resp;
        }
    }

    /*
    public function getDetailGeneralExpense()
    {
        $sql_gastos_generales = "SELECT
        gastos_generales.id,
        gastos_generales.descripcion AS name,
        grupos_id,
        grupos.descripcion AS 'grupos_descripcion',
        duracion AS duration,
        cantidad AS quantity,
        porcentaje_partida AS percentage,
        precio AS price,
        parcial AS partial,
        gastos_generales_id,
        unidad_medidas_id AS unit,
        proyecto_generales_id,
        'detail'
    FROM gastos_generales
    INNER JOIN grupos ON grupos_id = grupos.id
    WHERE proyecto_generales_id = :id AND gastos_generales_id is NULL AND deleted_at is NULL";
        $gastos_generales = self::fetchAllObj($sql_gastos_generales, ['id' => $this->_id]);

        $sql_gastos_generales_detalle = "SELECT
                                        id,
                                        descripcion AS name,
                                        grupos_id,
                                        duracion AS duration,
                                        cantidad AS quantity,
                                        porcentaje_partida AS percentage,
                                        precio AS price,
                                        parcial AS partial,
                                        gastos_generales_id,
                                        unidad_medidas_id AS unit,
                                        proyecto_generales_id
                                FROM gastos_generales
                                WHERE proyecto_generales_id = :id AND gastos_generales_id IS NOT NULL AND deleted_at is NULL";
        $gastos_generales_detalle = self::fetchAllObj($sql_gastos_generales_detalle, ['id' => $this->_id]);

        $sql_grupos = "SELECT id, descripcion AS name, 'items' FROM grupos";
        $grupos = self::fetchAllObj($sql_grupos);

        $recalculoPrespuesto = new RecalculoPrespuesto();
        $result = $recalculoPrespuesto->getBudgetFooter(["id" => $this->_id]);
        $costo_directo = 0.00;
        if ($result) {
            if ($result[0]['variable'] == 'CD') {
                $costo_directo = $result[0]['monto'];
            }
        }

        if ($gastos_generales) {
            $array_grupos = [];
            $total_parcial = 0.00;

            foreach ($grupos as $grupo) {
                $array_grupo_detalle = [];
                $suma_parcial = 0.00;

                $searchedValue = $grupo->id;
                $object_grupo = array_filter($gastos_generales, function ($e) use ($searchedValue) {
                    return ($e->grupos_id == $searchedValue);
                });

                foreach ($object_grupo as $value) {
                    $array = [];
                    $searchedValue = $value->id;
                    $detalle = $this->setMatrizGeneralExpense($searchedValue, $gastos_generales_detalle);
                    $parcial = 0;
                    foreach ($detalle as $row) {
                        $parcial += (float)($row->partial ?? 0);
                    }
                    $value->total = number_format($parcial, 2, '.', '');
                    $value->partial = number_format($parcial, 2, '.', '');
                    $value->detail = $detalle;
                    $suma_parcial = $suma_parcial + $parcial;
                    array_push($array_grupo_detalle, $value);
                }

                $grupo->subtotal = number_format($suma_parcial, 2, '.', '');
                $grupo->partial = number_format($suma_parcial, 2, '.', '');

                if ($grupo->id == 1) {
                    $existe = false;

                    foreach ($array_grupo_detalle as $item) {
                        if (trim($item->name) === 'PERSONAL DE OBRA') {
                            $existe = true;
                            break;
                        }
                    }

                    if (!$existe) {
                        array_unshift($array_grupo_detalle, (object)[
                            'id' => null,
                            'name' => 'PERSONAL DE OBRA',
                            'grupos_id' => 1,
                            'grupos_descripcion' => $grupo->name,
                            'duration' => null,
                            'quantity' => null,
                            'percentage' => null,
                            'price' => null,
                            'partial' => '0.00',
                            'gastos_generales_id' => null,
                            'unit' => null,
                            'proyecto_generales_id' => $this->_id,
                            'detail' => []
                        ]);
                    }
                }

                $grupo->items = $array_grupo_detalle;
                $total_parcial = $total_parcial + $suma_parcial;
                array_push($array_grupos, $grupo);
            }

            $percentage = $costo_directo ? (($total_parcial / $costo_directo) * 100) : 0;

            $matrix['success'] = true;
            $matrix['disaggregated'] = 1;
            $matrix['data'] = array(
                'total_percentage' => number_format($percentage, 2, '.', ''),
                'direct_cost' => number_format($costo_directo, 2, '.', ''),
                'total_general_expense' => number_format($total_parcial, 2, '.', ''),
                'detail' => $array_grupos
            );
            return $matrix;
        } else {
            $array_grupos = [];
            $total_parcial = 0.00;
            $percentage = 0.00;

            foreach ($grupos as $value) {
                if ($value->id == 1) {
                    $value->items = [
                        (object)[
                            'id' => null,
                            'name' => 'PERSONAL DE OBRA',
                            'grupos_id' => 1,
                            'grupos_descripcion' => $value->name,
                            'duration' => null,
                            'quantity' => null,
                            'percentage' => null,
                            'price' => null,
                            'partial' => '0.00',
                            'gastos_generales_id' => null,
                            'unit' => null,
                            'proyecto_generales_id' => $this->_id,
                            'detail' => []
                        ]
                    ];
                } else {
                    $value->items = [];
                }

                $value->partial = 0;
                $array_grupos[] = $value;
            }

            $matrix['success'] = true;
            $matrix['disaggregated'] = 1;
            $matrix['data'] = array(
                'total_percentage' => number_format($percentage, 2, '.', ''),
                'direct_cost' => number_format($costo_directo, 2, '.', ''),
                'total_general_expense' => number_format($total_parcial, 2, '.', ''),
                'detail' => $array_grupos
            );
            return $matrix;
        }
    }*/

    private function setMatrizGeneralExpense($searchedValue, $gastos_generales_detalle)
    {
        $detalle = [];
        $object = array_filter($gastos_generales_detalle, function ($e) use ($searchedValue) {
            return $e->gastos_generales_id == $searchedValue;
        });

        if (count($object)) {
            foreach ($object as $item) {
                $newSearchedValue = $item->id;
                $newDetalle = $this->setMatrizGeneralExpense($newSearchedValue, $gastos_generales_detalle);

                if (count($newDetalle)) {
                    // Nodo intermedio: suma los parciales de sus hijos
                    $parcial = 0;
                    foreach ($newDetalle as $row) {
                        $parcial += (float)($row->partial ?? 0);
                    }
                    $item->partial = number_format($parcial, 2, '.', '');
                } else {
                    // Nodo hoja: calcular parcial si es null
                    if ($item->partial === null || $item->partial === '') {
                        $duration   = (float)($item->duration   ?? 0);
                        $quantity   = (float)($item->quantity   ?? 0);
                        $percentage = (float)($item->percentage ?? 0);
                        $price      = (float)($item->price      ?? 0);
                        $item->partial = number_format($duration * $quantity * ($percentage / 100) * $price, 2, '.', '');
                    }
                }

                $item->detail = $newDetalle;
                array_push($detalle, $item);
            }
        }

        return $detalle;
    }

    /**
     * Busca el subtítulo "PERSONAL DE OBRA" dentro de GASTOS GENERALES
     * VARIABLES para este proyecto; si no existe, lo crea y devuelve su id.
     */
    private function getOrCrearPersonalDeObra()
    {
        $sql = "SELECT id
                FROM gastos_generales
                WHERE proyecto_generales_id = :proyecto
                  AND grupos_id = :grupo
                  AND tipo = 'subtitulo'
                  AND descripcion = :descripcion
                  AND gastos_generales_id IS NULL
                  AND deleted_at IS NULL
                LIMIT 1";

        $existente = self::fetchObj($sql, [
            'proyecto' => $this->_proyecto_generales_id,
            'grupo' => self::GRUPO_VARIABLES,
            'descripcion' => self::SUBTITULO_PERSONAL_OBRA,
        ]);

        if ($existente) {
            return (int) $existente->id;
        }

        $orden = $this->getSiguienteOrdenParaGrupoRaiz(self::GRUPO_VARIABLES);

        $insert = self::insert('gastos_generales', [
            'descripcion' => self::SUBTITULO_PERSONAL_OBRA,
            'tipo' => 'subtitulo',
            'grupos_id' => self::GRUPO_VARIABLES,
            'proyecto_generales_id' => $this->_proyecto_generales_id,
            'gastos_generales_id' => null,
            'orden' => $orden,
            'duracion' => 0,
            'cantidad' => 0,
            'porcentaje_partida' => 0,
            'precio' => 0,
            'parcial' => 0,
            'unidad_medidas_id' => null,
            'deleted_at' => null,
        ]);

        return (int) $insert['lastInsertId'];
    }

    private function getSiguienteOrdenParaGrupoRaiz($grupoId)
    {
        $sql = "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                FROM gastos_generales
                WHERE proyecto_generales_id = :proyecto
                  AND grupos_id = :grupo
                  AND gastos_generales_id IS NULL
                  AND deleted_at IS NULL";

        $res = self::fetchObj($sql, [
            'proyecto' => $this->_proyecto_generales_id,
            'grupo' => $grupoId,
        ]);

        return $res ? (int) $res->siguiente : 1;
    }

    /**
     * Calcula el siguiente número de orden entre los hermanos del nuevo
     * nodo (mismo grupos_id y mismo gastos_generales_id padre).
     */
    private function getSiguienteOrden()
    {
        if ($this->_gastos_generales_id) {
            $sql = "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                    FROM gastos_generales
                    WHERE proyecto_generales_id = :proyecto
                      AND gastos_generales_id = :padre
                      AND deleted_at IS NULL";
            $res = self::fetchObj($sql, [
                'proyecto' => $this->_proyecto_generales_id,
                'padre' => $this->_gastos_generales_id,
            ]);
        } else {
            $sql = "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                    FROM gastos_generales
                    WHERE proyecto_generales_id = :proyecto
                      AND grupos_id = :grupo
                      AND gastos_generales_id IS NULL
                      AND deleted_at IS NULL";
            $res = self::fetchObj($sql, [
                'proyecto' => $this->_proyecto_generales_id,
                'grupo' => $this->_grupos_id,
            ]);
        }

        return $res ? (int) $res->siguiente : 1;
    }

    public function changeDisaggregated($request)
    {
        try {
            $sql = "SELECT id FROM proyecto_pie_presupuesto WHERE proyectos_generales_id=:id AND type_percentage='TGG'";
            $totalgastogeneral = self::fetchObj($sql, ['id' => $request->id]);
            if ($totalgastogeneral) {
                self::update(
                    'proyecto_pie_presupuesto',
                    ['active' => $request->disaggregated],
                    ['proyectos_generales_id' => $request->id, 'type_percentage' => 'TGG']
                );
            } else {
                self::insert('proyecto_pie_presupuesto', [
                    'active' => $request->disaggregated,
                    'type_percentage' => 'TGG',
                    'proyectos_generales_id' => $request->id,
                ]);
            }
            $resp['success'] = true;
            $resp['message'] = 'Datos guardados';
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Error al actualizar';
            return $resp;
        }
    }

    public function moverGasto($request)
    {
        try {
            $gastoId = (int) ($request->id ?? 0);
            $proyectoGeneralesId = (int) ($request->proyecto_generales_id ?? 0);
            $typeItem = (int) ($request->type_item ?? 0);

            $parentId = (int) ($request->parent_id ?? 0);
            $parentGruposId = (int) ($request->parent_grupos_id ?? 0);

            $action = $request->actionClipboard ?? null;

            $gasto = $this->obtenerGasto(
                $gastoId,
                $proyectoGeneralesId
            );

            if (!$gasto) {
                return [
                    'success' => false,
                    'message' => 'Gasto no encontrado'
                ];
            }

            switch ($typeItem) {
                case 2:
                    if ($action == 'cortar' || !$action) {
                        return $this->moverGrupoCompleto(
                            $gastoId,
                            $proyectoGeneralesId,
                            $parentGruposId,
                        );
                    } elseif ($action == 'copiar') {
                        return $this->copiarGrupo(
                            $gasto,
                            $parentGruposId
                        );
                    }

                    return [
                            'success' => false,
                            'message' => 'No se pudo mover el grupo',
                        ];

                case 3:
                    if ($action == 'cortar' || !$action) {
                        return $this->moverGastoIndividual(
                            $gastoId,
                            $parentId,
                            $parentGruposId,
                        );
                    } elseif ($action == 'copiar') {
                        return $this->copiarGasto(
                            $gasto,
                            $parentId,
                            $parentGruposId
                        );
                    }

                    return [
                        'success' => false,
                        'message' => 'No se pudo mover el gasto',
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => 'Tipo de item no válido'
                    ];
            }
        } catch (\Throwable $th) {
            error_log($th->getMessage());

            return [
                'success' => false,
                'message' => 'Error al mover gasto'
            ];
        }
    }

    private function obtenerGasto(
        int $gastoId,
        int $proyectoGeneralesId
    ) {
        $sql = "SELECT *
            FROM gastos_generales
            WHERE id = :id
            AND proyecto_generales_id = :proyecto_generales_id
            AND deleted_at IS NULL";

        return self::fetchObj($sql, [
            'id' => $gastoId,
            'proyecto_generales_id' => $proyectoGeneralesId
        ]);
    }

    private function obtenerHijos(
        int $gastoId,
        int $proyectoGeneralesId
    ) {
        $sql = "SELECT *
                FROM gastos_generales
                WHERE gastos_generales_id = :gasto_id
                AND proyecto_generales_id = :proyecto_generales_id
                AND deleted_at IS NULL";

        return self::fetchAllObj($sql, [
            'gasto_id' => $gastoId,
            'proyecto_generales_id' => $proyectoGeneralesId
        ]);
    }

    private function moverGastoIndividual(
        int $gastoId,
        int $parentId,
        int $parentGruposId
    ) {
        // Evitar moverse sobre sí mismo
        if ($gastoId === $parentId) {
            return [
                'success' => false,
                'message' => 'No se puede mover un gasto sobre sí mismo'
            ];
        }

        $campos = [
            'grupos_id' => $parentGruposId,
            'gastos_generales_id' => $parentId
        ];

        $actualizado = self::update(
            'gastos_generales',
            $campos,
            ['id' => $gastoId]
        );

        if (!$actualizado) {
            return [
                'success' => false,
                'message' => 'No se pudo mover el gasto'
            ];
        }

        return [
            'success' => true,
            'message' => 'Gasto movido correctamente'
        ];
    }

    private function copiarGasto($gasto, int $parentId = 0, int $parentGruposId = 0)
    {
        $campos = [
            'grupos_id' => $parentGruposId,
            'proyecto_generales_id' => $gasto->proyecto_generales_id,
            'descripcion' => $gasto->descripcion,
            'duracion' => $gasto->duracion,
            'cantidad' => $gasto->cantidad,
            'porcentaje_partida' => $gasto->porcentaje_partida,
            'precio' => $gasto->precio,
            'parcial' => $gasto->parcial,
            'unidad_medidas_id' => $gasto->unidad_medidas_id,
            'deleted_at' => null
        ];

        if ($parentId > 0) {
            $campos['gastos_generales_id'] = $parentId;
        }

        $insertado = self::insert(
            'gastos_generales',
            $campos
        );

        if (!$insertado) {
            return [
                'success' => false,
                'message' => 'No se pudo copiar el gasto',
                'data' => $insertado
            ];
        }

        return [
            'success' => true,
            'message' => 'Gasto copiado correctamente',
            'lastInsertId' => $insertado['lastInsertId']
        ];
    }

    private function moverGrupoCompleto(
        int $gastoId,
        int $proyectoGeneralesId,
        int $parentGruposId
    ) {
        $actualizado = self::update(
            'gastos_generales',
            [
                'grupos_id' => $parentGruposId
            ],
            [
                'id' => $gastoId
            ]
        );

        if (!$actualizado) {
            return [
                'success' => false,
                'message' => 'No se pudo mover el grupo'
            ];
        }

        $hijos = $this->obtenerHijos(
            $gastoId,
            $proyectoGeneralesId
        );

        foreach ($hijos as $hijo) {
            self::update(
                'gastos_generales',
                [
                    'grupos_id' => $parentGruposId
                ],
                [
                    'id' => $hijo->id
                ]
            );
        }

        return [
            'success' => true,
            'message' => 'Grupo movido correctamente'
        ];
    }

    private function copiarGrupo(
        $gasto,
        int $parentGruposId
    ) {
        $result = $this->copiarGasto(
            $gasto,
            0,
            $parentGruposId
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'No se pudo copiar el grupo',
            ];
        }

        $nuevoGastoId = $result['lastInsertId'];

        $hijos = $this->obtenerHijos(
            $gasto->id,
            $gasto->proyecto_generales_id
        );

        if (count($hijos) > 0) {
            foreach ($hijos as $hijo) {
                $result = $this->copiarGasto(
                    $hijo,
                    $nuevoGastoId,
                    $parentGruposId
                );

                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => 'No se pudo copiar el grupo',
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Grupo copiado correctamente'
        ];
    }
}
