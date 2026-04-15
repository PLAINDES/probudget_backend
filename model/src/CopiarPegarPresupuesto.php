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

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class CopiarPegarPresupuesto extends Mysql
{
        private $_id;
        private $partidas = [];
        private $subpartidas = [];

    public function createItem($request)
    {
            $resp = [];
            $data = [];
            $this->_id = $request->proyecto_generales_id;
            $nro_orden = $request->nro_orden;
            $presupuestoId = $request->id;
            $ids = $request->ids;
            $destPresupuesto = $request->presupuestos_proyecto_generales_id;
        try {
            if (!$destPresupuesto) {
                $sql = 'SELECT type_item FROM presupuestos WHERE id = :id';
                $presupuesto = self::fetchObj($sql, ['id' => $presupuestoId]);
                if ($presupuesto && $presupuesto->type_item == 3) {
                        $resp['success'] = false;
                        $resp['message'] = 'La partida debe estar contenida en un título o subtítulo';
                        return $resp;
                }
                $destPresupuesto = null;
            }

            if ($ids) {
                    $sql_general = "SELECT
                                                id,nro_orden,
                                                presupuestos_title_id,
                                                descripcion,
                                                metrado,cu,
                                                mo,mt,eq,sc,sp,
                                                unidad_medidas_id,
                                                proyecto_generales_id,
                                                partidas_id,
                                                presupuestos_proyecto_generales_id,
                                                subpresupuestos_id,
                                                type_item                                                
                                                FROM presupuestos 
                                                WHERE id IN({$ids}) AND proyecto_generales_id = :proyecto_generales_id AND deleted_at IS NULL";
                    $presupuestos = self::fetchAllObj($sql_general, ['proyecto_generales_id' => $this->_id]);

                if (!empty($presupuestos)) {
                    $object = array_filter($presupuestos, function ($e) use ($presupuestoId) {
                            return $e->id == $presupuestoId;
                    });

                    if (count($object)) {
                        $key = array_keys($object);
                        $key = $key[0];
                        $object = $object[$key];
                        $var = [
                        "nro_orden" => $nro_orden,
                        "descripcion" => $object->descripcion,
                        "proyecto_generales_id" => $this->_id,
                        "presupuestos_proyecto_generales_id" => $destPresupuesto
                            ];

                        if ($object->metrado) {
                            $var["metrado"] = $object->metrado;
                        }
                        if ($object->cu) {
                            $var["cu"] = $object->cu;
                        }
                        if ($object->mo) {
                            $var["mo"] = $object->mo;
                        }
                        if ($object->mt) {
                            $var["mt"] = $object->mt;
                        }
                        if ($object->eq) {
                            $var["eq"] = $object->eq;
                        }
                        if ($object->sc) {
                            $var["sc"] = $object->sc;
                        }
                        if ($object->sp) {
                            $var["sp"] = $object->sp;
                        }

                        if ($object->unidad_medidas_id) {
                            $var["unidad_medidas_id"] = $object->unidad_medidas_id;
                        }
                        if ($object->presupuestos_title_id) {
                            $var["presupuestos_title_id"] = $object->presupuestos_title_id;
                        }
                        if ($object->partidas_id) {
                            $var["partidas_id"] = $object->partidas_id;
                        }

                        if ($object->subpresupuestos_id) {
                            $var["subpresupuestos_id"] = $object->subpresupuestos_id;
                        }
                        if ($object->type_item) {
                                $var["type_item"] = $destPresupuesto ? $object->type_item : 1;
                        }

                        $insert = self::insert("presupuestos", $var);

                        $lastID = $insert['lastInsertId'];
                        $nro_orden = $nro_orden * 1;

                        $sql = "SET @norder = {$nro_orden}; UPDATE presupuestos SET nro_orden = (@norder:=@norder+1) WHERE presupuestos_proyecto_generales_id = {$destPresupuesto} AND nro_orden >= {$nro_orden} AND id <> {$lastID} ORDER BY nro_orden ASC;";
                        self::ex($sql);

                        if ($object->type_item == 3) {
                            $this->partidas[$object->id] = $insert["lastInsertId"];
                        }
                        $data[$object->id] = array(
                                'id' => $insert["lastInsertId"],
                                'parent' => $destPresupuesto
                        );
                        $this->setVerificarPresupuesto($object->id, $presupuestos, $insert["lastInsertId"], $data);

                        $this->getInsertEspecificacionesTecnicas();

                        $this->getInsertPartidasPresupuestos();
                        $this->getInsertApus();

                        $this->getInsertMetrados();

                        $resp['success'] = true;
                        $resp['message'] = 'Se copió correctamente';
                        $resp['data'] = $data;
                    } else {
                            $resp['success'] = false;
                            $resp['message'] = 'Error presupuesto no encontrado';
                    }
                } else {
                        $resp['success'] = false;
                        $resp['message'] = 'Error en la información de los presupuestos';
                }
            } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No hay suficiente información para copiar el elemento';
            }
        } catch (\Throwable $th) {
                $resp['success'] = false;
                $resp['message'] = $th->getMessage();
        }
            return $resp;
    }

    private function setVerificarPresupuesto($searchedValue, $dataPresupuesto, $id, &$data)
    {

            $object =   array_filter(
                $dataPresupuesto,
                function ($e) use ($searchedValue) {
                        return $e->presupuestos_proyecto_generales_id == $searchedValue;
                }
            );

        if (count($object)) {
            foreach ($object as $value) {
                $var = [
                        "nro_orden" => $value->nro_orden,
                        "descripcion" => $value->descripcion,
                        "proyecto_generales_id" => $this->_id,
                        "presupuestos_proyecto_generales_id" => $id
                ];

                if ($value->metrado) {
                    $var["metrado"] = $value->metrado;
                }
                if ($value->cu) {
                    $var["cu"] = $value->cu;
                }
                if ($value->mo) {
                    $var["mo"] = $value->mo;
                }
                if ($value->mt) {
                    $var["mt"] = $value->mt;
                }
                if ($value->eq) {
                    $var["eq"] = $value->eq;
                }
                if ($value->sc) {
                    $var["sc"] = $value->sc;
                }
                if ($value->sp) {
                    $var["sp"] = $value->sp;
                }

                if ($value->unidad_medidas_id) {
                    $var["unidad_medidas_id"] = $value->unidad_medidas_id;
                }
                if ($value->presupuestos_title_id) {
                    $var["presupuestos_title_id"] = $value->presupuestos_title_id;
                }
                if ($value->partidas_id) {
                    $var["partidas_id"] = $value->partidas_id;
                }

                if ($value->subpresupuestos_id) {
                    $var["subpresupuestos_id"] = $value->subpresupuestos_id;
                }
                if ($value->type_item) {
                    $var["type_item"] = $value->type_item;
                }

                    $insert = self::insert("presupuestos", $var);

                if ($value->type_item == 3) {
                    $this->partidas[$value->id] = $insert["lastInsertId"];
                }
                    $data[$value->id] = array(
                            'id' => $insert["lastInsertId"],
                            'parent' => $id
                    );
                    $this->setVerificarPresupuesto($value->id, $dataPresupuesto, $insert["lastInsertId"], $data);
            }
        }
    }

    private function getInsertEspecificacionesTecnicas()
    {
            $partidas = array_keys($this->partidas);
            $queryIn = implode(',', $partidas);
        if (!$queryIn) {
            return;
        }
            $sql = "SELECT
                                id,
                                presupuestos_id,
                                titulo,
                                descripcion,
                                proyecto_generales_id,
                                position,
                                deleted_at
                        FROM especificaciones_tecnicas  
                        WHERE proyecto_generales_id = :proyecto_generales_id AND presupuestos_id IN ({$queryIn}) 
                                AND deleted_at is NULL";
            $especificaciones_tecnicas =  self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_id]);

        if ($especificaciones_tecnicas) {
            foreach ($especificaciones_tecnicas as $value) {
                $var = [
                        "titulo" => $value->titulo,
                        "descripcion" => $value->descripcion,
                        "position" => $value->position,
                        "proyecto_generales_id" => $this->_id
                ];
                if (isset($this->partidas[$value->presupuestos_id])) {
                        $var['presupuestos_id'] = $this->partidas[$value->presupuestos_id];
                }
                self::insert("especificaciones_tecnicas", $var);
            }
        }
    }

    private function getInsertApus()
    {
            $part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
        if (!$queryIn) {
            return;
        }
            $sql = "SELECT                      
                        id,
                        cuadrilla,
                        cantidad,
                        precio,
                        insumo_id,
                        unidad_medidas_id,
                        proyectos_generales_id,
                        presupuestos_id,
                        subpresupuestos_id,
                        partida_id,
                        subpartida_id,
                        iu,
                        monomio
                        FROM apus_partida_presupuestos  
                        WHERE presupuestos_id IN ({$queryIn}) AND deleted_at IS NULL";
            $apus_partida_presupuestos =  self::fetchAllObj($sql);

        if ($apus_partida_presupuestos) {
            foreach ($apus_partida_presupuestos as $value) {
                $var = [
                        "unidad_medidas_id" => $value->unidad_medidas_id,
                        "proyectos_generales_id" => $value->proyectos_generales_id,
                        "insumo_id" => $value->insumo_id,
                        "presupuestos_id" =>  $this->partidas[$value->presupuestos_id],
                        "subpresupuestos_id" => $value->subpresupuestos_id
                ];
                if ($value->cuadrilla) {
                    $var["cuadrilla"] = $value->cuadrilla;
                }
                if ($value->cantidad) {
                    $var["cantidad"] = number_format($value->cantidad, 4, '.', '');
                }
                if ($value->precio) {
                    $var["precio"] = $value->precio;
                }
                if ($value->partida_id) {
                    $var["partida_id"] = $value->partida_id;
                }
                if ($value->subpartida_id) {
                    $var["subpartida_id"] = $this->subpartidas[$value->subpartida_id];
                }
                if ($value->iu) {
                    $var["iu"] = $value->iu;
                }
                if ($value->monomio) {
                    $var["monomio"] = $value->monomio;
                }
                $insert = self::insert("apus_partida_presupuestos", $var);
                if ($value->partida_id) {
                    $this->subpartidas[$value->id] = $insert["lastInsertId"];
                }
            }
        }
    }

    private function getInsertPartidasPresupuestos()
    {
            $part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
        if (!$queryIn) {
            return;
        }
            $sql = "SELECT id,rendimiento,
                        rendimiento_unid,
                        presupuestos_id,
                        proyectos_generales_id,
                        subpartida_id,
	                partida_id
                        FROM presupuestos_partida  
                        WHERE presupuestos_id IN ({$queryIn})";
            $presupuestos_partida =  self::fetchAllObj($sql);
        if ($presupuestos_partida) {
            foreach ($presupuestos_partida as $value) {
                $var = ["presupuestos_id" => $this->partidas[$value->presupuestos_id]];
                if ($value->rendimiento) {
                    $var["rendimiento"] = $value->rendimiento;
                }
                if ($value->rendimiento_unid) {
                    $var["rendimiento_unid"] = $value->rendimiento_unid;
                }
                if ($value->proyectos_generales_id) {
                    $var["proyectos_generales_id"] = $value->proyectos_generales_id;
                }
                if ($value->subpartida_id) {
                    $var["subpartida_id"] = $this->subpartidas[$value->subpartida_id];
                }
                if ($value->partida_id) {
                    $var["partida_id"] = $value->partida_id;
                }
                $insert = self::insert("presupuestos_partida", $var);
            }
        }
    }

    private function getInsertMetrados()
    {
            $part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
        if (!$queryIn) {
            return;
        }
            $sql = "SELECT                      
                        id,
                        descripcion,
                        metrado_largo,
                        metrado_alto,
                        metrado_ancho,
                        metrado_area,
                        metrado_volumen,
                        metrado_cantidad,
                        metrado_nro_elemto,
                        metrado_factor,
                        presupuestos_id,
                        proyecto_generales_id,
                        img,
                        is_information
                        FROM metrado_partida_presupuestos  
                        WHERE presupuestos_id IN ({$queryIn})";
            $metrado_partida_presupuestos =  self::fetchAllObj($sql);

        if ($metrado_partida_presupuestos) {
            foreach ($metrado_partida_presupuestos as $value) {
                $var = [
                        "descripcion" => $value->descripcion,
                        "presupuestos_id" =>  $this->partidas[$value->presupuestos_id],
                        "proyecto_generales_id" => $value->proyecto_generales_id
                ];
                if ($value->metrado_largo) {
                    $var["metrado_largo"] = $value->metrado_largo;
                }
                if ($value->metrado_alto) {
                    $var["metrado_alto"] = $value->metrado_alto;
                }
                if ($value->metrado_ancho) {
                    $var["metrado_ancho"] = $value->metrado_ancho;
                }
                if ($value->metrado_area) {
                    $var["metrado_area"] = $value->metrado_area;
                }
                if ($value->metrado_volumen) {
                    $var["metrado_volumen"] = $value->metrado_volumen;
                }
                if ($value->metrado_cantidad) {
                    $var["metrado_cantidad"] = $value->metrado_cantidad;
                }
                if ($value->metrado_nro_elemto) {
                    $var["metrado_nro_elemto"] = $value->metrado_nro_elemto;
                }
                if ($value->img) {
                    $var["img"] = $value->img;
                }
                if ($value->is_information) {
                    $var["is_information"] = $value->is_information;
                }
                self::insert("metrado_partida_presupuestos", $var);
            }
        }
    }
}
