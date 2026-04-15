<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../persistence/Mariadb.php');
require_once(__DIR__ . '/../utilitarian/FG.php');

class Insumos extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_insumos;
    public function getinsumos()
    {
        return $this->_insumos;
    }
    private $_precio;
    public function getprecio()
    {
        return $this->_precio;
    }
    private $_tipo;
    public function gettipo()
    {
        return $this->_tipo;
    }
    private $_indice_unificado;
    public function getindice_unificado()
    {
        return $this->_indice_unificado;
    }
    private $_unidad_medidas_id;
    public function getunidad_medidas_id()
    {
        return $this->_unidad_medidas_id;
    }

    private $_archivo_id;
    public function getarchivo_id()
    {
        return $this->_archivo_id;
    }

    public function __construct($array = [])
    {
        $this->_id = FG::validateMatrizKey('id', $array, 0);
        $this->_insumos = FG::validateMatrizKey('insumos', $array); //(array_key_exists('insumos',$array))?$array['insumos']:"";
        $this->_precio = FG::validateMatrizKey('precio', $array); //(array_key_exists('precio',$array))?$array['precio']:"";
        $this->_tipo = FG::validateMatrizKey('tipo', $array); //  (array_key_exists('tipo',$array))?$array['tipo']:"";
        $this->_indice_unificado = FG::validateMatrizKey('indice_unificado', $array); // (array_key_exists('indice_unificado',$array))?$array['indice_unificado']:"";
        $this->_unidad_medidas_id = FG::validateMatrizKey('unidad_medidas_id', $array); // (array_key_exists('unidad_medidas_id',$array))?$array['unidad_medidas_id']:"" ;
        $this->_archivo_id = FG::validateMatrizKey('archivo_id', $array); // (array_key_exists('archivo_id',$array))?$array['archivo_id']:"" ;
    }

    public function getListAll()
    {
        $sql = 'SELECT insumos.id, insumos, tipo, precio , codigo, indice_unificado, unidad_medidas.alias AS unidad_medida, unidad_medidas.id AS unidad_medidas_id
              FROM insumos
              INNER JOIN unidad_medidas ON unidad_medidas.id = insumos.unidad_medidas_id ';
        $minsumos = self::fetchAllObj($sql);



        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = array(
        'master' => $minsumos
        );
        return $resp;
    }

    public function getListInsumo($proyectos_generales_id)
    {
        $sql = 'SELECT insumos.id, insumos,tipo, unidad_medidas.alias AS unidad_medida, unidad_medidas.id AS unidad_medidas_id
              FROM insumos
              INNER JOIN unidad_medidas ON unidad_medidas.id = insumos.unidad_medidas_id ';
        $minsumos = self::fetchAllObj($sql);

        $sql = 'SELECT ipr.id, ipr.insumos,ipr.tipo,ipr.master_insumo_id, um.alias AS unidad_medida, um.id AS unidad_medidas_id
              FROM insumos_proyecto ipr
              INNER JOIN unidad_medidas um ON um.id = ipr.unidad_medidas_id WHERE ipr.proyectos_generales_id = :id AND ipr.master_insumo_id IS NULL';
        $pinsumos = self::fetchAllObj($sql, ['id' => $proyectos_generales_id]);

        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = array(
        'master' => $minsumos,
        'proyecto' => $pinsumos
        );
        return $resp;
    }

    public function getListInsumoByArchivo($archivo_id)
    {
        $sql = 'SELECT i.id, i.codigo, i.insumos, i.tipo, i.precio, um.alias AS unidad_medida, um.id AS unidad_medidas_id FROM insumos i
          INNER JOIN unidad_medidas um ON um.id = i.unidad_medidas_id WHERE i.archivo_id = :archivo_id';

        $insumos = self::fetchAllObj($sql, ['archivo_id' => $archivo_id]);

        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = array(
        'master' => $insumos
        );
        return $resp;
    }

    public function searchByName($termino, $tipo = null, $proyecto_id = null)
    {
        // Ajustamos la consulta para traer los alias y campos específicos de tu JS
        error_log("DEBUG searchByName");
        error_log("termino: " . $termino);
        error_log("tipo: " . var_export($tipo, true));
        error_log("proyecto_id: " . var_export($proyecto_id, true));

        $sql = "SELECT 
                  i.id, 
                  i.insumos, 
                  i.tipo, 
                  i.precio, 
                  i.iu,
                  i.indice_unificado AS iu_nombre, 
                  i.master_insumo_id,
                  um.alias, 
                  um.id AS unidad_medidas_id
              FROM insumos_proyecto i
              INNER JOIN unidad_medidas um ON um.id = i.unidad_medidas_id
              WHERE i.insumos LIKE :termino
              AND i.proyectos_generales_id = :proyecto_id 
              AND i.estado = 1";

        $params = ['termino' => '%' . $termino . '%', 'proyecto_id' => $proyecto_id];

        // Si recibes tipo (Mano de obra, Materiales, etc), filtramos
        if (!empty($tipo)) {
            $sql .= " AND i.tipo = :tipo";
            $params['tipo'] = $tipo;
        }


        $data = self::fetchAllObj($sql, $params);

        return [
          'success' => true,
          'data' => $data
        ];
    }




    public function getInsumoDatabases()
    {
        $sql = "
          SELECT 
              COALESCE(a.id, 0) as archivo_id,
              COALESCE(a.nombre, 'Otros') as nombre_archivo,
              COUNT(i.id) as total_insumos
          FROM insumos i
          LEFT JOIN archivos a ON i.archivo_id = a.id AND a.deleted_at IS NULL
          GROUP BY COALESCE(a.id, 0), COALESCE(a.nombre, 'Otros')
          HAVING total_insumos > 0
          ORDER BY 
              CASE WHEN a.id IS NULL THEN 1 ELSE 0 END,
              a.nombre ASC
      ";
        $resp['success'] = true;
        $resp['data'] = self::fetchAllObj($sql);
        return $resp;
    }


    public function getIndicesUnificados()
    {
        $sql = 'SELECT * FROM indice_unificado';
        $resp['success'] = true;
        $resp['data'] = self::fetchAllObj($sql);
        return $resp;
    }

    public function renameInsumo($request)
    {
        $response = [];
        $params = [
        'insumos' => $request->insumos,
        ];
        $sql = 'SELECT insumo_id FROM apus_partida_presupuestos WHERE id = :id AND deleted_at IS NULL';
        $insu = self::fetchObj($sql, ['id' => $request->id]);
        if ($insu) {
            self::update('insumos_proyecto', $params, ['id' => $insu->insumo_id]);
            $response['success'] = true;
            $response['message'] = 'Datos guardados';
            $response['data'] = $params;
        } else {
            $response['success'] = false;
            $response['message'] = 'El insumo no existe';
            $response['data'] = null;
        }

        return $response;
    }

  /**
   * Actualizar precio de un insumo individual
   */
    public function actualizarPrecio($id, $precio)
    {
        $response = array(
        "success" => false,
        "message" => null
        );

        try {
          // Verificar que el insumo existe
            $sql = 'SELECT id FROM insumos WHERE id = :id';
            $insumo = self::fetchObj($sql, ['id' => $id]);

            if (!$insumo) {
                $response['message'] = "El insumo con ID $id no existe";
                return $response;
            }

          // Actualizar precio
            $params = ['precio' => $precio];
            $resultado = self::update('insumos', $params, ['id' => $id]);

            if ($resultado) {
                $response['success'] = true;
                $response['message'] = "Precio actualizado correctamente";
            } else {
                $response['message'] = "No se pudo actualizar el precio";
            }
        } catch (\Exception $e) {
            $response['message'] = "Error al actualizar precio: " . $e->getMessage();
            error_log("Error en actualizarPrecio ID=$id: " . $e->getMessage());
        }

        return $response;
    }

  /**
   * Actualizar precios de múltiples insumos masivamente
   */
    public function actualizarPreciosMasivo($actualizaciones)
    {
        $response = array(
        "success" => false,
        "message" => null,
        "actualizados" => 0,
        "errores" => []
        );

        try {
            if (empty($actualizaciones)) {
                $response['message'] = "No hay insumos para actualizar";
                return $response;
            }

            $actualizados = 0;
            $errores = [];

          // Iniciar transacción para mantener consistencia
            self::beginTransaction();

            foreach ($actualizaciones as $item) {
                $id = intval($item['id']);
                $precio = floatval($item['precio']);

                if ($id <= 0 || $precio < 0) {
                    $errores[] = "ID o precio inválido: ID=$id, Precio=$precio";
                    continue;
                }

                try {
                  // Verificar que el insumo existe
                    $sql = 'SELECT id FROM insumos WHERE id = :id';
                    $insumo = self::fetchObj($sql, ['id' => $id]);

                    if (!$insumo) {
                        $errores[] = "El insumo con ID $id no existe";
                        continue;
                    }

                  // Actualizar precio
                    $params = ['precio' => $precio];
                    $resultado = self::update('insumos', $params, ['id' => $id]);

                    if ($resultado) {
                        $actualizados++;
                    } else {
                        $errores[] = "Error al actualizar ID $id";
                    }
                } catch (\Exception $e) {
                    $errores[] = "Error al actualizar ID $id: " . $e->getMessage();
                    error_log("Error en actualizarPreciosMasivo ID=$id: " . $e->getMessage());
                }
            }

          // Confirmar transacción
            self::commit();

          // Preparar respuesta
            $response['success'] = $actualizados > 0;
            $response['actualizados'] = $actualizados;
            $response['errores'] = $errores;

            if ($actualizados > 0) {
                $response['message'] = "Se actualizaron $actualizados insumo(s) correctamente";
                if (!empty($errores)) {
                    $response['message'] .= ". Algunos registros presentaron errores.";
                }
            } else {
                $response['message'] = "No se pudo actualizar ningún insumo";
            }
        } catch (\Exception $e) {
          // Revertir transacción en caso de error
            self::rollback();
            $response['message'] = "Error al procesar actualización masiva: " . $e->getMessage();
            error_log("Error en actualizarPreciosMasivo: " . $e->getMessage());
        }

        return $response;
    }


