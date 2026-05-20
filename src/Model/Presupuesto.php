<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/PresupuestoTransactions.php');
//require_once(__DIR__ . '/PresupuestosTitulos.php');
//require_once(__DIR__ . '/PartidaDetail.php');
//require_once(__DIR__ . '/Partidas.php');
//require_once(__DIR__ . '/../src/Plan.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\PresupuestoTransactions;
use App\Model\PresupuestosTitulos;
use App\Model\PartidaDetail;
use App\Model\Partidas;
use App\Model\Plan;
use App\Model\SubpartidaProyecto;

class Presupuesto extends Mysql
{
    private $_id;
    private $_item_partida;
    private $_descripcion;
    private $_unidad_medidas_id;
    private $_proyecto_generales_id;
    private $_presupuestos_id;
    private $_presupuestos_unidad_medidas_id;
    private $_presupuestos_proyecto_generales_id;
    private $_fecha_inicio;
    private $_fecha_fin;
    private $_duracion;
    private $_nro_orden;
    private $_partidas_id;
    private $_master_partidas_id;
    private $_insumos_id;
    private $_type_item;
    private $_tipo;
    private $_values;
    private $_presupuestos_title_id;
    private $_subpresupuestos_id;
    private $idspartida;
    private $titulos;
    private $_rendimiento_unid;
    private $_rendimiento;

    public function setProyectoGeneralesId($id)
    {
        $this->_proyecto_generales_id = $id;
    }


    public function __construct($request)
    {
        $column = [
            'id',
            'item_partida',
            'presupuestos_title_id',
            'descripcion',
            'subpresupuestos_id',
            'unidad_medidas_id',
            'proyecto_generales_id',
            'presupuestos_id',
            'presupuestos_unidad_medidas_id',
            'presupuestos_proyecto_generales_id',
            'fecha_inicio',
            'fecha_fin',
            'duracion',
            'nro_orden',
            'partidas_id',
            'insumos_id',
            'tipo',
            'type_item'
        ];

        if ($request) {
            foreach ($column as $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }
            if (isset($request->idspartida)) {
                $this->idspartida = $request->idspartida;
            }
            if (isset($request->titulos) && $request->titulos) {
                $this->titulos = $request->titulos;
            }
            if (isset($request->rendimiento_unid)) {
                $this->_rendimiento_unid = $request->rendimiento_unid;
            }
            if (isset($request->rendimiento)) {
                $this->_rendimiento = $request->rendimiento;
            }
        }
    }

