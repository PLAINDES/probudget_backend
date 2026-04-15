<?php

require_once(__DIR__ . '/../model/src/Insumos.php');

class InsumosController
{
    public function getListAll()
    {
        $insumos   = new Insumos();
        return $insumos->getListAll();
    }

    public function getList($request)
    {
        $insumos   = new Insumos();
        return $insumos->getListInsumo($request->proyectos_generales_id);
    }

    public function getListByArchivo($request)
    {

        $insumos   = new Insumos();
        return $insumos->getListInsumoByArchivo($request->archivo_id);
    }

    public function searchInsumos($request)
    {
        // Capturamos los datos enviados por Select2 (vía GET o POST dependiendo de tu config)
        $termino     = $request->param('q');
        $tipo        = $request->param('tipo');
        $proyecto_id = $request->param('proyecto_id');

        // Opción B (Más corta): Acceso directo a propiedades
        // $termino = $request->q;

        $insumos = new Insumos();
        return $insumos->searchByName($termino, $tipo, $proyecto_id);
    }


    public function getFuenteInsumos()
    {
        $insumos   = new Insumos();
        return $insumos->getInsumoDatabases();
    }

    public function getIndicesUnificados()
    {
        $insumos   = new Insumos();
        return $insumos->getIndicesUnificados();
    }

    public function renameInsumo($request)
    {
        $insumos   = new Insumos();
        return $insumos->renameInsumo($request);
    }