/**
 * Obtener conteo de insumos por estado de archivo
 *
 * @param int|null $archivo_id
 * @return array
 */
    public function getConteoInsumosPorArchivo($archivo_id = null)
    {
        if ($archivo_id === null || $archivo_id === 'null' || $archivo_id === '') {
            $sql = "SELECT COUNT(*) as total FROM insumos WHERE archivo_id IS NULL";
            $params = [];
        } else {
            $sql = "SELECT COUNT(*) as total FROM insumos WHERE archivo_id = :archivo_id";
            $params = ['archivo_id' => $archivo_id];
        }

        $resultado = self::fetchObj($sql, $params);

        return [
        'success' => true,
        'data' => [
            'total' => $resultado ? $resultado->total : 0,
            'archivo_id' => $archivo_id
        ]
        ];
    }

/**
 * Obtener todos los insumos (con o sin archivo)
 *
 * @param int|null $archivo_id - NULL para insumos sin archivo, ID para archivo específico
 * @return array
 */
    public function getInsumosMasterPorArchivo($archivo_id = null)
    {
        if ($archivo_id === null || $archivo_id === 'null' || $archivo_id === '') {
            // Insumos sin archivo asignado
            $sql = "SELECT 
                    i.id,
                    i.codigo,
                    i.insumos,
                    i.tipo,
                    i.precio,
                    i.iu,
                    i.indice_unificado,
                    i.archivo_id,
                    um.alias AS unidad_medida,
                    um.descripcion AS unidad_descripcion,
                    um.id AS unidad_medidas_id
                FROM insumos i
                INNER JOIN unidad_medidas um ON um.id = i.unidad_medidas_id
                WHERE i.archivo_id IS NULL
                ORDER BY i.tipo ASC, i.insumos ASC";
            $params = [];
        } else {
            // Insumos de un archivo específico
            $sql = "SELECT 
                    i.id,
                    i.codigo,
                    i.insumos,
                    i.tipo,
                    i.precio,
                    i.iu,
                    i.indice_unificado,
                    i.archivo_id,
                    um.alias AS unidad_medida,
                    um.descripcion AS unidad_descripcion,
                    um.id AS unidad_medidas_id
                FROM insumos i
                INNER JOIN unidad_medidas um ON um.id = i.unidad_medidas_id
                WHERE i.archivo_id = :archivo_id
                ORDER BY i.tipo ASC, i.insumos ASC";
            $params = ['archivo_id' => $archivo_id];
        }

        $insumos = self::fetchAllObj($sql, $params);

        return [
        'success' => true,
        'data' => $insumos,
        'total' => count($insumos),
        'archivo_id' => $archivo_id
        ];
    }
