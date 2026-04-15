<?php

require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql


class Categoria extends Mysql
{
    public function getList()
    {
        $sql = 'SELECT id, descripcion, icono FROM categorias WHERE deleted_at IS NULL';
        $resp['success'] = true;
        $resp['data'] = [];
        $result = self::fetchAllObj($sql);
        if ($result) {
            $resp['data'] = $result;
        }
        return $resp;
    }

    public function getSave($request)
    {
        try {
            if ($request->id) {
                $sql = 'SELECT COUNT(id) FROM categorias WHERE id = :id';
                $cat = self::fetchObj($sql, ['id' => $request->id]);
                if ($cat) {
                    $update = self::update("categorias", [
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono,
                        'updated_at' => date("Y-m-d H:i:s")
                    ], ['id' => $request->id]);
                    $resp['success'] = true;
                    $resp['message'] = 'Se ha actualizado';
                    $resp['data'] = [
                        'id' => $request->id,
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono
                    ];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Categoría no existe';
                }
            } else {
                $insert = self::insert("categorias", [
                    'descripcion' => $request->descripcion,
                    'icono' => $request->icono,
                    'created_at' => date("Y-m-d H:i:s")
                ]);
                if ($insert && $insert["lastInsertId"]) {
                    $resp['success'] = true;
                    $resp['message'] = 'Categoría registrada';
                    $resp['data'] = [
                        'id' => $insert["lastInsertId"],
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono,
                    ];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Error al registrar categoría';
                }
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function setCategoryToBudget($request)
    {
        try {
            $sql = 'SELECT COUNT(id) AS id FROM proyecto_generales WHERE id = :id';
            $proyectoGeneral = self::fetchObj($sql, ['id' => $request->id]);
            if ($proyectoGeneral && $proyectoGeneral->id) {
                $update = self::update("proyecto_generales", [
                    'categoriaId' => $request->categoryId
                ], ['id' => $request->id]);
                $resp['success'] = true;
                $resp['message'] = 'Presupuesto actualizado';
                $resp['data'] = [
                    'categoriaId' => $request->categoryId
                ];
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Presupuesto no existe';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getDelete($request)
    {
        try {
            $sql = 'SELECT COUNT(id) AS id FROM categorias
                    WHERE id = :id';
            $cat = self::fetchObj($sql, ['id' => $request->id]);
            if ($cat && $cat->id) {
                self::update('categorias', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $request->id]);
                $resp['success'] = true;
                $resp['message'] = 'Categoría eliminada';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Categoría no existe';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar la categoría';
            return $resp;
        }
    }

    public function getListProyectoGeneral($categoriaId)
    {
        $response = [];
        $sql = "SELECT
                    id,
                    proyecto,
                    cliente,
                    direccion,
                    distrito,
                    provincia,
                    departamento,
                    pais,
                    area_geografica,
                    fecha_base,
                    jornada_laboral,
                    moneda,
                    fecha_inicio,
                    fecha_fin,
                    costo_directo
            FROM proyecto_generales
            WHERE categoriaId = :categoriaId AND deleted_at is NULL
            ORDER BY id ASC";
        $result = self::fetchAllObj($sql, ['categoriaId' => $categoriaId]);
        if ($result) {
            $response = $result;
        }
        return $response;
    }
}
