<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class Plantilla extends Mysql
{
        private $_id;
        private $_newId;
        private $partidas = [];
        private $insumos = [];
        private $partidas_proyecto = [];
        private $subpresupuestos = [];
        private $titulos = [];
        private $subpartidas = [];

    public function getGenerarPlantilla($request)
    {
            $resp = [];
            $this->_id = $request->budget;
            $departamento = $request->departamento;
            $provincia = $request->provincia;
            $distrito = $request->distrito;
            $area_geografica = $request->area_geografica;
            $users_id = $request->users_id;
        try {
                $sql = "SELECT                      
                        id,
                        users_id,
                        proyecto,
                        cliente,
                        direccion,
                        pais,
                        fecha_base,
                        jornada_laboral,
                        moneda,
                        proyecto_generalescol,
                        fecha_inicio,
                        fecha_fin,
                        costo_directo,
                        categoriaId,
                        deleted_at
                        FROM proyecto_generales
                        WHERE id = :id AND deleted_at is NULL";
                $proyecto_generales =  self::fetchObj($sql, ['id' => $this->_id]);

            if ($proyecto_generales) {
                $var = [
                        "users_id" => $users_id,
                        "proyecto" => $proyecto_generales->proyecto,
                        "cliente" => $proyecto_generales->cliente,
                        "direccion" => $proyecto_generales->direccion,
                        "distrito" => $distrito,
                        "provincia" => $provincia,
                        "departamento" => $departamento,
                        "pais" => $proyecto_generales->pais,
                        "area_geografica" => $area_geografica,
                        "fecha_base" => $proyecto_generales->fecha_base,
                        "jornada_laboral" => $proyecto_generales->jornada_laboral,
                        "moneda" => $proyecto_generales->moneda,
                        "categoriaId" => $proyecto_generales->categoriaId,
                        "proyecto_generalescol" => $proyecto_generales->proyecto_generalescol
                ];
                if ($proyecto_generales->fecha_inicio) {
                    $var["fecha_inicio"] = $proyecto_generales->fecha_inicio;
                }
                if ($proyecto_generales->fecha_fin) {
                    $var["fecha_fin"] = $proyecto_generales->fecha_fin;
                }
                if ($proyecto_generales->costo_directo) {
                    $var["costo_directo"] = $proyecto_generales->costo_directo;
                }
                $insert = self::insert("proyecto_generales", $var);
                $this->_newId = $insert["lastInsertId"];
                $this->getInsertSubcategoria();
                $this->getInsertTitulos();
                $this->startBuilding();
                $resp = [
                        "success" => true,
                        "message" => "Presupuesto generado correctamente",
                        "data" => ['id' =>  $insert["lastInsertId"]]
                ];
            } else {
                    $resp = ["success" => false, "message" => "El presupuesto no existe"];
            }
        } catch (\Throwable $th) {
                $resp['success'] = false;
                $resp['message'] = $th->getMessage();
        }
            return $resp;
    }

    private function startBuilding()
    {
            $this->getInsertPartidasProyecto();

            $this->getInsertPresupuesto();

            $this->getInsertEspecificacionesTecnicas();

            $this->getInsertGastosGenerales();

            $this->getInsertPartidasPresupuestos();

            $this->getInsertInsumosProyecto();

            $this->getInsertApus();

            $this->getInsertMetrados();

            $this->getInsertFormulasPolinomica();

            $this->getInsertPiePesupuesto();

            $this->getInsertPiePresupuestoGrupo();
    }

    private function getInsertSubcategoria()
    {
            $sql = "SELECT                      
                        id,
                        descripcion,
                        orden,
                        subcategorias_master_id,
                        proyecto_generales_id
                FROM subcategorias_proyecto_general  
                WHERE proyecto_generales_id = :proyecto_generales_id ";
            $subcategorias_proyecto_general =  self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_id]);

        if ($subcategorias_proyecto_general) {
            foreach ($subcategorias_proyecto_general as $value) {
                $var = [
                        "descripcion" => $value->descripcion,
                        "orden" => $value->orden,
                            "proyecto_generales_id" => $this->_newId,
                    ];
                if ($value->subcategorias_master_id) {
                    $var['subcategorias_master_id'] = $value->subcategorias_master_id;
                }
                    $insert = self::insert("subcategorias_proyecto_general", $var);
                    $this->subpresupuestos[$value->id] = $insert["lastInsertId"];
            }
        }
    }

    private function getInsertTitulos()
    {
            $sql = "SELECT                      
                        id,
                        titulo,
                        master_title_id,
                        proyectos_generales_id
                FROM titulos_proyecto  
                WHERE proyectos_generales_id = :proyecto_generales_id ";
            $titulos_proyecto =  self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_id]);

        if ($titulos_proyecto) {
            foreach ($titulos_proyecto as $value) {
                $var = [
                        "titulo" => $value->titulo,
                        "proyectos_generales_id" => $this->_newId,
                ];
                if ($value->master_title_id) {
                    $var['master_title_id'] = $value->master_title_id;
                }
                $insert = self::insert("titulos_proyecto", $var);
                $this->titulos[$value->id] = $insert["lastInsertId"];
            }
        }
    }

    private function getInsertPresupuesto()
    {
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
                    WHERE proyecto_generales_id = :id
                            AND deleted_at is NULL                           
                    ORDER BY nro_orden ASC";
            $presupuestos = self::fetchAllObj($sql_general, ['id' => $this->_id]);

        if ($presupuestos) {
                $object = array_filter($presupuestos, function ($e) {
                        return $e->presupuestos_proyecto_generales_id == null || $e->presupuestos_proyecto_generales_id == 0;
                });

            foreach ($object as $value) {
                $var = [
                "nro_orden" => $value->nro_orden,
                "descripcion" => $value->descripcion,
                "proyecto_generales_id" => $this->_newId
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
                    $var["presupuestos_title_id"] = $this->titulos[$value->presupuestos_title_id];
                }
                if ($value->partidas_id) {
                    $var["partidas_id"] = $this->partidas_proyecto[$value->partidas_id];
                }

                if ($value->subpresupuestos_id) {
                    $var["subpresupuestos_id"] = $this->subpresupuestos[$value->subpresupuestos_id];
                }
                if ($value->type_item) {
                    $var["type_item"] = $value->type_item;
                }

                $insert = self::insert("presupuestos", $var);
                $this->partidas[$value->id] = $insert["lastInsertId"];
                $this->setVerificarPresupuesto($value->id, $presupuestos, $insert["lastInsertId"]);
            }
        }
    }

    private function setVerificarPresupuesto($searchedValue, $dataPresupuesto, $id)
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
                            "proyecto_generales_id" => $this->_newId,
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
                    $var["presupuestos_title_id"] = $this->titulos[$value->presupuestos_title_id];
                }
                if ($value->partidas_id) {
                    $var["partidas_id"] = $this->partidas_proyecto[$value->partidas_id];
                }

                if ($value->subpresupuestos_id) {
                    $var["subpresupuestos_id"] = $this->subpresupuestos[$value->subpresupuestos_id];
                }
                if ($value->type_item) {
                    $var["type_item"] = $value->type_item;
                }

                    $insert = self::insert("presupuestos", $var);
                if ($value->type_item == 3) {
                    $this->partidas[$value->id] = $insert["lastInsertId"];
                }
                    $this->setVerificarPresupuesto($value->id, $dataPresupuesto, $insert["lastInsertId"]);
            }
        }
            return $object;
    }

    private function getInsertEspecificacionesTecnicas()
    {
            $sql = "SELECT                      
                        id,
                        presupuestos_id,
                        titulo,
                        descripcion,
                        proyecto_generales_id,
                        position,
                        deleted_at
                FROM especificaciones_tecnicas  
                WHERE proyecto_generales_id = :proyecto_generales_id 
                      AND deleted_at is NULL";
            $especificaciones_tecnicas =  self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_id]);

        if ($especificaciones_tecnicas) {
            foreach ($especificaciones_tecnicas as $value) {
                $var = [
                        "titulo" => $value->titulo,
                        "descripcion" => $value->descripcion,
                        "position" => $value->position,
                        "proyecto_generales_id" => $this->_newId
                ];
                if ($value->presupuestos_id) {
                    if (isset($this->partidas[$value->presupuestos_id])) {
                                $var['presupuestos_id'] = $this->partidas[$value->presupuestos_id];
                    }
                }
                self::insert("especificaciones_tecnicas", $var);
            }
        }
    }

    private function getInsertGastosGenerales()
    {
            $sql = "SELECT                      
                        id,
                        descripcion,
                        grupos_id,
                        duracion,
                        cantidad,
                        porcentaje_partida,
                        precio,
                        parcial,
                        gastos_generales_id,
                        unidad_medidas_id,
                        proyecto_generales_id,
                        deleted_at
                FROM gastos_generales  
                WHERE proyecto_generales_id = :proyecto_generales_id 
                      AND deleted_at is NULL";
            $gastos_generales =  self::fetchAllObj($sql, ['proyecto_generales_id' => $this->_id]);

        if ($gastos_generales) {
                $ggenerales_id = [];

            foreach ($gastos_generales as $value) {
                $var = [
                        "descripcion" => $value->descripcion,
                        "grupos_id" => $value->grupos_id,
                        "unidad_medidas_id" => $value->unidad_medidas_id,
                        "proyecto_generales_id" => $this->_newId,
                ];
                if ($value->duracion) {
                    $var["duracion"] = $value->duracion;
                }
                if ($value->cantidad) {
                    $var["cantidad"] = $value->cantidad;
                }
                if ($value->porcentaje_partida) {
                    $var["porcentaje_partida"] = $value->porcentaje_partida;
                }
                if ($value->precio) {
                    $var["precio"] = $value->precio;
                }
                if ($value->parcial) {
                    $var["parcial"] = $value->parcial;
                }
                if ($value->gastos_generales_id) {
                    $var["gastos_generales_id"] = $ggenerales_id[$value->gastos_generales_id];
                }
                $insert = self::insert("gastos_generales", $var);
                if (!$value->gastos_generales_id) {
                    $ggenerales_id[$value->id] = $insert["lastInsertId"];
                }
            }
        }
    }

    private function getInsertPartidasProyecto()
    {
            $sql = "SELECT
                	id,
                        partida,
                        rendimiento,
                        rendimiento_unid,
                        unidad_medidas_id,
                        proyectos_generales_id,
                        master_partida_id
                        FROM partidas_proyecto  
                        WHERE proyectos_generales_id = :id";
            $partidas_proyecto =  self::fetchAllObj($sql, ['id' => $this->_id]);
        if ($partidas_proyecto) {
            foreach ($partidas_proyecto as $value) {
                $var = ["partida" => $value->partida];
                if ($value->rendimiento) {
                    $var["rendimiento"] = $value->rendimiento;
                }
                if ($value->rendimiento_unid) {
                    $var["rendimiento_unid"] = $value->rendimiento_unid;
                }
                if ($value->unidad_medidas_id) {
                    $var["unidad_medidas_id"] = $value->unidad_medidas_id;
                }
                if ($value->master_partida_id) {
                    $var["master_partida_id"] = $value->master_partida_id;
                }
                if ($value->proyectos_generales_id) {
                    $var["proyectos_generales_id"] = $this->_newId;
                }
                $insert = self::insert("partidas_proyecto", $var);
                $this->partidas_proyecto[$value->id] = $insert["lastInsertId"];
            }
        }
    }

    private function getInsertInsumosProyecto()
    {
            $sql = "SELECT
                        id,
                        codigo,
                        iu,
                        indice_unificado,
                        tipo,
                        insumos,
                        precio,
                        unidad_medidas_id,
                        master_insumo_id,
                        proyectos_generales_id
                        FROM insumos_proyecto
                        WHERE proyectos_generales_id = :id AND deleted_at IS NULL";
            $insumos_proyecto =  self::fetchAllObj($sql, ['id' => $this->_id]);

        if ($insumos_proyecto) {
            foreach ($insumos_proyecto as $value) {
                $var = [
                        "codigo" => $value->codigo,
                        "indice_unificado" => $value->indice_unificado,
                        "tipo" => $value->tipo,
                        "insumos" => $value->insumos,
                        "unidad_medidas_id" => $value->unidad_medidas_id,
                        "proyectos_generales_id" => $this->_newId
                ];
                if ($value->iu) {
                    $var["iu"] = $value->iu;
                }
                if ($value->precio) {
                    $var["precio"] = $value->precio;
                }
                if ($value->master_insumo_id) {
                    $var["master_insumo_id"] = $value->master_insumo_id;
                }
                $insert = self::insert("insumos_proyecto", $var);
                $this->insumos[$value->id] = $insert["lastInsertId"];
            }
        }
    }

    private function getInsertApus()
    {
            /*$part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
            if(!$queryIn) return;*/
            $sql = "SELECT                      
                        id,
                        cuadrilla,
                        cantidad,
                        insumo_id,
                        unidad_medidas_id,
                        proyectos_generales_id,
                        presupuestos_id,
                        subpresupuestos_id,
                        precio,
                        partida_id,
                        subpartida_id,
                        iu,
                        monomio
                        FROM apus_partida_presupuestos  
                        WHERE proyectos_generales_id = {$this->_id} AND deleted_at IS NULL";
            $apus_partida_presupuestos =  self::fetchAllObj($sql);

        if ($apus_partida_presupuestos) {
            foreach ($apus_partida_presupuestos as $value) {
                if (!isset($this->insumos[$value->insumo_id])) {
                    continue; // Saltar si el insumo no existe
                }

                if (!isset($this->partidas[$value->presupuestos_id])) {
                        continue; // Saltar si la partida no existe
                }

                if (!isset($this->subpresupuestos[$value->subpresupuestos_id])) {
                        continue; // Saltar si el subpresupuesto no existe
                }
                                $var = [
                        "unidad_medidas_id" => $value->unidad_medidas_id,
                        "proyectos_generales_id" => $this->_newId,
                        "insumo_id" => $this->insumos[$value->insumo_id],
                        "presupuestos_id" =>  $this->partidas[$value->presupuestos_id],
                        "subpresupuestos_id" => $this->subpresupuestos[$value->subpresupuestos_id]
                                ];
                                if ($value->cuadrilla) {
                                    $var["cuadrilla"] = $value->cuadrilla;
                                }
                                if ($value->cantidad) {
                                    $var["cantidad"] = number_format($value->cantidad, 4, '.', '');
                                }
                                if ($value->precio) {
                                    $var["precio"] = number_format($value->precio, 2, '.', '');
                                }
                                if ($value->partida_id) {
                                    $var["partida_id"] = $this->partidas_proyecto[$value->partida_id];
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
            /*$part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
            if(!$queryIn) return;*/
            $sql = "SELECT pp.id,pp.rendimiento,
                        pp.rendimiento_unid,
                        pp.presupuestos_id,
                        pp.proyectos_generales_id,
                        pp.subpartida_id,
	                pp.partida_id
                        FROM presupuestos_partida pp
                        INNER JOIN presupuestos pr ON pp.presupuestos_id = pr.id AND pr.deleted_at IS NULL
                        WHERE pp.proyectos_generales_id = {$this->_id}";
            $presupuestos_partida =  self::fetchAllObj($sql);
        if ($presupuestos_partida) {
            foreach ($presupuestos_partida as $value) {
                if (!isset($this->partidas[$value->presupuestos_id])) {
                        continue;
                }
                $var = ["presupuestos_id" => $this->partidas[$value->presupuestos_id]];
                if ($value->rendimiento) {
                    $var["rendimiento"] = $value->rendimiento;
                }
                if ($value->rendimiento_unid) {
                    $var["rendimiento_unid"] = $value->rendimiento_unid;
                }
                if ($value->subpartida_id) {
                    $var["subpartida_id"] = $this->subpartidas[$value->subpartida_id];
                }
                if ($value->partida_id) {
                    $var["partida_id"] = $this->partidas_proyecto[$value->partida_id];
                }
                if ($value->proyectos_generales_id) {
                    $var["proyectos_generales_id"] = $this->_newId;
                }
                $insert = self::insert("presupuestos_partida", $var);
            }
        }
    }

    private function getInsertMetrados()
    {
            /*$part = array_keys($this->partidas);
            $queryIn = implode(',', $part);
            if(!$queryIn) return;*/
            $sql = "SELECT                      
                        mp.id,
                        mp.descripcion,
                        mp.position,
                        mp.metrado_largo,
                        mp.metrado_alto,
                        mp.metrado_ancho,
                        mp.metrado_area,
                        mp.metrado_volumen,
                        mp.metrado_cantidad,
                        mp.metrado_nro_elemto,
                        mp.metrado_factor,
                        mp.presupuestos_id,
                        mp.proyecto_generales_id,
                        mp.img,
                        mp.is_information
                        FROM metrado_partida_presupuestos mp
                        INNER JOIN presupuestos pr ON mp.presupuestos_id = pr.id AND pr.deleted_at IS NULL
                        WHERE mp.proyecto_generales_id = {$this->_id}";
            $metrado_partida_presupuestos =  self::fetchAllObj($sql);

        if ($metrado_partida_presupuestos) {
            foreach ($metrado_partida_presupuestos as $value) {
                if (!isset($this->partidas[$value->presupuestos_id])) {
                        continue;
                }
                $var = [
                        "descripcion" => $value->descripcion,
                        "presupuestos_id" =>  $this->partidas[$value->presupuestos_id],
                        "proyecto_generales_id" => $this->_newId
                ];
                if ($value->position) {
                    $var["position"] = $value->position;
                }
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
                if ($value->metrado_factor) {
                    $var["metrado_factor"] = $value->metrado_factor;
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

    private function getInsertFormulasPolinomica()
    {
            $sql = "SELECT
                        id,
                        monomio,
                        simbolo,
                        proyecto_general_id,
                        subpresupuesto_id
                        FROM formula_polinomica  
                        WHERE proyecto_general_id = :id";
            $formula_polinomica =  self::fetchAllObj($sql, ['id' => $this->_id]);

        if ($formula_polinomica) {
            foreach ($formula_polinomica as $value) {
                $var = [
                        "proyecto_general_id" => $this->_newId
                ];
                if ($value->monomio) {
                    $var["monomio"] = $value->monomio;
                }
                if ($value->simbolo) {
                    $var["simbolo"] = $value->simbolo;
                }
                if ($value->subpresupuesto_id) {
                    $var["subpresupuesto_id"] = $value->subpresupuesto_id;
                }
                self::insert("formula_polinomica", $var);
            }
        }
    }

    private function getInsertPiePesupuesto()
    {
            $sql = "SELECT
                        id,
                        percentage,
                        active,
                        type_percentage,
                        proyectos_generales_id,
                        pie_presupuesto_id
                        FROM proyecto_pie_presupuesto  
                        WHERE proyectos_generales_id = :id";
            $proyecto_pie_presupuesto =  self::fetchAllObj($sql, ['id' => $this->_id]);

        if ($proyecto_pie_presupuesto) {
            foreach ($proyecto_pie_presupuesto as $value) {
                $var = [
                        "proyectos_generales_id" => $this->_newId
                ];
                if ($value->percentage) {
                    $var["percentage"] = $value->percentage;
                }
                if ($value->active) {
                    $var["active"] = $value->active;
                }
                if ($value->type_percentage) {
                    $var["type_percentage"] = $value->type_percentage;
                }
                if ($value->pie_presupuesto_id) {
                    $var["pie_presupuesto_id"] = $value->pie_presupuesto_id;
                }
                self::insert("proyecto_pie_presupuesto", $var);
            }
        }
    }

    private function getInsertPiePresupuestoGrupo()
    {
            $sql = "SELECT
                        id,
                        iu,
                        monomio,
                        subpresupuestos_id,
                        proyectos_generales_id
                        FROM pie_presupuesto_grupo  
                        WHERE proyectos_generales_id = :id";
            $pie_presupuesto_grupo =  self::fetchAllObj($sql, ['id' => $this->_id]);

        if ($pie_presupuesto_grupo) {
            foreach ($pie_presupuesto_grupo as $value) {
                $var = [
                        "proyectos_generales_id" => $this->_newId
                ];
                if ($value->iu) {
                    $var["iu"] = $value->iu;
                }
                if ($value->monomio) {
                    $var["monomio"] = $value->monomio;
                }
                if ($value->subpresupuestos_id) {
                    $var["subpresupuestos_id"] = $value->subpresupuestos_id;
                }
                self::insert("pie_presupuesto_grupo", $var);
            }
        }
    }
}
