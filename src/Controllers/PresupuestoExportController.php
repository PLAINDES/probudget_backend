<?php

// Archivo: backend/controller/PresupuestoExportController.php

//require_once(__DIR__ . '/../model/src/integration/PresupuestoExport.php');

namespace App\Controllers;

use App\Model\Integration\PresupuestoExport;

class PresupuestoExportController
{
    public function export()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $proyecto_id = $_GET['proyecto_id'] ?? null;
            $subpresupuesto_id = $_GET['subpresupuesto_id'] ?? '1';
            $format = $_GET['format'] ?? 'hierarchy';
            $all = $_GET['all'] ?? 'false'; // NUEVO PARÁMETRO

            if (!$proyecto_id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El parámetro proyecto_id es requerido'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $request = (object)[
                'proyecto_generales_id' => $proyecto_id,
                'subpresupuestos_id' => $subpresupuesto_id
            ];

            $presupuestoExport = new PresupuestoExport($request);

            // LÓGICA PARA EXPORTAR TODO O INDIVIDUAL
            if ($all === 'true' || $all === '1') {
                // Exportar TODOS los subpresupuestos
                if ($format === 'flat') {
                    $result = $presupuestoExport->getExportJSONCompleteFlat();
                } else {
                    $result = $presupuestoExport->getExportJSONComplete();
                }
            } else {
                // Exportar UN subpresupuesto específico
                if ($format === 'flat') {
                    $result = $presupuestoExport->getExportJSONFlat();
                } else {
                    $result = $presupuestoExport->getExportJSON();
                }
            }

            if (!$result['success']) {
                http_response_code(404);
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error en el servidor: ' . $th->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function exportByParams($proyecto_id, $subpresupuesto_id = '1', $format = 'hierarchy', $all = false)
    {
        try {
            $request = (object)[
                'proyecto_generales_id' => $proyecto_id,
                'subpresupuestos_id' => $subpresupuesto_id
            ];

            $presupuestoExport = new PresupuestoExport($request);

            if ($all) {
                if ($format === 'flat') {
                    return $presupuestoExport->getExportJSONCompleteFlat();
                } else {
                    return $presupuestoExport->getExportJSONComplete();
                }
            } else {
                if ($format === 'flat') {
                    return $presupuestoExport->getExportJSONFlat();
                } else {
                    return $presupuestoExport->getExportJSON();
                }
            }
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }
}
