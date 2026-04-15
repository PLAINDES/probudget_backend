<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class EspecificacionesTecnicas extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_presupuestos_id;
    public function getPresupuestosId()
    {
        return $this->_presupuestos_id;
    }
    private $_titulo;
    public function gettitulo()
    {
        return $this->_titulo;
    }
    private $_descripcion;
    public function getdescripcion()
    {
        return $this->_descripcion;
    }
    private $_proyecto_generales_id;
    public function getProyectoGeneralesId()
    {
        return $this->_proyecto_generales_id;
    }
    private $_position;
    public function getPosition()
    {
        return $this->_position;
    }
    private $_values;
    public function getValue()
    {
        return $this->_values;
    }
    private $_subpresupuesto_id;

    public function __construct($request, $array = [])
    {
        if ($request) {
            $column = [
                'id',
                'presupuestos_id',
                'titulo',
                'descripcion',
                'proyecto_generales_id',
                'position'
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    if ($value != 'id') {
                        $this->_values[$value] = $request->{$value};
                    }
                    $this->{"_$value"} = $request->{$value};
                }
            }
            if ($request->subpresupuesto_id) {
                $this->_subpresupuesto_id = $request->subpresupuesto_id;
            }
        }
    }

    public function getSave()
    {
        try {
            if ($this->_id) {
                $sql = 'SELECT presupuestos_id, position FROM especificaciones_tecnicas WHERE id = :id';
                $eept = self::fetchObj($sql, ['id' => $this->_id]);
                if ($eept) {
                    $update = self::update("especificaciones_tecnicas", $this->_values, ['id' => $this->_id]);
                    if ($this->_position) {
                        $this->_position = $this->_position * 1;
                        if ($eept->position < $this->_position) {
                            $sql = "SET @norder = {$this->_position};
                                UPDATE especificaciones_tecnicas SET position = (@norder:=@norder-1) WHERE presupuestos_id = {$eept->presupuestos_id}
                                AND position <= {$this->_position} AND id <> {$this->_id} AND deleted_at IS NULL ORDER BY position ASC;";
                        } else {
                            $sql = "SET @norder = {$this->_position};
                                UPDATE especificaciones_tecnicas SET position = (@norder:=@norder+1) WHERE presupuestos_id = {$eept->presupuestos_id}
                                AND position >= {$this->_position} AND id <> {$this->_id} AND deleted_at IS NULL ORDER BY position ASC;";
                        }
                        self::ex($sql);
                    }
                    $resp['success'] = true;
                    $resp['message'] = 'se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $insert = self::insert("especificaciones_tecnicas", $this->_values);

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

    public function getDelete()
    {
        try {
            $sql = 'SELECT COUNT(id) AS idgg FROM especificaciones_tecnicas 
                    WHERE id = :id';
            $apud = self::fetchObj($sql, ['id' => $this->_id]);
            if ($apud) {
                self::update('especificaciones_tecnicas', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $this->_id]);
                $resp['success'] = true;
                $resp['message'] = 'Se elimino el registro';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'No se puede eliminar el registro';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar el registro';
            return $resp;
        }
    }

    public function getListado()
    {
        try {
            error_log("Valor REAL en modelo _subpresupuesto_id: " . var_export($this->_subpresupuesto_id, true));

            $sql_general = "SELECT id, descripcion, presupuestos_proyecto_generales_id, type_item
                            FROM presupuestos
                            WHERE proyecto_generales_id = :proyecto_generales_id
                            AND deleted_at IS NULL
                            AND subpresupuestos_id IN ({$this->_subpresupuesto_id})
                            ORDER BY nro_orden ASC";
            $data = self::fetchAllObj($sql_general, ['proyecto_generales_id' => $this->_proyecto_generales_id]);

            $sql_general = "SELECT pt.id AS presupuestos_id, et.id, et.titulo, et.descripcion
                            FROM especificaciones_tecnicas et
                            INNER JOIN presupuestos pt  ON pt.id = et.presupuestos_id
                            WHERE et.proyecto_generales_id = :proyecto_generales_id 
                            AND et.deleted_at IS NULL
                            AND pt.subpresupuestos_id IN ({$this->_subpresupuesto_id})
                            ORDER BY et.position ASC";
            $especs = self::fetchAllObj($sql_general, ['proyecto_generales_id' => $this->_proyecto_generales_id]);

            $detail = [];
            $k = $this->getOrderSubBudget($this->_subpresupuesto_id);
            foreach ($data as $i => $value) {
                if ($value->presupuestos_proyecto_generales_id == null || $value->presupuestos_proyecto_generales_id == '') {
                    $index = $k + 1;
                    $index = $this->baseindex('', $index);
                    $this->assembled($data, $value->id, $index, $detail);
                    $k++;
                }
            }

            usort($detail, function ($a, $b) {
                return $a->index > $b->index;
            });

            $group = [];
            foreach ($especs as $key => $value) {
                if (!isset($group[$value->presupuestos_id])) {
                    $group[$value->presupuestos_id] = [];
                }
                array_push($group[$value->presupuestos_id], $value);
            }

            foreach ($detail as $key => $value) {
                if (isset($group[$value->id])) {
                    $detail[$key]->detail = $group[$value->id];
                } else {
                    $detail[$key]->detail = [];
                }
            }

            return $detail;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Ocurrió un erro al registrar insumo';
            return $resp;
        }
    }

    public function getUnitaria()
    {
        try {
            $sql = "SELECT         
                           	especificaciones_tecnicas.id,
                            especificaciones_tecnicas.presupuestos_id,
                            titulo,
                            especificaciones_tecnicas.descripcion,
                            presupuestos.descripcion AS 'presupuestos_descripcion'
                    FROM especificaciones_tecnicas 
                    INNER JOIN presupuestos  ON especificaciones_tecnicas.presupuestos_id = presupuestos.id                    
                    WHERE especificaciones_tecnicas.presupuestos_id = :id AND especificaciones_tecnicas.deleted_at is NULL ORDER BY especificaciones_tecnicas.position ASC";
            return self::fetchAllObj($sql, ['id' => $this->_id]);
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'Ocurrió un erro al registrar insumo';
            return $resp;
        }
    }

    private function assembled($data, $parent, $index, &$detail)
    {
        $k = 0;
        foreach ($data as $i => $value) {
            if ($value->presupuestos_proyecto_generales_id == $parent) {
                $idx = $k + 1;
                $idx = $this->baseindex($index, $idx);
                $item = $this->assembled($data, $value->id, $idx, $detail);
                $value->detail = $item;
                $value->index = $idx;
                if ($value->type_item == 3) {
                    $detail[$value->id] = $value;
                }
                $k++;
            }
        }
    }

    private function baseindex($num, $idx)
    {
        $index = $this->addzero($idx);
        return (!$num) ? $index : "{$num}.{$index}";
    }

    private function addzero($i)
    {
        if ($i < 10) {
            $i = "0{$i}";
        }
        return $i;
    }

    public function getItemsPresupuestos($proyectos_generales_id, $subpresupuestos_id)
    {
        $sql = "SELECT id, descripcion, presupuestos_proyecto_generales_id, type_item, subpresupuestos_id
                FROM presupuestos
                WHERE proyecto_generales_id = :proyecto_generales_id
                AND deleted_at IS NULL
                AND subpresupuestos_id IN ({$subpresupuestos_id})
                ORDER BY subpresupuestos_id ASC, nro_orden ASC";
        $data = self::fetchAllObj($sql, ['proyecto_generales_id' => $proyectos_generales_id]);

        $group_keys = [];
        foreach ($data as $key => $item) {
            $group_keys[$item->subpresupuestos_id][] = $item;
        }

        $detail = [];
        $increment = $this->getOrderSubBudget($subpresupuestos_id);
        foreach ($group_keys as $key => $items) {
            $k = 0;
            $groups = [];
            foreach ($items as $i => $value) {
                if ($value->presupuestos_proyecto_generales_id == null || $value->presupuestos_proyecto_generales_id == '') {
                    $index = $k + 1;
                    $index = $this->baseindex('', $index);
                    $this->assembled($items, $value->id, $index, $detail);
                    $k++;
                }
            }
            if (count(explode(',', $subpresupuestos_id)) > 1) {
                foreach ($detail as $k2 => $d2) {
                    if ($d2->subpresupuestos_id == $key) {
                        $detail[$k2]->index = "0" . ($increment + 1) . "." . $detail[$k2]->index;
                    }
                }
            }
            $increment++;
        }

        usort($detail, function ($a, $b) {
            return $a->index > $b->index;
        });

        return $detail;
    }

    private function getOrderSubBudget($subpresupuestos_id)
    {
        $sql = 'SELECT orden FROM subcategorias_proyecto_general WHERE id = :id';
        $subpresupuesto = self::fetchObj($sql, [
            'id' => $subpresupuestos_id
        ]);
        if (!$subpresupuesto) {
            return 0;
        }

        return ($subpresupuesto->orden - 1);
    }

    // Agregar este método en la clase EspecificacionesTecnicas

    public function getAll()
    {
        try {
            // Obtener todas las partidas del subpresupuesto
            $sql_general = "SELECT id, `index`, descripcion, presupuestos_proyecto_generales_id, type_item
                        FROM presupuestos
                        WHERE proyecto_generales_id = :proyecto_generales_id
                        AND deleted_at IS NULL
                        AND subpresupuestos_id = :subpresupuesto_id
                        ORDER BY nro_orden ASC";

            $data = self::fetchAllObj($sql_general, [
            'proyecto_generales_id' => $this->_proyecto_generales_id,
            'subpresupuesto_id' => $this->_subpresupuesto_id
            ]);

            // Obtener todas las especificaciones técnicas
            $sql_especs = "SELECT et.id, et.titulo, et.descripcion, et.presupuestos_id, et.position
                       FROM especificaciones_tecnicas et
                       INNER JOIN presupuestos pt ON pt.id = et.presupuestos_id
                       WHERE et.proyecto_generales_id = :proyecto_generales_id 
                       AND et.deleted_at IS NULL
                       AND pt.subpresupuestos_id = :subpresupuesto_id
                       ORDER BY et.position ASC";

            $especs = self::fetchAllObj($sql_especs, [
                'proyecto_generales_id' => $this->_proyecto_generales_id,
                'subpresupuesto_id' => $this->_subpresupuesto_id
            ]);

            // Construir la estructura jerárquica de partidas
            $detail = [];
            $k = $this->getOrderSubBudget($this->_subpresupuesto_id);

            foreach ($data as $i => $value) {
                if ($value->presupuestos_proyecto_generales_id == null || $value->presupuestos_proyecto_generales_id == '') {
                    $index = $k + 1;
                    $index = $this->baseindex('', $index);
                    $this->assembled($data, $value->id, $index, $detail);
                    $k++;
                }
            }

            // Ordenar por índice
            usort($detail, function ($a, $b) {
                return $a->index > $b->index;
            });

            // Agrupar especificaciones por presupuesto_id
            $group = [];
            foreach ($especs as $key => $value) {
                if (!isset($group[$value->presupuestos_id])) {
                    $group[$value->presupuestos_id] = [];
                }
                array_push($group[$value->presupuestos_id], $value);
            }

            // Asignar las especificaciones a cada partida
            foreach ($detail as $key => $value) {
                if (isset($group[$value->id])) {
                    $detail[$key]->detail = $group[$value->id];
                } else {
                    $detail[$key]->detail = [];
                }
            }

            $resp['success'] = true;
            $resp['data'] = $detail;
            $resp['message'] = 'successfully';

            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }
}