    public function getSave()
    {
        try {
            if ($this->_nro_orden) {
                $this->_values["nro_orden"] = $this->_nro_orden;
            }

            $flag = true;
            if ($this->_presupuestos_proyecto_generales_id) {
                $sql_a = 'SELECT type_item FROM presupuestos WHERE id = :id AND deleted_at is NULL';
                $val_a = self::fetchObj($sql_a, ['id' => $this->_presupuestos_proyecto_generales_id]);
                if ($val_a) {
                    $flag = $val_a->type_item != '3';
                }
            }

            if ($flag) {
                $title_id = 0;
                $partida_id = 0;
                if ($this->titulos) {
                    $presupuestosTitulos = new PresupuestosTitulos([
                        'title' => $this->_descripcion,
                        'titulos' => $this->titulos,
                        'proyecto_generales_id' => $this->_proyecto_generales_id
                    ]);
                    $title_id = $presupuestosTitulos->getSave();
                    if ($title_id) {
                        $this->_values["presupuestos_title_id"] = $title_id;
                    }
                }

                if ($this->idspartida) {
                    $datapartida = json_decode($this->idspartida);
                    if ($datapartida) {
                        $this->_master_partidas_id = $datapartida->masterid;
                        $partidas = new Partidas();
                        $result = $partidas->getSave([
                            'id' => $datapartida->id ?? 0,
                            'masterid' => $datapartida->masterid ?? 0,
                            'partida' => $this->_descripcion,
                            'rendimiento_unid' => $this->_rendimiento_unid,
                            'unidad_medidas_id' => $this->_unidad_medidas_id,
                            'rendimiento' => $this->_rendimiento,
                            'proyecto_generales_id' => $this->_proyecto_generales_id,
                            'subpresupuesto_id' => $this->_subpresupuestos_id
                        ]);

                        if (!empty($result['data'])) {
                            $this->_values["partidas_id"] = $result['data']['id'];
                            $this->_partidas_id = $result['data']['id'];
                            $partida_id = $result['data']['id'];
                            $this->_rendimiento_unid = $result['data']['rendimiento_unid'];
                            $this->_rendimiento = $result['data']['rendimiento'];
                            $this->_values['unidad_medidas_id'] = $this->_unidad_medidas_id = $result['data']['unidad_medidas_id'];
                        }
                    }
                }

                if ($this->_id) {
                    $sql = 'SELECT id, partidas_id, type_item FROM presupuestos WHERE id = :id  AND deleted_at is NULL';
                    $presupuestos = self::fetchObj($sql, ['id' => $this->_id]);
                    if ($presupuestos && $presupuestos->id) {
                        $sql = 'SELECT COUNT(1) AS checked FROM apus_partida_presupuestos WHERE presupuestos_id = :id  AND deleted_at is NULL';
                        $verify = self::fetchObj($sql, ['id' => $this->_id]);

                        if ($verify && $verify->checked) {
                            $resp['success'] = false;
                            $resp['message'] = 'Error la partida ya tiene apus asignados';
                            return $resp;
                        }

                        $update = self::update("presupuestos", $this->_values, ['id' => $this->_id]);

                        if (!$presupuestos->partidas_id && $presupuestos->type_item == '3' && $this->_partidas_id) {
                            $partidaDetail = new PartidaDetail();
                            $partidaDetail->getSave([
                                'partidas_id' => $this->_partidas_id,
                                'master_partidas_id' => $this->_master_partidas_id,
                                'id' => $this->_id,
                                'subpresupuestos_id' => $this->_subpresupuestos_id,
                                'proyecto_generales_id' => $this->_proyecto_generales_id,
                            ]);
                        }

                        $this->_type_item = $presupuestos->type_item;

                        $this->savePresupuestoPartida();

                        $presupuestoTransactions = new PresupuestoTransactions([
                            "id" => $this->_id,
                            "nro_orden" => $this->_nro_orden,
                            "presupuestos_proyecto_generales_id" => $this->_presupuestos_proyecto_generales_id,
                        ]);

                        $presupuestoTransactions->getMatrixUpdateItems();

                        $resp['success'] = true;
                        $resp['message'] = 'Presupuesto registrado';
                        $resp['data'] = ['id' => $this->_id, 'title_id' => $title_id, 'partida_id' => $partida_id];
                    } else {
                        $resp['success'] = false;
                        $resp['message'] = 'No se puede actualizar el registro';
                    }
                } else {
                    $plan = new Plan();
                    $proyecto_general = self::fetchObj("SELECT * FROM proyecto_generales 
                                                    WHERE id = :id;", ['id' => $this->_proyecto_generales_id]);

                    $result = $plan->getValidate(['modulo' => 2, 'user_id' => @$proyecto_general->users_id,'proyecto_id' => $this->_proyecto_generales_id]);

                    if (!$result['success']) {
                        $resp['success'] = false;
                        $resp['message'] = $result['message'];
                        return $resp;
                    }
                    $insert = self::insert("presupuestos", $this->_values);
                    if ($insert && $insert["lastInsertId"]) {
                        if ($this->_partidas_id) {
                            $partidaDetail = new PartidaDetail();
                            $partidaDetail->getSave([
                                'partidas_id' => $this->_partidas_id,
                                'master_partidas_id' => $this->_master_partidas_id,
                                'id' => $insert["lastInsertId"],
                                'subpresupuestos_id' => $this->_subpresupuestos_id,
                                'proyecto_generales_id' => $this->_proyecto_generales_id,
                            ]);
                        }

                        $this->_id = $insert["lastInsertId"];
                        $this->savePresupuestoPartida();

                        $presupuestoTransactions = new PresupuestoTransactions([
                            "id" => $insert["lastInsertId"],
                            "nro_order" => $this->_nro_orden,
                            "presupuestos_proyecto_generales_id" => $this->_presupuestos_proyecto_generales_id,
                        ]);
                        $presupuestoTransactions->getMatrixUpdateItems();

                        $this->defaultMetered();

                        $id = $insert["lastInsertId"];
                        $resp['success'] = true;
                        $resp['message'] = 'Presupuesto registrado';
                        $resp['data'] = compact('id', 'title_id');
                    } else {
                        $resp['success'] = false;
                        $resp['message'] = 'Ocurrió un erro al registrar presupuesto';
                    }
                }
            } else {
                $resp['success'] = false;
                $resp['message'] = 'El presupuesto ya cuenta con un metrado o APU creado';
            }
            return $resp;
        } catch (\Throwable $th) {
            error_log("Error al registrar presupuesto: " . $th->getMessage());
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getOrder()
    {
        if ($this->_presupuestos_proyecto_generales_id) {
            if ($this->_nro_orden) {
                $nro_orden = ($this->_nro_orden * 1);
                $sql = "SET @norder = {$nro_orden}; 
                    UPDATE presupuestos SET nro_orden = (@norder:=@norder+1) 
                    WHERE presupuestos_proyecto_generales_id = {$this->_presupuestos_proyecto_generales_id} AND nro_orden >= {$nro_orden} ORDER BY nro_orden ASC";
                self::ex($sql);
            }
        }
    }

    public function getDelete()
    {
        try {
            $sql = 'SELECT id, presupuestos_proyecto_generales_id, nro_orden, type_item FROM presupuestos 
                    WHERE id = :id  AND deleted_at IS NULL';
            $presupuestos = self::fetchObj($sql, ['id' => $this->_id]);
            if ($presupuestos) {
                $this->removerRecursive($this->_id, $presupuestos->type_item);
                $presupuestoTransactions = new PresupuestoTransactions([
                    "id" => $this->_id,
                    "nro_order" => $presupuestos->nro_orden,
                    "presupuestos_proyecto_generales_id" => $presupuestos->presupuestos_proyecto_generales_id,
                ]);
                $presupuestoTransactions->getMatrixUpdateItems(true);
                $resp['success'] = true;
                $resp['message'] = 'Presupuesto eliminado';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'No se puede eliminar el presupuesto';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar el presupuesto';
            return $resp;
        }
    }

    public function getListPresupuesto()
    {
        try {
        // ✅ CONSTRUIR LA CONDICIÓN DINÁMICAMENTE
            $subpresupuestoCondition = "";

            if (!empty($this->_subpresupuestos_id)) {
                // Si hay subpresupuesto, agregar la condición
                $subpresupuestoCondition = "AND subpresupuestos_id IN ({$this->_subpresupuestos_id})";
            }


            $sql_general = "SELECT        
                        id,
                        descripcion AS 'name',
                        unidad_medidas_id AS 'uni',
                        proyecto_generales_id,
                        partidas_id,
                        subpresupuestos_id,
                        presupuestos_title_id,
                        cu,                        
                        mo,
                        mt AS mat,
                        eq,
                        sc,
                        sp,
                        metrado AS 'metered',                        
                        presupuestos_proyecto_generales_id,
                        nro_orden AS 'level',
                        type_item
                FROM presupuestos 
                WHERE proyecto_generales_id = :id 
                        AND deleted_at IS NULL
                        {$subpresupuestoCondition}
                ORDER BY nro_orden ASC";

            $presupuestos_general = self::fetchAllObj($sql_general, ['id' => $this->_id]);
            $data = array();

            foreach ($presupuestos_general as $key => $value) {
                if ($value->presupuestos_proyecto_generales_id == null || $value->presupuestos_proyecto_generales_id == 0) {
                    $detail = $this->setMatrizPresupuesto($value->id, $presupuestos_general);
                    $total = 0;
                    foreach ($detail as $item) {
                        $total += ($item->total_parcial * 1);
                    }
                    $presupuestos_general[$key]->detail = $detail;
                    $presupuestos_general[$key]->total_parcial = $total;
                    array_push($data, $presupuestos_general[$key]);
                }
            }

            return $data;
        } catch (\Throwable $th) {
            error_log("Error: " . $th->getMessage());
            return [];
        }
    }

    public function setMatrizPresupuesto($searchedValue, $dataPresupuesto)
    {
        $array = [];
        $pt = 0.00;
        $object =   array_filter(
            $dataPresupuesto,
            function ($e) use ($searchedValue) {
                return $e->presupuestos_proyecto_generales_id == $searchedValue;
            }
        );

        foreach ($object as $value) {
            array_push($array, $value);
        }

        $childrens = $array;
        if (count($childrens)) {
            foreach ($childrens as $key => $value) {
                if ($value->type_item == '3') {
                    $metrado = $value->metered ? $value->metered : 0;
                    $cu = $value->cu ? $value->cu : 0;

                    $total_parcial = ($metrado * $cu);

                    $childrens[$key]->total_parcial = $total_parcial;

                    $met = $value->metered ? $value->metered : 0;

                    $childrens[$key]->mo  = $value->mo  ? $value->mo  * $met : 0;
                    $childrens[$key]->mat = $value->mat ? $value->mat * $met : 0;
                    $childrens[$key]->eq  = $value->eq  ? $value->eq  * $met : 0;
                    $childrens[$key]->sc  = $value->sc  ? $value->sc  * $met : 0;
                    $childrens[$key]->sp  = $value->sp  ? $value->sp  * $met : 0;

                    $childrens[$key]->unmetered = $met == 0;

                    // TRAER APUS / SUBPARTIDAS
                    /*
                    $apus = $this->getDetallePartida($value->id);

                    $childrens[$key]->detail = $apus;*/

                    continue;
                }
                $detail = $this->setMatrizPresupuesto($value->id, $dataPresupuesto);
                $total = 0;
                foreach ($detail as $item) {
                    $total += ($item->total_parcial * 1);
                }
                $childrens[$key]->total_parcial = $total; // number_format($total, 2, '.', '');
                $childrens[$key]->detail = $detail;
            }
        }

        return $childrens;
    }

    /*
    private function getDetallePartida($presupuestoId, $subpartidaId = null)
    {
        error_log("=== getDetallePartida ===");
        error_log("presupuestoId: " . $presupuestoId);
        error_log("subpartidaId: " . ($subpartidaId ?? 'NULL'));
        try {
            $where = 'WHERE pp.presupuestos_id = :id AND pp.partida_id IS NOT NULL';
            $id = $presupuestoId;

            if ($subpartidaId) {
                $where = 'WHERE pp.subpartida_id = :id';
                $id = $subpartidaId;
            }

            $sql = "SELECT
                        pp.id,
                        pp.iu,
                        pp.cuadrilla,
                        pp.cantidad,
                        pp.unidad_medidas_id,
                        pp.presupuestos_id,
                        pp.partida_id,
                        pp.subpartida_id,
                        pp.precio AS punit,
                        ip.id AS insumo_id,
                        ip.tipo,
                        ip.insumos,
                        ip.precio,
                        um.alias,
                        um.apu_cantidad,
                        pt.partida
                    FROM apus_partida_presupuestos pp
                    LEFT JOIN insumos_proyecto ip ON pp.insumo_id = ip.id
                    LEFT JOIN partidas_proyecto pt ON pp.partida_id = pt.id
                    INNER JOIN unidad_medidas um ON um.id = pp.unidad_medidas_id
                    {$where}
                    AND pp.deleted_at IS NULL";

            $detalle = self::fetchAllObj($sql, ['id' => $id]);

            // SI UNA FILA ES SUBPARTIDA, CARGAR SUS HIJOS
            $newDetalle = [];

            foreach ($detalle as $item) {
                // =========================
                // SUBPARTIDA
                // =========================
                if ($item->partida_id) {
                    $newItem = new \stdClass();

                    $newItem->id = $item->id;
                    $newItem->name = $item->partida;
                    $newItem->uni = $item->unidad_medidas_id;

                    $newItem->proyecto_generales_id = null;
                    $newItem->partidas_id = $item->partida_id;
                    $newItem->subpresupuestos_id = null;
                    $newItem->presupuestos_title_id = null;

                    $newItem->cu = $item->punit ?? 0;

                    $newItem->mo = 0;
                    $newItem->mat = 0;
                    $newItem->eq = 0;
                    $newItem->sc = 0;
                    $newItem->sp = 0;

                    $newItem->metered = $item->cantidad;

                    $newItem->presupuestos_proyecto_generales_id = $item->presupuestos_id;

                    $newItem->level = null;

                    $newItem->type_item = '3';

                    $newItem->total_parcial = 0;

                    $newItem->unmetered = true;

                    $newItem->detail = $this->getDetallePartida(
                        $presupuestoId,
                        $item->id
                    );

                    $newDetalle[] = $newItem;
                }
            }

            return $newDetalle;
        } catch (Exception $e) {
            error_log("Error al traer detalle de partida: " . $e->getMessage());
            return [];
        }
    }*/

        // ====================================
        // MOVER PARTIDA
        // ====================================
        /**
     * Método principal para mover partida a otra partida (convertirla en subpartida)
     */
    public function moverPartida($request)
    {
        try {
            $itemId = $request->item_id ?? null;
            $newParentId = $request->new_parent_id ?? null;
            $proyectoId = $request->proyecto_generales_id ?? null;

            // =========================
            // VALIDACIONES BÁSICAS
            // =========================
            if (!$itemId || !$newParentId || !$proyectoId) {
                return [
                    'success' => false,
                    'message' => 'Faltan parámetros'
                ];
            }

            // No permitir mover un item a si mismo
            if ($itemId == $newParentId) {
                return [
                    'success' => false,
                    'message' => 'No puedes mover un item dentro de sí mismo'
                ];
            }

            // =========================
            // BUSCAR ITEM A MOVER
            // =========================
            $sqlItem = "SELECT
                            id,
                            descripcion,
                            type_item,
                            presupuestos_proyecto_generales_id,
                            proyecto_generales_id
                        FROM presupuestos
                        WHERE id = :id
                        AND proyecto_generales_id = :proyecto_id
                        AND deleted_at IS NULL";

            $item = self::fetchObj($sqlItem, [
                'id' => $itemId,
                'proyecto_id' => $proyectoId
            ]);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'La partida no existe'
                ];
            }

            // =========================
            // BUSCAR NUEVO PADRE
            // =========================
            $sqlParent = "SELECT
                            id,
                            descripcion,
                            type_item,
                            presupuestos_proyecto_generales_id,
                            subpresupuestos_id
                        FROM presupuestos
                        WHERE id = :id
                        AND proyecto_generales_id = :proyecto_id
                        AND deleted_at IS NULL";

            $parent = self::fetchObj($sqlParent, [
                'id' => $newParentId,
                'proyecto_id' => $proyectoId
            ]);

            if (!$parent) {
                return [
                    'success' => false,
                    'message' => 'El nuevo padre no existe'
                ];
            }

            // =========================
            // DETERMINAR TIPO DE MOVIMIENTO
            // =========================

            if ($parent->type_item == 1) {
                // MOVER A TÍTULO
                error_log("Movimiento a TÍTULO (type_item=1)");
                return $this->moverATitulo($itemId, $newParentId, $proyectoId);
            } elseif ($parent->type_item == 3) {
                // MOVER A PARTIDA (crear subpartida)
                $subpartida = new SubpartidaProyecto();
                $data = (object) [
                    'partida' => $item->descripcion ?? '',
                    'rendimiento' => 0,
                    'unidad_medidas_id' => 9,
                    'id' => 0,
                    'masterid' => 0,
                    'descripcion' => $item->descripcion ?? '',
                    'proyectos_generales_id' => $proyectoId,
                    'presupuestos_id' => $parent->id,
                    'cuadrilla' => 0,
                    'precio' => 0,
                    'cantidad' => 0,
                    'subpresupuestos_id' => $parent->subpresupuestos_id,
                    'rendimiento_unid' => 'UND/DÍA'
                ];
                $result = $subpartida->save($data);

                if ($result['success']) {
                    self::update(
                        'presupuestos',
                        [
                            'deleted_at' => date('Y-m-d H:i:s')
                        ],
                        [
                            'id' => $itemId
                        ]
                    );
                }

                return $result;
            } else {
                return [
                    'success' => false,
                    'message' => 'Tipo de destino no válido'
                ];
            }
        } catch (\Throwable $th) {
            error_log("Error al mover partida: " . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error al mover partida: ' . $th->getMessage()
            ];
        }
    }

    /**
     * Mueve una partida a un título (solo cambia el padre)
     */
    private function moverATitulo($itemId, $titleId, $proyectoId)
    {
        self::update('presupuestos', [
            'presupuestos_proyecto_generales_id' => $titleId
        ], [
            'id' => $itemId
        ]);

        return [
            'success' => true,
            'message' => 'Partida movida al título correctamente'
        ];
    }

    private function savePresupuestoPartida()
    {
        if ($this->_type_item == '3') {
            $var = [];
            $sql_a = 'SELECT id FROM presupuestos_partida 
                  WHERE presupuestos_id = :presupuestos_id AND proyectos_generales_id = :proyectos_generales_id AND subpartida_id IS NULL';
            $val_a = self::fetchObj($sql_a, ['presupuestos_id' => $this->_id, 'proyectos_generales_id' => $this->_proyecto_generales_id]);
            if ($this->_rendimiento) {
                $var['rendimiento'] = $this->_rendimiento;
            }
            if ($this->_rendimiento_unid) {
                $var['rendimiento_unid'] = $this->_rendimiento_unid;
            }
            if ($this->_partidas_id) {
                $var['partida_id'] = $this->_partidas_id;
            }
            if ($val_a) {
                if (!empty($var)) {
                    self::update(
                        "presupuestos_partida",
                        $var,
                        ['presupuestos_id' => $this->_id, 'proyectos_generales_id' => $this->_proyecto_generales_id]
                    );
                }
            } else {
                $var['presupuestos_id'] = $this->_id;
                $var['proyectos_generales_id'] = $this->_proyecto_generales_id;
                self::insert("presupuestos_partida", $var);
            }
        }
    }

    public function updateBudgetFooter($request)
    {
        $response = [];
        $sql = 'SELECT id FROM proyecto_pie_presupuesto WHERE proyectos_generales_id = :pgid AND pie_presupuesto_id = :pie_pid';
        $pie = self::fetchObj($sql, ['pgid' => $request->proyectos_generales_id, 'pie_pid' => $request->pie_presupuesto_id]);
        $percentage = number_format(($request->percentage / 100), 2, '.', '');
        if ($pie) {
            self::update('proyecto_pie_presupuesto', [
                'percentage' => $percentage
            ], ['id' => $pie->id]);
        } else {
            self::insert('proyecto_pie_presupuesto', [
                'percentage' => $percentage,
                'type_percentage' => 'PIE',
                'proyectos_generales_id' => $request->proyectos_generales_id,
                'pie_presupuesto_id' => $request->pie_presupuesto_id
            ]);
        }

        $response['success'] = true;
        $response['message'] = 'Datos guardados';
        $response['data'] = array(
            'percentage' => $percentage
        );

        return $response;
    }

    private function removerRecursive($id, $type_item)
    {
        $deleted_at = FG::getFechaHora();
        self::ex("UPDATE presupuestos SET deleted_at = '{$deleted_at}' WHERE id = {$id} OR presupuestos_proyecto_generales_id = {$id}");
        if ($type_item && $type_item != 3) {
            $sql = 'SELECT id, type_item FROM presupuestos WHERE presupuestos_proyecto_generales_id = :id';
            $presupuestos = self::fetchAllObj($sql, ['id' => $id]);
            foreach ($presupuestos as $key => $value) {
                $this->removerRecursive($value->id, $value->type_item);
            }
        } elseif ($type_item == 3) {
            self::update("apus_partida_presupuestos", ['deleted_at' => $deleted_at], ['presupuestos_id' => $id]);
        }
    }

    private function defaultMetered()
    {
        $mtdo = [
            'presupuestos_id' => $this->_id,
            'proyecto_generales_id' => $this->_proyecto_generales_id,
            'descripcion' => '',
            'position' => 1,
            'metrado_largo' => 0,
            'metrado_ancho' => 0
        ];
        self::insert("metrado_partida_presupuestos", $mtdo);
    }

    public function updateDescription($request)
    {
        $response = [];
        $params = [
            'descripcion' => $request->descripcion
        ];
        self::update('presupuestos', $params, ['id' => $request->id]);
        $response['success'] = true;
        $response['message'] = 'Datos guardados';
        $response['data'] = $params;
        return $response;
    }



    public function searchPresupuestoByTerm($term, $proyectoId, $subpresupuestoId = null)
    {
        try {
            error_log("=== searchPresupuestoByTerm ===");
            error_log("term: " . $term);
            error_log("proyectoId: " . $proyectoId);
            error_log("subpresupuestoId: " . ($subpresupuestoId ?? 'NULL'));

            // ✅ Preparar el término de búsqueda
            $searchTerm = "%" . $term . "%";

            // ✅ Construir la condición del subpresupuesto
            $subpresupuestoCondition = "";
            $params = [
            'search' => $searchTerm,
            'proyecto_id' => (int)$proyectoId // ✅ Cast a int
            ];

            if (!empty($subpresupuestoId)) {
                // ✅ Agregar condición y parámetro para subpresupuesto
                $subpresupuestoCondition = "AND p.subpresupuestos_id = :subpresupuesto_id";
                $params['subpresupuesto_id'] = (int)$subpresupuestoId; // ✅ Cast a int

                error_log("Condición subpresupuesto: " . $subpresupuestoCondition);
                error_log("Valor subpresupuesto: " . $params['subpresupuesto_id']);
            } else {
                error_log("Sin filtro de subpresupuesto");
            }

            // ✅ Construir SQL con condición dinámica
            $sql = "SELECT DISTINCT p.*
                FROM presupuestos p
                LEFT JOIN apus_partida_presupuestos apu 
                    ON p.id = apu.presupuestos_id 
                    AND apu.deleted_at IS NULL
                LEFT JOIN insumos_proyecto i 
                    ON apu.insumo_id = i.id
                WHERE p.proyecto_generales_id = :proyecto_id
                    AND p.deleted_at IS NULL
                    {$subpresupuestoCondition}
                    AND (
                        p.descripcion LIKE :search 
                        OR i.insumos LIKE :search
                        OR i.codigo LIKE :search 
                    )
                ORDER BY p.nro_orden ASC";

            error_log("SQL generado:");
            error_log($sql);
            error_log("Parámetros SQL: " . print_r($params, true));

            // ✅ Ejecutar query
            $resultados = self::fetchAllObj($sql, $params);

            error_log("Filas encontradas: " . count($resultados));

            if (count($resultados) > 0) {
                error_log("Primera fila: " . print_r($resultados[0], true));
            }

            return $resultados;
        } catch (\Exception $e) {
            error_log("ERROR en searchPresupuestoByTerm: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            throw $e;
        }
    }
}