/**
 * Obtener insumos por archivo_id con su estado
 *
 * @param int $archivo_id
 * @param int $proyectos_generales_id
 * @return array
 */
    public function getInsumosByArchivo($archivo_id, $proyectos_generales_id)
    {
        $sql = "SELECT 
                ip.id,
                ip.insumos,
                ip.tipo,
                ip.precio,
                ip.iu,
                ip.indice_unificado,
                ip.estado,
                ip.master_insumo_id,
                ip.archivo_id,
                um.alias AS unidad_medida,
                um.id AS unidad_medidas_id
            FROM insumos_proyecto ip
            INNER JOIN unidad_medidas um ON um.id = ip.unidad_medidas_id
            WHERE ip.archivo_id = :archivo_id
            AND ip.proyectos_generales_id = :proyecto_id
            ORDER BY ip.estado DESC, ip.tipo ASC";

        $insumos = self::fetchAllObj($sql, [
        'archivo_id' => $archivo_id,
        'proyecto_id' => $proyectos_generales_id
        ]);

        return [
        'success' => true,
        'data' => $insumos
        ];
    }

/**
 * Actualizar estado de insumos específicos
 *
 * @param array $insumos_ids - Array de IDs de insumos_proyecto
 * @param int $estado - 0 o 1
 * @return array
 */
    public function actualizarEstadoInsumos($insumos_ids, $estado)
    {
        if (empty($insumos_ids) || !is_array($insumos_ids)) {
            return ['success' => false, 'message' => 'IDs inválidos'];
        }

        $placeholders = implode(',', array_fill(0, count($insumos_ids), '?'));

        $sql = "UPDATE insumos_proyecto 
            SET estado = ? 
            WHERE id IN ($placeholders)";

        $params = array_merge([$estado], $insumos_ids);

        $resultado = self::insert($sql, $params);

        return [
        'success' => $resultado,
        'message' => $resultado ? 'Estados actualizados' : 'Error al actualizar'
        ];
    }

