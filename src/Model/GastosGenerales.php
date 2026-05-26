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
    private $_disaggregated;
    private $_level;

    public function __construct($request = null)
    {
        if ($request) {
            $column = [
                'id',
                'descripcion',
                'grupos_id',
                'duracion',
                'cantidad',
                'porcentaje_partida',
                'precio',
                'parcial',
                'unidad_medidas_id',
                'proyecto_generales_id',
                'gastos_generales_id',
                'disaggregated'
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }
            if (isset($request->level) && !empty($request->level)) {
                $this->_level = $request->level;
            }
        }
    }

    public function getSave()
    {
        try {
            if ($this->_gastos_generales_id && $this->_level == 4) {
                $this->_values["parcial"] = $this->getparcial();
            }
            if ($this->_id) {
                $sql = 'SELECT COUNT(id) FROM gastos_generales WHERE id = :id';
                $analisisPreciosUnitarios = self::fetchObj($sql, ['id' => $this->_id]);
                if ($analisisPreciosUnitarios) {
                    $update = self::update("gastos_generales", $this->_values, ['id' => $this->_id]);
                    $resp['success'] = true;
                    $resp['message'] = 'se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $insert = self::insert("gastos_generales", $this->_values);
                if ($insert && $insert["lastInsertId"]) {
                    $id = $insert["lastInsertId"];
                    $resp['success'] = true;
                    $resp['message'] = 'se registro correctamente ';
                    $resp['data'] = compact('id');
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Ocurrió un erro al registrar';
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
                    WHERE proyectos_generales_id=:id 
                    AND type_percentage='TGG'";
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
                    $result = $recalculoPrespuesto->getBudgetFooter(["id" => $this->_id]);
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
                    $matrix['data'] = array(
                        'total_percentage' => $percentage,
                        'direct_cost' => number_format($costo_directo, 2, '.', ''),
                        'total_general_expense' => number_format($total_general_expense, 2, '.', '')
                    );
                    return $matrix;
                } else {
                    $result = $this->getDetailGeneralExpense();
                    return $result;
                }
            }
        } catch (\Throwable $th) {
            return ["success" => false, "message" => $th->getMessage()];
        }
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
                        $parcial = $parcial + ($row->partial * 1);
                    }
                    $value->partial = number_format($parcial, 2, '.', '');
                    $value->detail = $detalle;
                    $suma_parcial = $suma_parcial + $parcial;
                    array_push($array_grupo_detalle, $value);
                }

                $grupo->partial = number_format($suma_parcial, 2, '.', '');
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
                $value->items = [];
                $value->partial = 0;
                array_push($array_grupos, $value);
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
    }

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
                    $parcial = 0;
                    foreach ($newDetalle as $row) {
                        $parcial = $parcial + ($row->partial * 1);
                    }
                    $item->partial = number_format($parcial, 2, '.', '');
                }
                $item->detail = $newDetalle;
                array_push($detalle, $item);
            }
        }
        return $detalle;
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
