<?php

//require_once(__DIR__ . '/../model/src/FileLector/FileLector.php');
//require_once(__DIR__ . '/ArchivoController.php');

namespace App\Controllers;

use App\Model\FileLector\FileLector;
use App\Controllers\ArchivoController;

class FileLectorController
{
    /**
     * Extraer materiales desde un PDF subido
     * Endpoint: POST /FileLector/extractMaterialsFromPDF
     */
    public function extractMaterialsFromPDF($request)
    {
        $uploadedFile = $request->getUploadedFiles()['pdf'] ?? null;

        if (!$uploadedFile) {
            return [
                "success" => false,
                "message" => "Debe enviar un archivo PDF en el campo 'pdf'"
            ];
        }

        if ($uploadedFile->getClientMediaType() !== "application/pdf") {
            return [
                "success" => false,
                "message" => "El archivo debe ser un PDF válido"
            ];
        }

        // Guardar archivo en carpeta pública
        $folder = "public/uploads/archivos-insumos/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $filename = time() . "_" . $uploadedFile->getClientFilename();
        $filePath = $folder . $filename;

        $uploadedFile->moveTo($filePath);

        // Procesar con FileLector OPTIMIZADO
        try {
            $lector = new FileLector();
            $result = $lector->extractMaterialPrices($filePath);

            return [
                "success" => $result['success'],
                "file" => $filename,
                "data" => $result['data'] ?? [],
                "count" => $result['count'] ?? 0,
                "message" => $result['message'] ?? 'Extracción completada',
                "optimizacion" => $result['debug'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    /**
     * 🚀 MÉTODO OPTIMIZADO: Extraer materiales de un PDF usando su ID
     * El archivo ya debe estar guardado en el servidor
     * Endpoint: POST /FileLector/extractById
     *
     * MEJORAS vs versión anterior:
     * ✅ Solo envía páginas relevantes a Gemini (70-85% reducción)
     * ✅ Maneja correctamente la respuesta estructurada de FileLector
     * ✅ Info detallada de optimización para el frontend
     * ✅ Mejor manejo de errores
     */
    public function extractById($request)
    {
        error_log("=== FileLectorController: extractById() [OPTIMIZED v2] ===");

        $response = ["success" => false, "message" => null];

        try {
            // 1. Parsear request KLEIN
            $postData = $this->parseKleinRequest($request);
            error_log("POST data parseado: " . print_r($postData, true));

            $archivoId = $postData['id'] ?? 0;

            if (empty($archivoId)) {
                throw new \Exception("ID de archivo no especificado");
            }

            error_log("ID archivo recibido: $archivoId");

            // 2. Obtener información del archivo usando ArchivoController
            $archivoController = new ArchivoController();

            // Crear un objeto simulado de request con el ID
            $mockRequest = (object)['id' => $archivoId];
            $archivoResult = $archivoController->obtener($mockRequest);

            error_log("Resultado ArchivoController: " . print_r($archivoResult, true));

            if (!$archivoResult['success']) {
                throw new \Exception($archivoResult['message'] ?? "Archivo no encontrado");
            }

            $archivo = $archivoResult['data'];

            // 3. Validar que sea PDF
            $extension = strtolower($archivo->formato ?? $archivo->extension ?? '');

            if ($extension !== 'pdf') {
                throw new \Exception("El archivo debe ser un PDF. Extensión actual: $extension");
            }

            // 4. Encontrar archivo físico
            $rutaBD = $archivo->url ?? '';

            if (empty($rutaBD)) {
                throw new \Exception("No se encontró la ruta del archivo en la base de datos");
            }

            $rutaAbsoluta = $this->encontrarArchivoFisico($rutaBD);

            if (!$rutaAbsoluta) {
                // Debug: listar archivos en directorio esperado
                $basePath = realpath(__DIR__ . '/../..');
                $dirEsperado = $basePath . '/backend/public/uploads/archivos-insumos';

                $mensajeError = "Archivo físico no encontrado.\n\n";
                $mensajeError .= "Datos:\n";
                $mensajeError .= "- Ruta BD: $rutaBD\n";
                $mensajeError .= "- Nombre: " . basename($rutaBD) . "\n";
                $mensajeError .= "- Base path: $basePath\n\n";

                if (is_dir($dirEsperado)) {
                    $archivos = array_diff(scandir($dirEsperado), ['.', '..']);
                    $mensajeError .= "Archivos en directorio:\n";
                    $mensajeError .= "- " . implode("\n- ", array_slice($archivos, 0, 10));
                } else {
                    $mensajeError .= "El directorio esperado no existe: $dirEsperado";
                }

                throw new \Exception($mensajeError);
            }

            $fileSize = filesize($rutaAbsoluta);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);
            error_log("✓ Archivo válido. Tamaño: {$fileSizeMB}MB");

            // 🚀 5. PROCESAR CON FILELECTOR OPTIMIZADO
            error_log("Iniciando extracción optimizada...");
            $lector = new FileLector();
            $resultadoExtraccion = $lector->extractMaterialPrices($rutaAbsoluta);

            // ⚠️ IMPORTANTE: FileLector ahora retorna un array estructurado:
            // [
            //   'success' => bool,
            //   'data' => array,      // Las tablas extraídas
            //   'count' => int,       // Número de tablas
            //   'message' => string,
            //   'debug' => [          // Info de optimización
            //     'total_pages' => int,
            //     'relevant_pages' => [1, 5, 10],
            //     'pages_sent_to_ai' => int
            //   ]
            // ]

            if (!$resultadoExtraccion['success']) {
                throw new \Exception($resultadoExtraccion['message'] ?? 'Error al extraer materiales');
            }

            $tablas = $resultadoExtraccion['data'] ?? [];
            $count = $resultadoExtraccion['count'] ?? 0;
            $debug = $resultadoExtraccion['debug'] ?? [];

            // 📊 Log de optimización
            if (isset($debug['total_pages']) && isset($debug['pages_sent_to_ai'])) {
                $reduccion = $debug['total_pages'] > 0
                    ? round((1 - $debug['pages_sent_to_ai'] / $debug['total_pages']) * 100, 1)
                    : 0;

                error_log("📊 OPTIMIZACIÓN APLICADA:");
                error_log("  - Páginas totales: {$debug['total_pages']}");
                error_log("  - Páginas enviadas a IA: {$debug['pages_sent_to_ai']}");
                error_log("  - Reducción: {$reduccion}%");
                error_log("  - Páginas relevantes: " . implode(', ', $debug['relevant_pages'] ?? []));
            }

            error_log("✓ Extracción exitosa. Tablas encontradas: $count");

            // 6. Retornar respuesta estructurada
            $response = [
                "success" => true,
                "data" => $tablas,
                "count" => $count,
                "message" => $resultadoExtraccion['message'],
                "archivo" => [
                    'id' => $archivo->id ?? $archivoId,
                    'nombre' => $archivo->nombre ?? $archivo->descripcion ?? 'archivo.pdf',
                    'ruta' => basename($rutaBD),
                    'tamano' => $fileSize,
                    'tamano_mb' => $fileSizeMB
                ],
                "optimizacion" => [
                    'paginas_totales' => $debug['total_pages'] ?? 0,
                    'paginas_procesadas' => $debug['pages_sent_to_ai'] ?? 0,
                    'paginas_relevantes' => $debug['relevant_pages'] ?? [],
                    'reduccion_porcentaje' => isset($debug['total_pages'], $debug['pages_sent_to_ai']) && $debug['total_pages'] > 0
                        ? round((1 - $debug['pages_sent_to_ai'] / $debug['total_pages']) * 100, 1)
                        : 0
                ]
            ];
        } catch (\Exception $e) {
            error_log("❌ ERROR en extractById: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            $response = [
                "success" => false,
                "message" => $e->getMessage(),
                "data" => [],
                "count" => 0
            ];
        }

        return $response;
    }

    /**
     * Parsear request de Klein para obtener datos POST
     * Klein usa php://input para POST JSON
     */
    private function parseKleinRequest($request)
    {
        $rawBody = file_get_contents('php://input');

        if (!empty($rawBody)) {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log("✓ Datos parseados de php://input");
                return $decoded;
            }
        }

        if (!empty($_POST)) {
            error_log("✓ Datos obtenidos de \$_POST");
            return $_POST;
        }

        if (method_exists($request, 'params')) {
            $params = $request->params();
            if (!empty($params)) {
                error_log("✓ Datos obtenidos de request->params()");
                return $params;
            }
        }

        error_log("⚠️ No se pudieron obtener datos POST");
        return [];
    }

    /**
     * Encuentra la ruta absoluta de un archivo a partir de su ruta relativa/URL de BD
     *
     * @param string $rutaBD Ruta almacenada en base de datos
     * @return string|null Ruta absoluta si existe, null si no
     */
    private function encontrarArchivoFisico($rutaBD)
    {
        error_log("=== Buscando archivo físico ===");
        error_log("Ruta BD: $rutaBD");

        // Normalizar separadores
        $rutaBD = str_replace('\\', '/', $rutaBD);
        $nombreArchivo = basename($rutaBD);

        error_log("Nombre archivo: $nombreArchivo");

        // Obtener base path del proyecto (desde controller/)
        $basePath = realpath(__DIR__ . '/../..') . '/backend';
        error_log("Base path real: $basePath");


        // Directorios comunes donde buscar
        $directoriosPosibles = [
            '/public/uploads/archivos-insumos',
            '/uploads/archivos-insumos',
            '/public/uploads',
            '/uploads',
        ];

        $rutasCompletas = [
            $basePath . '/' . ltrim($rutaBD, '/'),
            $basePath . '/public/' . ltrim($rutaBD, '/'),
            $basePath . '/public' . $rutaBD,
        ];

        foreach ($rutasCompletas as $ruta) {
            if (file_exists($ruta)) {
                error_log("✓ Encontrado (ruta completa): $ruta");
                return $ruta;
            }
        }

        foreach ($directoriosPosibles as $dir) {
            $ruta = $basePath . $dir . '/' . $nombreArchivo;
            if (file_exists($ruta)) {
                error_log("✓ Encontrado (por nombre): $ruta");
                return $ruta;
            }
        }

        $uploadDir = $basePath . '/public/uploads';
        if (is_dir($uploadDir)) {
            $subdirs = scandir($uploadDir);
            foreach ($subdirs as $subdir) {
                if ($subdir === '.' || $subdir === '..') {
                    continue;
                }

                $subdirPath = $uploadDir . '/' . $subdir;
                if (is_dir($subdirPath)) {
                    $ruta = $subdirPath . '/' . $nombreArchivo;
                    if (file_exists($ruta)) {
                        error_log("✓ Encontrado (recursivo): $ruta");
                        return $ruta;
                    }
                }
            }
        }

        error_log("❌ Archivo NO encontrado en ninguna ubicación");
        return null;
    }
}