/**
 * Marcar todos los insumos de un archivo como inactivos (estado=0)
 *
 * @param int $archivo_id
 * @param int $proyectos_generales_id
 * @return array
 */
    public function desactivarInsumosPorArchivo($archivo_id, $proyectos_generales_id)
    {
        $sql = "UPDATE insumos_proyecto 
            SET estado = 0 
            WHERE archivo_id = :archivo_id 
            AND proyectos_generales_id = :proyecto_id";

        $resultado = self::ex($sql, [
        'archivo_id' => $archivo_id,
        'proyecto_id' => $proyectos_generales_id
        ]);

        return [
        'success' => $resultado,
        'message' => $resultado ? 'Insumos desactivados' : 'Error al desactivar'
        ];
    }

/**
 * Obtener detalle de insumos por archivo en un proyecto específico
 *
 * @param int $archivo_id
 * @param int $proyectos_generales_id
 * @return array
 */
    public function getDetalleInsumosPorArchivo($archivo_id, $proyectos_generales_id)
    {
        $response = ["success" => false, "data" => []];

        try {
            $sql = "SELECT 
                    ip.id,
                    ip.insumos,
                    ip.tipo,
                    ip.precio,
                    ip.iu,
                    ip.indice_unificado,
                    ip.estado,
                    ip.master_insumo_id,
                    ip.archivo_id,
                    um.alias AS unidad_medida,
                    um.id AS unidad_medidas_id
                FROM insumos_proyecto ip
                INNER JOIN unidad_medidas um ON um.id = ip.unidad_medidas_id
                WHERE ip.archivo_id = :archivo_id
                AND ip.proyectos_generales_id = :proyecto_id
                ORDER BY ip.estado DESC, ip.tipo ASC, ip.insumos ASC";

            $insumos = self::fetchAllObj($sql, [
            'archivo_id' => $archivo_id,
            'proyecto_id' => $proyectos_generales_id
            ]);

            $response["success"] = true;
            $response["data"] = $insumos;
        } catch (\Exception $e) {
            $response["message"] = "Error: " . $e->getMessage();
            error_log("Error en getDetalleInsumosPorArchivo: " . $e->getMessage());
        }

        return $response;
    }

/**
 * Obtener estadísticas de insumos agrupados por archivo en un proyecto
 *
 * @param int $proyectos_generales_id
 * @return array
 */
    public function getInsumosAgrupadosPorArchivo($proyectos_generales_id)
    {
        $response = ["success" => false, "data" => []];

        try {
            // Estadísticas por archivo
            $sql = "SELECT 
                    COALESCE(ip.archivo_id, 0) as archivo_id,
                    COALESCE(a.nombre, 'Sin archivo asignado') as nombre_archivo,
                    COUNT(ip.id) as total_insumos,
                    SUM(CASE WHEN ip.estado = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN ip.estado = 0 THEN 1 ELSE 0 END) as inactivos,
                    COUNT(DISTINCT ip.tipo) as tipos_diferentes
                FROM insumos_proyecto ip
                LEFT JOIN archivos a ON ip.archivo_id = a.id AND a.deleted_at IS NULL
                WHERE ip.proyectos_generales_id = :proyecto_id
                GROUP BY COALESCE(ip.archivo_id, 0), COALESCE(a.nombre, 'Sin archivo asignado')
                ORDER BY 
                    CASE WHEN ip.archivo_id IS NULL THEN 1 ELSE 0 END,
                    a.nombre ASC";

            $estadisticas = self::fetchAllObj($sql, ['proyecto_id' => $proyectos_generales_id]);

            // Totales generales
            $sqlTotales = "SELECT 
                        COUNT(ip.id) as total_general,
                        SUM(CASE WHEN ip.estado = 1 THEN 1 ELSE 0 END) as activos_general,
                        SUM(CASE WHEN ip.estado = 0 THEN 1 ELSE 0 END) as inactivos_general,
                        COUNT(DISTINCT ip.archivo_id) as total_archivos
                       FROM insumos_proyecto ip
                       WHERE ip.proyectos_generales_id = :proyecto_id";

            $totales = self::fetchObj($sqlTotales, ['proyecto_id' => $proyectos_generales_id]);

            $response["success"] = true;
            $response["data"] = [
            'por_archivo' => $estadisticas,
            'totales' => $totales
            ];
        } catch (\Exception $e) {
            $response["message"] = "Error: " . $e->getMessage();
            error_log("Error en getInsumosAgrupadosPorArchivo: " . $e->getMessage());
        }

        return $response;
    }