   /**
     * Actualizar precio de un insumo individual
     * Compatible con Klein Router
     */
    public function actualizarPrecio($request)
    {
        error_log("===== INICIO actualizarPrecio CONTROLLER =====");
        header('Content-Type: application/json');

        $response = array(
            "success" => false,
            "message" => null
        );

        try {
            // KLEIN ROUTER: Leer body RAW con file_get_contents
            $rawBody = file_get_contents("php://input");
            error_log("RAW Body: " . $rawBody);

            // Decodificar JSON
            $body = json_decode($rawBody, true);
            error_log("Body decodificado: " . json_encode($body));

            // Si viene como form-urlencoded, usar params() de Klein
            if ($body === null && $request->params()) {
                error_log("JSON decode falló, usando Klein params()");
                $body = $request->params();
                error_log("Klein params: " . json_encode($body));
            }

            if (!isset($body['id']) || !isset($body['precio'])) {
                $response['message'] = "Datos incompletos. Se requiere id y precio";
                error_log("ERROR: Datos incompletos");
                echo json_encode($response);
                return;
            }

            $id = intval($body['id']);
            $precio = floatval($body['precio']);
            error_log("ID: $id, Precio: $precio");

            if ($id <= 0 || $precio < 0) {
                $response['message'] = "ID o precio inválido";
                error_log("ERROR: Validación fallida");
                echo json_encode($response);
                return;
            }

            // Actualizar precio
            $insumo = new Insumos();
            $resultado = $insumo->actualizarPrecio($id, $precio);
            error_log("Resultado: " . json_encode($resultado));

            $response = $resultado;
        } catch (\Exception $e) {
            $response['message'] = "Error en el servidor: " . $e->getMessage();
            error_log("EXCEPTION: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

        error_log("Respuesta: " . json_encode($response));
        error_log("===== FIN actualizarPrecio CONTROLLER =====");
        echo json_encode($response);
    }

    /**
     * Actualizar precios de múltiples insumos masivamente
     * Compatible con Klein Router
     */
    public function actualizarPreciosMasivo($request)
    {
        error_log("===== INICIO actualizarPreciosMasivo CONTROLLER =====");
        header('Content-Type: application/json');

        $response = array(
            "success" => false,
            "message" => null,
            "actualizados" => 0,
            "errores" => []
        );

        try {
            // KLEIN ROUTER: Leer body RAW con file_get_contents
            $rawBody = file_get_contents("php://input");
            error_log("RAW Body: " . $rawBody);

            // Decodificar JSON
            $body = json_decode($rawBody, true);
            error_log("Body decodificado: " . json_encode($body));

            // Si viene como form-urlencoded, usar params() de Klein
            if ($body === null && $request->params()) {
                error_log("JSON decode falló, usando Klein params()");
                $body = $request->params();
                error_log("Klein params: " . json_encode($body));
            }

            if (!isset($body['actualizaciones']) || !is_array($body['actualizaciones'])) {
                $response['message'] = "No se recibieron datos de actualización";
                error_log("ERROR: 'actualizaciones' no existe o no es array");
                echo json_encode($response);
                return;
            }

            $actualizaciones = $body['actualizaciones'];
            error_log("Total actualizaciones: " . count($actualizaciones));

            if (empty($actualizaciones)) {
                $response['message'] = "No hay insumos para actualizar";
                error_log("ERROR: Array vacío");
                echo json_encode($response);
                return;
            }

            // Validar estructura
            foreach ($actualizaciones as $index => $item) {
                if (!isset($item['id']) || !isset($item['precio'])) {
                    $response['message'] = "Datos incompletos en las actualizaciones";
                    error_log("ERROR: Item $index incompleto");
                    echo json_encode($response);
                    return;
                }
            }

            // Procesar actualización masiva
            $insumo = new Insumos();
            $resultado = $insumo->actualizarPreciosMasivo($actualizaciones);
            error_log("Resultado: " . json_encode($resultado));

            $response = $resultado;
        } catch (\Exception $e) {
            $response['message'] = "Error en el servidor: " . $e->getMessage();
            error_log("EXCEPTION: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }

        error_log("Respuesta: " . json_encode($response));
        error_log("===== FIN actualizarPreciosMasivo CONTROLLER =====");
        echo json_encode($response);
    }


/**
 * Cambiar la fuente de insumos de un proyecto
 * Desactiva insumos actuales e inserta nuevos desde el catálogo
 */
    public function cambiarFuenteInsumos($request)
    {
        $response = ["success" => false, "message" => null, "data" => []];
        error_log('========== CONTROLLER cambiarFuenteInsumos ==========');
        error_log('REQUEST recibido: ' . print_r($request, true));

        try {
            // Extraer parámetros
            $proyecto_generales_id = $request->proyecto_generales_id ?? null;
            $archivo_insumo_id = $request->archivo_insumo_id ?? null;

            error_log("Parámetros extraídos: proyecto_id={$proyecto_generales_id}, archivo_id={$archivo_insumo_id}");

            // Validación de proyecto
            if ($proyecto_generales_id === null || $proyecto_generales_id === '') {
                error_log("ERROR: ID de proyecto no especificado");
                $response["message"] = "ID de proyecto no especificado";
                return $response;
            }

            // Convertir string 'null' o vacío a NULL real
            if ($archivo_insumo_id === 'null' || $archivo_insumo_id === '' || $archivo_insumo_id == 0) {
                error_log("Convirtiendo archivo_id a NULL (era: '{$archivo_insumo_id}')");
                $archivo_insumo_id = null;
            }

            // Validación de archivo
            if ($archivo_insumo_id === null) {
                error_log("ERROR: archivo_insumo_id es NULL o inválido");
                $response["message"] = "Debe seleccionar un archivo de insumos válido";
                return $response;
            }

            error_log("Validaciones OK, llamando a modelo Insumos...");

            // Llamar al método del modelo
            $insumos = new Insumos();
            $resultado = $insumos->cambiarFuenteInsumosModelo(
                $proyecto_generales_id,
                $archivo_insumo_id
            );

            error_log("Respuesta del modelo: " . print_r($resultado, true));

            // Devolver resultado
            $response = $resultado;

            error_log("========== FIN CONTROLLER cambiarFuenteInsumos ==========");
        } catch (\Exception $e) {
            error_log("========== EXCEPCIÓN EN CONTROLLER ==========");
            error_log("Error: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());

            $response["message"] = "Error en controller: " . $e->getMessage();
        }

        return $response;
    }

/**
 * Obtener todos los insumos de un archivo específico
 */
    public function getInsumosByArchivo($request)
    {
        $response = ["success" => false, "data" => []];

        try {
            $archivo_id = $request->archivo_id;
            $proyecto_generales_id = $request->proyecto_generales_id ?? null;

            if (empty($archivo_id)) {
                $response["message"] = "ID de archivo no especificado";
                return $response;
            }

            $insumos = new Insumos();

            if ($proyecto_generales_id) {
                // Obtener insumos del proyecto filtrados por archivo
                $resultado = $insumos->getDetalleInsumosPorArchivo($archivo_id, $proyecto_generales_id);
            } else {
                // Obtener insumos directamente de la tabla insumos
                $resultado = $insumos->getInsumosMasterPorArchivo($archivo_id);
            }

            if ($resultado['success']) {
                $response["success"] = true;
                $response["data"] = $resultado['data'];
            } else {
                $response["message"] = $resultado['message'] ?? "Error al obtener insumos";
            }
        } catch (\Exception $e) {
            $response["message"] = "Error: " . $e->getMessage();
        }

        return $response;
    }

/**
 * Obtener estadísticas de insumos por archivo en el proyecto
 */
    public function getEstadisticasInsumosPorArchivo($request)
    {
        $response = ["success" => false, "data" => []];

        try {
            $proyectos_generales_id = $request->proyectos_generales_id;

            if (empty($proyectos_generales_id)) {
                $response["message"] = "ID de proyecto no especificado";
                return $response;
            }

            $insumos = new Insumos();
            $resultado = $insumos->getInsumosAgrupadosPorArchivo($proyectos_generales_id);

            if ($resultado['success']) {
                $response["success"] = true;
                $response["data"] = $resultado['data'];
            }
        } catch (\Exception $e) {
            $response["message"] = "Error: " . $e->getMessage();
        }

        return $response;
    }
}
