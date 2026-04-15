<?php

require_once(__DIR__ . '/../model/src/Proyectogeneral.php');
require_once(__DIR__ . '/../model/src/Presupuesto.php');
require_once(__DIR__ . '/../model/src/RecalculoPrespuesto.php');
require_once(__DIR__ . '/../model/src/PresupuestosTitulos.php');

class PresupuestoController
{
    public function getSave($request)
    {

        $presupuesto   = new Presupuesto($request);
        return $presupuesto->getSave();
    }

    public function getDelete($request)
    {
        $presupuesto   = new Presupuesto($request);
        return $presupuesto->getDelete();
    }

    public function getListPresupuesto($request)
    {
        $presupuesto   = new Presupuesto($request);
        return $presupuesto->getListPresupuesto();
    }

    public function getListMetrado($request)
    {
        $presupuesto   = new Presupuesto($request);
        return $presupuesto->getListMetrado();
    }

    public function getMatrix($request)
    {
        $value = [
            "id" => $request->id,
            // "partidas_id" => $request->partidas_id,
            "item_partida" => $request->item_partida,
            // "descripcion" => $request->descripcion,
            // "proyecto_generales_id" => $request->proyecto_generales_id,
            "presupuestos_proyecto_generales_id" => $request->presupuestos_proyecto_generales_id,
            "nro_orden" => $request->nro_orden,
            "tipo" => $request->tipo,
        ];

        $presupuesto   = new Presupuesto($value);
        return $presupuesto->getMatrixCutPaste();
    }

    public function getPiePresupuesto($request)
    {
        $presupuesto   = new RecalculoPrespuesto();
        return $presupuesto->getPiePresupuesto(['id' => $request->id]);
    }

    public function getListTitle($request)
    {
        $presupuestosTitulos   = new PresupuestosTitulos([]);
        return $presupuestosTitulos->getListTitle($request);
    }

    public function updateBudgetFooter($request)
    {
        $presupuestos = new Presupuesto(false);
        return $presupuestos->updateBudgetFooter($request);
    }


    public function updateDescription($request)
    {
        $presupuestos = new Presupuesto(false);
        return $presupuestos->updateDescription($request);
    }

    public function findPresupuestoByInsumos($request)
    {
        try {
            error_log("=== API findPresupuestoByInsumos ===");
            error_log("Tipo de request: " . gettype($request));
            error_log("Request completo: " . print_r($request, true));

            // ✅ SOLUCIÓN: Verificar si $request es array o necesita parsear JSON
            $data = null;

            // Caso 1: $request ya es un array (viene del método api() de Budget.php)
            if (is_array($request)) {
                error_log("✅ Request es array directo");
                $data = $request;
            }
            // Caso 2: $request es objeto con método getParsedBody()
            elseif (is_object($request) && method_exists($request, 'getParsedBody')) {
                error_log("✅ Request es objeto PSR-7, usando getParsedBody()");
                $data = $request->getParsedBody();
            }
            // Caso 3: Necesitamos leer php://input
            else {
                error_log("✅ Leyendo php://input");
                $json = file_get_contents("php://input");
                error_log("JSON raw: " . $json);
                $data = json_decode($json, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("❌ Error JSON: " . json_last_error_msg());
                    throw new \Exception("Error al decodificar JSON: " . json_last_error_msg());
                }
            }

            error_log("Data final parseada: " . print_r($data, true));

            // ✅ Validar datos requeridos
            if (empty($data) || !isset($data['term']) || !isset($data['proyecto_generales_id'])) {
                error_log("❌ ERROR: Faltan parámetros");
                error_log("Data vacía: " . (empty($data) ? 'SI' : 'NO'));
                error_log("term existe: " . (isset($data['term']) ? 'SI' : 'NO'));
                error_log("proyecto_generales_id existe: " . (isset($data['proyecto_generales_id']) ? 'SI' : 'NO'));

                return [
                'success' => false,
                'message' => 'Faltan parámetros: term y proyecto_generales_id son requeridos',
                'data' => [],
                'debug' => [
                    'received_data' => $data,
                    'request_type' => gettype($request)
                ]
                ];
            }

            // ✅ Extraer y validar parámetros
            $term = trim($data['term']);
            $proyectoId = (int)$data['proyecto_generales_id'];
            $subpresupuestoId = isset($data['subpresupuestos_id']) ? (int)$data['subpresupuestos_id'] : null;

            error_log("Parámetros procesados:");
            error_log("  - term: " . $term);
            error_log("  - proyectoId: " . $proyectoId);
            error_log("  - subpresupuestoId: " . ($subpresupuestoId ?? 'NULL'));

            // ✅ Ejecutar búsqueda
            $presupuesto = new Presupuesto(false);
            $result = $presupuesto->searchPresupuestoByTerm($term, $proyectoId, $subpresupuestoId);

            error_log("✅ Resultados encontrados: " . count($result));

            return [
            'success' => true,
            'data' => $result,
            'total' => count($result)
            ];
        } catch (\Exception $e) {
            error_log("❌ EXCEPTION en API: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());

            return [
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
            ];
        }
    }
}