/**
 * Cambiar fuente de insumos de un proyecto
 * Desactiva insumos actuales e inserta nuevos desde catálogo
 *
 * @param int $proyecto_id - ID del proyecto
 * @param int $archivo_id - ID del nuevo archivo fuente
 * @return array - Resultado de la operación con estadísticas
 */
    public function cambiarFuenteInsumos($proyecto_id, $archivo_id)
    {
        $response = ["success" => false, "message" => null, "debug" => []];

        error_log("========== INICIO cambiarFuenteInsumos ==========");
        error_log("Parámetros recibidos: proyecto_id={$proyecto_id}, archivo_id={$archivo_id}");

        try {
            // PASO 1: Validar que el proyecto existe
            error_log("PASO 1: Validando existencia del proyecto...");
            $sqlProyecto = "SELECT id, proyecto FROM proyecto_generales WHERE id = :id AND deleted_at IS NULL";
            $proyecto = self::fetchObj($sqlProyecto, ['id' => $proyecto_id]);

            if (!$proyecto) {
                error_log("ERROR PASO 1: Proyecto no encontrado o eliminado");
                throw new Exception("El proyecto con ID {$proyecto_id} no existe o fue eliminado");
            }

            error_log("PASO 1 OK: Proyecto encontrado - {$proyecto->proyecto}");
            $response['debug'][] = "Paso 1: Proyecto validado correctamente";

            // PASO 2: Validar que el archivo tiene insumos
            error_log("PASO 2: Validando insumos en archivo fuente...");
            $sqlConteo = "SELECT COUNT(*) as total FROM insumos WHERE archivo_id = :archivo_id";
            $conteo = self::fetchObj($sqlConteo, ['archivo_id' => $archivo_id]);

            if (!$conteo || $conteo->total == 0) {
                error_log("ERROR PASO 2: No hay insumos en archivo_id={$archivo_id}");
                throw new Exception("El archivo seleccionado no tiene insumos asociados");
            }

            error_log("PASO 2 OK: Archivo tiene {$conteo->total} insumos disponibles");
            $response['debug'][] = "Paso 2: Archivo validado - {$conteo->total} insumos disponibles";

            // PASO 3: Iniciar transacción
            error_log("PASO 3: Iniciando transacción...");
            self::beginTransaction();
            error_log("PASO 3 OK: Transacción iniciada");
            $response['debug'][] = "Paso 3: Transacción iniciada";

            // PASO 4: Desactivar insumos actuales del proyecto
            error_log("PASO 4: Desactivando insumos actuales...");
            $sqlDesactivar = "UPDATE insumos_proyecto
                          SET estado = 0
                          WHERE proyectos_generales_id = :proyecto_id
                            AND estado = 1";

            $resultadoDesactivar = self::ex($sqlDesactivar, ['proyecto_id' => $proyecto_id]);

            // Contar cuántos se desactivaron
            $sqlConteoDesactivados = "SELECT ROW_COUNT() as afectados";
            $desactivados = self::fetchObj($sqlConteoDesactivados);
            $totalDesactivados = $desactivados ? $desactivados->afectados : 0;

            error_log("PASO 4 OK: {$totalDesactivados} insumos desactivados");
            $response['debug'][] = "Paso 4: {$totalDesactivados} insumos desactivados";

            // PASO 5: Insertar nuevos insumos desde catálogo
            error_log("PASO 5: Insertando nuevos insumos desde archivo_id={$archivo_id}...");
            $sqlInsertar = "INSERT INTO insumos_proyecto (
                            codigo, iu, indice_unificado, tipo, insumos,
                            precio, unidad_medidas_id,
                            proyectos_generales_id, archivo_id, estado
                        )
                        SELECT
                            i.codigo, 
                            i.iu, 
                            i.indice_unificado, 
                            i.tipo, 
                            i.insumos,
                            i.precio, 
                            i.unidad_medidas_id, 
                            :proyecto_id, 
                            i.archivo_id, 
                            1,
                            NOW()
                        FROM insumos i
                        WHERE i.archivo_id = :archivo_id";

            $resultadoInsertar = self::ex($sqlInsertar, [
            'proyecto_id' => $proyecto_id,
            'archivo_id'  => $archivo_id
            ]);

            // Contar cuántos se insertaron
            $sqlConteoInsertados = "SELECT ROW_COUNT() as afectados";
            $insertados = self::fetchObj($sqlConteoInsertados);
            $totalInsertados = $insertados ? $insertados->afectados : 0;

            if ($totalInsertados == 0) {
                error_log("ERROR PASO 5: No se insertaron insumos (posible problema con la consulta)");
                throw new Exception("No se pudieron insertar los insumos del archivo seleccionado");
            }

            error_log("PASO 5 OK: {$totalInsertados} insumos insertados correctamente");
            $response['debug'][] = "Paso 5: {$totalInsertados} nuevos insumos insertados";

            // PASO 6: Confirmar transacción
            error_log("PASO 6: Confirmando transacción...");
            self::commit();
            error_log("PASO 6 OK: Transacción confirmada exitosamente");
            $response['debug'][] = "Paso 6: Transacción confirmada";

            // Preparar respuesta exitosa
            $response["success"] = true;
            $response["message"] = "Fuente de insumos cambiada correctamente";
            $response["data"] = [
            'desactivados' => $totalDesactivados,
            'insertados' => $totalInsertados,
            'archivo_id' => $archivo_id,
            'proyecto_id' => $proyecto_id
            ];

            error_log("========== FIN EXITOSO cambiarFuenteInsumos ==========");
            error_log("Resultado: {$totalInsertados} insumos nuevos, {$totalDesactivados} desactivados");
        } catch (\Exception $e) {
            // Revertir transacción en caso de error
            error_log("========== ERROR EN cambiarFuenteInsumos ==========");
            error_log("Excepción capturada: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            try {
                self::rollback();
                error_log("Rollback ejecutado correctamente");
                $response['debug'][] = "Error detectado - Rollback ejecutado";
            } catch (\Exception $rollbackEx) {
                error_log("ERROR AL HACER ROLLBACK: " . $rollbackEx->getMessage());
                $response['debug'][] = "Error crítico en rollback";
            }

            $response["message"] = "Error al cambiar fuente de insumos: " . $e->getMessage();
            $response["error_detail"] = $e->getMessage();

            error_log("========== FIN CON ERROR cambiarFuenteInsumos ==========");
        }

        return $response;
    }

