<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class Subcategoria extends Mysql
{
    private  $_id;
    private  $_descripcion;
    private  $_subcategorias_id;
    private  $_proyecto_generales_id;
    private  $_subcategorias;

    public function getId()
    {
        return $this->_id;
    }
    public function getDescripcion()
    {
        return $this->_descripcion;
    }
    public function getSubcategoriasId()
    {
        return $this->_subcategorias_id;
    }
    public function getProyectoGeneralesId()
    {
        return $this->_proyecto_generales_id;
    }
    public function getSubcategorias()
    {
        return $this->_subcategorias;
    }

    public function __construct($array = [])
    {
        $this->_id = (array_key_exists('id', $array)) ? $array['id'] : "0";
        $this->_descripcion = (array_key_exists('descripcion', $array)) ? $array['descripcion'] : "";
        $this->_subcategorias_id = (array_key_exists('subcategorias_id', $array)) ? $array['subcategorias_id'] : "";
        $this->_proyecto_generales_id = (array_key_exists('proyecto_generales_id', $array)) ? $array['proyecto_generales_id'] : "";
        $this->_subcategorias = (array_key_exists('subcategorias', $array)) ? $array['subcategorias'] : "";
    }

    public function getList()
    {
        $sql = 'SELECT id, descripcion FROM subcategorias';
        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = self::fetchAllObj($sql);
        return $resp;
    }

    public function getListFilter()
    {
        $sql = " SELECT id, descripcion, subcategorias_master_id, orden
                 FROM subcategorias_proyecto_general
                 WHERE proyecto_generales_id = :id  ORDER BY orden ASC";
        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = self::fetchAllObj($sql, ["id" => $this->_id]);
        return $resp;
    }

    public function getCreateArray()
    {
        try {
            $resp = ['success' => false, 'message' => 'Error al asignar subpresupuestos'];
            $sql = "SELECT id, descripcion, subcategorias_master_id
                    FROM subcategorias_proyecto_general
                    WHERE proyecto_generales_id = :id";
            $list = self::fetchAllObj($sql, ["id" => $this->_proyecto_generales_id]);
            $subpresupuestos = json_decode($this->_subcategorias);
            if ($subpresupuestos) {
                $i = 1;
                foreach ($subpresupuestos as $key => $value) {
                    if ($value->id != 0) {
                        $val = [
                            'descripcion' => $value->name,
                            'orden' => $i,
                            "proyecto_generales_id" =>  $this->_proyecto_generales_id,
                        ];
                        if ($value->masterId) $val['subcategorias_master_id'] = $value->masterId;
                        self::update("subcategorias_proyecto_general", $val, ['id' => $value->id]);
                    } else if ($value->id == 0 && $value->masterId != 0) {
                        $value = [
                            'descripcion' => $value->name,
                            'subcategorias_master_id' => $value->masterId,
                            'orden' => $i,
                            "proyecto_generales_id" =>  $this->_proyecto_generales_id,
                        ];
                        self::insert("subcategorias_proyecto_general", $value);
                    } else {
                        $val = [
                            'descripcion' => $value->name,
                            'orden' => $i,
                            "proyecto_generales_id" =>  $this->_proyecto_generales_id,
                        ];
                        self::insert("subcategorias_proyecto_general", $val);
                    }
                    $i++;
                }
                foreach ($list as $item) {
                    $searchedValue = $item->id;
                    $object =   array_filter($subpresupuestos, function ($e) use ($searchedValue) {
                        return $e->id == $searchedValue;
                    });
                    if (empty($object)) {
                        self::delete("subcategorias_proyecto_general", ['id' => $item->id]);
                    }
                }
                $resp['success'] = true;
                $resp['message'] = 'Se guardo registrado';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Se proyecto no encontrado';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getSave()
    {
        try {
            $value = [
                'descripcion' => $this->_descripcion,
                'orden' => $this->_orden,
                'proyecto_generales_id' => $this->_proyecto_generales_id,
            ];
            if ($this->_id) {
                $sql = 'SELECT COUNT(id) FROM subcategorias_proyecto_general WHERE id = :id';
                $analisisPreciosUnitarios = self::fetchObj($sql, ['id' => $this->_id]);
                if ($analisisPreciosUnitarios) {
                    $update = self::update("subcategorias_proyecto_general", $value, ['id' => $this->_id]);
                    $resp['success'] = true;
                    $resp['message'] = 'Se ha actualizado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $insert = self::insert("subcategorias_proyecto_general", $value);

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
            $sql = 'SELECT COUNT(id) AS idSubcategoria FROM subcategorias 
                    WHERE id = :id  ';
            $apud = self::fetchObj($sql, ['id' => $this->_id]);
            if (count($apud)) {
                self::update('subcategorias', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $this->_id]);
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

    public function getSubPresupuestoParcial()
    {

        $listSubPresupuestos = $this->getListFilter();
        $listSubPresupuestos = $listSubPresupuestos['data'];
        $sql_general = "SELECT        
                        id,
                        proyecto_generales_id,
                        subpresupuestos_id,
                        cu,                        
                        mo,
                        mt AS mat,
                        eq,
                        sc,
                        sp,
                        metrado AS 'metered',                        
                        presupuestos_proyecto_generales_id,
                        type_item
                FROM presupuestos 
                WHERE proyecto_generales_id = :id  AND deleted_at is NULL";
        $presupuestos_general = self::fetchAllObj($sql_general, ['id' => $this->_id]);
        $data = [];
        foreach ($presupuestos_general as  $key => $value) {
            if ($value->presupuestos_proyecto_generales_id == NULL || $value->presupuestos_proyecto_generales_id == 0) {
                $detail = $this->setMatrizPresupuesto($value->id, $presupuestos_general);
                $total = 0;
                foreach ($detail as $item) {
                    $total += ($item->total_parcial * 1);
                }
                $total_parcial = number_format($total, 2, '.', '');
                if (isset($data[$value->subpresupuestos_id])) {
                    $data[$value->subpresupuestos_id] = $data[$value->subpresupuestos_id] + $total_parcial;
                } else {
                    $data[$value->subpresupuestos_id] = $total_parcial;
                }
            }
        }

        foreach ($listSubPresupuestos as $key => $value) {
            if (isset($data[$value->id])) {
                $listSubPresupuestos[$key]->total_parcial = $data[$value->id];
            } else {
                $listSubPresupuestos[$key]->total_parcial = 0.00;
            }
        }

        return $listSubPresupuestos;
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
                    $total_parcial = number_format(($metrado * $cu), 2, '.', '');
                    $childrens[$key]->total_parcial = $total_parcial;
                    $met = $value->metered ? $value->metered : 0;
                    $childrens[$key]->mo = $value->mo ? number_format(($value->mo * $met), 2, '.', '') : 0;
                    $childrens[$key]->mat = $value->mat ? number_format(($value->mat * $met), 2, '.', '') : 0;
                    $childrens[$key]->eq = $value->eq ? number_format(($value->eq * $met), 2, '.', '') : 0;
                    $childrens[$key]->sc = $value->sc ? number_format(($value->sc * $met), 2, '.', '') : 0;
                    $childrens[$key]->sp = $value->sp ? number_format(($value->sp * $met), 2, '.', '') : 0;
                    continue;
                }
                $detail = $this->setMatrizPresupuesto($value->id, $dataPresupuesto);
                $total = 0;
                foreach ($detail as $item) {
                    $total += ($item->total_parcial * 1);
                }
                $childrens[$key]->total_parcial = number_format($total, 2, '.', '');
                $childrens[$key]->detail = $detail;
            }
        }

        return $childrens;
    }
}