/**
 * Asignar insumos desde la tabla 'insumos' hacia 'insumos_proyecto'
 * Solo crea los que no existen y marca estado=1 para creados, estado=0 para no creados
 *
 * @param int|null $archivo_id - ID del archivo de fuente de insumos (NULL para insumos sin archivo)
 * @param int $proyectos_generales_id - ID del proyecto
 * @return array - Resultado con estadísticas de la operación
 */
    public function asignarInsumosDeArchivoAProyecto($archivo_id, $proyectos_generales_id)
    {
        try {
            // 1. Construir consulta según si archivo_id es NULL o no
            if ($archivo_id === null || $archivo_id === 'null' || $archivo_id === '') {
                // Buscar insumos sin archivo asignado
                $sql = "SELECT id, insumos, tipo, precio, iu, indice_unificado, unidad_medidas_id, archivo_id
                    FROM insumos 
                    WHERE archivo_id IS NULL";
                $params = [];
            } else {
                // Buscar insumos del archivo específico
                $sql = "SELECT id, insumos, tipo, precio, iu, indice_unificado, unidad_medidas_id, archivo_id
                    FROM insumos 
                    WHERE archivo_id = :archivo_id";
                $params = ['archivo_id' => $archivo_id];
            }

            $insumosFuente = self::fetchAllObj($sql, $params);

            if (empty($insumosFuente)) {
                $mensaje = $archivo_id
                ? "No se encontraron insumos en el archivo especificado (ID: {$archivo_id})"
                : "No se encontraron insumos sin archivo asignado";

                return [
                'success' => false,
                'message' => $mensaje,
                'data' => [
                    'creados' => 0,
                    'omitidos' => 0,
                    'actualizados' => 0,
                    'total' => 0,
                    'archivo_id' => $archivo_id
                ]
                ];
            }

            $creados = 0;
            $omitidos = 0;
            $actualizados = 0;
            $errores = [];

            // 2. Procesar cada insumo
            foreach ($insumosFuente as $insumo) {
                // Verificar si el insumo ya existe en el proyecto (por master_insumo_id)
                $sqlCheck = "SELECT id, estado FROM insumos_proyecto 
                         WHERE master_insumo_id = :master_id 
                         AND proyectos_generales_id = :proyecto_id";

                $existe = self::fetchObj($sqlCheck, [
                'master_id' => $insumo->id,
                'proyecto_id' => $proyectos_generales_id
                ]);

                if ($existe) {
                    // Ya existe, actualizar estado a 0 (duplicado) y sincronizar datos
                    $sqlUpdate = "UPDATE insumos_proyecto 
                              SET estado = 0, 
                                  archivo_id = :archivo_id,
                                  precio = :precio,
                                  iu = :iu,
                                  indice_unificado = :indice_unificado
                              WHERE id = :id";

                    $resultado = self::ex($sqlUpdate, [
                        'archivo_id' => $archivo_id, // Puede ser NULL
                        'precio' => $insumo->precio,
                        'iu' => $insumo->iu,
                        'indice_unificado' => $insumo->indice_unificado,
                        'id' => $existe->id
                    ]);

                    if ($resultado) {
                            $actualizados++;
                    }
                    $omitidos++;
                } else {
                    // No existe, crear con estado = 1 (creado exitosamente)
                    $params = [
                    'insumos' => $insumo->insumos,
                    'tipo' => $insumo->tipo,
                    'precio' => $insumo->precio,
                    'iu' => $insumo->iu,
                    'indice_unificado' => $insumo->indice_unificado,
                    'unidad_medidas_id' => $insumo->unidad_medidas_id,
                    'master_insumo_id' => $insumo->id,
                    'archivo_id' => $archivo_id, // Puede ser NULL
                    'proyectos_generales_id' => $proyectos_generales_id,
                    'estado' => 1  // ✅ Creado exitosamente
                    ];

                    $resultado = self::insert('insumos_proyecto', $params);

                    if ($resultado) {
                        $creados++;
                    } else {
                        $errores[] = "Error al crear: " . $insumo->insumos;
                    }
                }
            }

            $mensaje = $archivo_id
            ? "Proceso completado del archivo ID {$archivo_id}: {$creados} creados, {$omitidos} omitidos, {$actualizados} actualizados"
            : "Proceso completado de insumos sin archivo: {$creados} creados, {$omitidos} omitidos, {$actualizados} actualizados";

            return [
            'success' => true,
            'message' => $mensaje,
            'data' => [
                'creados' => $creados,
                'omitidos' => $omitidos,
                'actualizados' => $actualizados,
                'total' => count($insumosFuente),
                'errores' => $errores,
                'archivo_id' => $archivo_id
            ]
            ];
        } catch (\Exception $e) {
            return [
            'success' => false,
            'message' => 'Error al asignar insumos: ' . $e->getMessage(),
            'data' => [
                'creados' => 0,
                'omitidos' => 0,
                'actualizados' => 0,
                'total' => 0,
                'archivo_id' => $archivo_id
            ]
            ];
        }
    }
/**
 * Cambiar fuente de insumos de un proyecto
 * Desactiva insumos actuales e inserta nuevos desde catálogo
 *
 * @param int $proyecto_id - ID del proyecto
 * @param int $archivo_id - ID del nuevo archivo fuente
 * @return array - Resultado de la operación con estadísticas
 */
    public function cambiarFuenteInsumosModelo($proyecto_id, $archivo_id)
    {
        $response = ["success" => false, "message" => null, "data" => [], "debug" => []];

        error_log("========== INICIO cambiarFuenteInsumosModelo ==========");
        error_log("Parámetros: proyecto_id={$proyecto_id}, archivo_id={$archivo_id}");

        try {
            // PASO 1: Validar que el proyecto existe
            error_log("PASO 1: Validando existencia del proyecto...");
            $sqlProyecto = "SELECT id, proyecto FROM proyecto_generales WHERE id = :id AND deleted_at IS NULL";
            $proyecto = self::fetchObj($sqlProyecto, ['id' => $proyecto_id]);

            if (!$proyecto) {
                error_log("ERROR PASO 1: Proyecto no encontrado");
                throw new \Exception("El proyecto con ID {$proyecto_id} no existe o fue eliminado");
            }

            error_log("PASO 1 OK: Proyecto '{$proyecto->proyecto}' encontrado");
            $response['debug'][] = "Paso 1: Proyecto validado";

            // PASO 2: Validar que el archivo tiene insumos
            error_log("PASO 2: Validando insumos en archivo...");
            $sqlConteo = "SELECT COUNT(*) as total FROM insumos WHERE archivo_id = :archivo_id";
            $conteo = self::fetchObj($sqlConteo, ['archivo_id' => $archivo_id]);

            if (!$conteo || $conteo->total == 0) {
                error_log("ERROR PASO 2: Archivo sin insumos (total={$conteo->total})");
                throw new \Exception("El archivo seleccionado (ID: {$archivo_id}) no tiene insumos asociados");
            }

            error_log("PASO 2 OK: {$conteo->total} insumos disponibles en catálogo");
            $response['debug'][] = "Paso 2: {$conteo->total} insumos disponibles";

            // PASO 3: Iniciar transacción
            error_log("PASO 3: Iniciando transacción...");
            self::beginTransaction();
            error_log("PASO 3 OK: Transacción iniciada");
            $response['debug'][] = "Paso 3: Transacción iniciada";

            // PASO 4: Contar insumos actuales activos antes de desactivar
            error_log("PASO 4A: Contando insumos activos actuales...");
            $sqlCountActivos = "SELECT COUNT(*) as total FROM insumos_proyecto 
                            WHERE proyectos_generales_id = :proyecto_id AND estado = 1";
            $activosAntes = self::fetchObj($sqlCountActivos, ['proyecto_id' => $proyecto_id]);
            $totalActivosAntes = $activosAntes ? $activosAntes->total : 0;
            error_log("PASO 4A: Hay {$totalActivosAntes} insumos activos actualmente");

            // PASO 4B: Desactivar insumos actuales
            error_log("PASO 4B: Desactivando insumos actuales...");
            $sqlDesactivar = "UPDATE insumos_proyecto
                          SET estado = 0
                          WHERE proyectos_generales_id = :proyecto_id
                            AND estado = 1";

            $resultadoDesactivar = self::execute($sqlDesactivar, ['proyecto_id' => $proyecto_id]);


            if (!$resultadoDesactivar) {
                error_log("ERROR PASO 4B: No se pudo ejecutar UPDATE");
                throw new \Exception("Error al desactivar insumos actuales");
            }

            // Verificar cuántos quedaron desactivados
            $sqlCountInactivos = "SELECT COUNT(*) as total FROM insumos_proyecto 
                              WHERE proyectos_generales_id = :proyecto_id AND estado = 0";
            $inactivosDespues = self::fetchObj($sqlCountInactivos, ['proyecto_id' => $proyecto_id]);
            $totalDesactivados = $inactivosDespues ? $inactivosDespues->total : 0;

            error_log("PASO 4B OK: {$totalActivosAntes} insumos marcados como inactivos");
            $response['debug'][] = "Paso 4: {$totalActivosAntes} insumos desactivados";

            // PASO 5: Insertar nuevos insumos
            error_log("PASO 5: Insertando nuevos insumos desde archivo_id={$archivo_id}...");
            $sqlInsertar = "INSERT INTO insumos_proyecto (
                            codigo, iu, indice_unificado, tipo, insumos,
                            precio, unidad_medidas_id,
                            proyectos_generales_id, archivo_id, estado
                        )
                        SELECT
                            i.codigo, i.iu, i.indice_unificado, i.tipo, i.insumos,
                            i.precio, i.unidad_medidas_id,
                            :proyecto_id, i.archivo_id, 1
                        FROM insumos i
                        WHERE i.archivo_id = :archivo_id";

            $resultadoInsertar = self::execute($sqlInsertar, [
            'proyecto_id' => $proyecto_id,
            'archivo_id'  => $archivo_id
            ]);

            if (!$resultadoInsertar) {
                error_log("ERROR PASO 5: No se pudo ejecutar INSERT");
                throw new \Exception("Error al insertar nuevos insumos");
            }

            // Contar cuántos se insertaron realmente
            $sqlCountInsertados = "SELECT COUNT(*) as total FROM insumos_proyecto 
                               WHERE proyectos_generales_id = :proyecto_id 
                               AND archivo_id = :archivo_id AND estado = 1";
            $insertados = self::fetchObj($sqlCountInsertados, [
            'proyecto_id' => $proyecto_id,
            'archivo_id' => $archivo_id
            ]);
            $totalInsertados = $insertados ? $insertados->total : 0;

            if ($totalInsertados == 0) {
                error_log("ERROR PASO 5: Se ejecutó INSERT pero no se insertaron registros");
                throw new \Exception("No se pudieron insertar los insumos (0 registros afectados)");
            }

            error_log("PASO 5 OK: {$totalInsertados} insumos insertados correctamente");
            $response['debug'][] = "Paso 5: {$totalInsertados} insumos insertados";
            /*
            // PASO 6: Actualizar archivo_insumo_id en proyecto_generales
            error_log("PASO 6: Actualizando proyecto_generales...");
            $sqlUpdateProyecto = "UPDATE proyecto_generales
                              SET archivo_insumo_id = :archivo_id
                              WHERE id = :proyecto_id";

            $resultadoUpdate = self::execute($sqlUpdateProyecto, [
            'archivo_id' => $archivo_id,
            'proyecto_id' => $proyecto_id
            ]);

            if (!$resultadoUpdate) {
            error_log("ERROR PASO 6: No se pudo actualizar proyecto_generales");
            throw new \Exception("Error al actualizar la referencia del proyecto");
            }


            error_log("PASO 6 OK: proyecto_generales.archivo_insumo_id actualizado");
            $response['debug'][] = "Paso 6: Proyecto actualizado";
            */
            // PASO 7: Confirmar transacción
            error_log("PASO 7: Confirmando transacción...");
            self::commit();
            error_log("PASO 7 OK: Transacción confirmada exitosamente");
            $response['debug'][] = "Paso 7: Transacción confirmada";

            // Respuesta exitosa
            $response["success"] = true;
            $response["message"] = "Fuente de insumos cambiada correctamente. " .
                               "{$totalInsertados} insumos nuevos activados, " .
                               "{$totalActivosAntes} insumos anteriores desactivados.";
            $response["data"] = [
            'desactivados' => $totalActivosAntes,
            'insertados' => $totalInsertados,
            'archivo_id' => $archivo_id,
            'proyecto_id' => $proyecto_id
            ];

            error_log("========== FIN EXITOSO ==========");
            error_log("Resumen: {$totalInsertados} insertados, {$totalActivosAntes} desactivados");
        } catch (\Exception $e) {
            error_log("========== ERROR EN TRANSACCIÓN ==========");
            error_log("Excepción: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());

            try {
                if (self::inTransaction()) {
                    self::rollback();
                    error_log("Rollback ejecutado correctamente");
                    $response['debug'][] = "Error detectado - Rollback ejecutado";
                } else {
                    error_log("No hay transacción activa para hacer rollback");
                }
            } catch (\Exception $rollbackEx) {
                error_log("ERROR CRÍTICO AL HACER ROLLBACK: " . $rollbackEx->getMessage());
                $response['debug'][] = "Error crítico en rollback";
            }

            $response["message"] = "Error al cambiar fuente de insumos: " . $e->getMessage();
            $response["error_detail"] = $e->getMessage();

            error_log("========== FIN CON ERROR ==========");
        }

        return $response;
    }


/**
 * Actualizar el archivo_insumo_id en proyecto_generales
 * (método auxiliar para compatibilidad)
 */
    public function updateFuenteInsumos($proyecto_id, $archivo_id)
    {
        try {
            $sql = "UPDATE proyecto_generales 
                SET archivo_insumo_id = :archivo_id
                WHERE id = :proyecto_id AND deleted_at IS NULL";

            $resultado = self::ex($sql, [
            'archivo_id' => $archivo_id,
            'proyecto_id' => $proyecto_id
            ]);

            return [
                'success' => $resultado,
                'message' => $resultado ? 'Proyecto actualizado' : 'Error al actualizar'
            ];
        } catch (\Exception $e) {
            return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}
