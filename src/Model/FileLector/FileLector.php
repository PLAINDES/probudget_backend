<?php

//require_once(__DIR__ . '/../../utilitarian/Gemini.php');
//require_once(__DIR__ . '/PDFExtractor.php');

namespace App\Model\FileLector;

use App\Model\Utilitarian\Gemini;
use App\Model\FileLector\PDFExtractor;

// Asegurar que Composer autoload esté disponible
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once(__DIR__ . '/../../../vendor/autoload.php');
}

// Aumentar límites de ejecución para procesar PDFs grandes
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);

class FileLector
{
    private $gemini;
    private $pdfExtractor;
    private const MAX_CHUNK_SIZE = 8000;  // REDUCIDO: 8KB por chunk para evitar truncamiento
    private const MIN_ROWS_PER_REQUEST = 50; // Máximo de filas a procesar por vez

    public function __construct()
    {
        $this->gemini = new Gemini();
        $this->pdfExtractor = new PDFExtractor();
    }

    /**
     * Extrae tablas de páginas del PDF que tengan el header "Precio de los Materiales"
     * OPTIMIZADO: Procesa en chunks pequeños para evitar respuestas truncadas
     */
    public function extractMaterialPrices(string $filePath)
    {
        try {
            // PASO 1: Identificar páginas relevantes
            $relevantPages = $this->pdfExtractor->findPagesByHeader($filePath, 'Precios de los Materiales');

            if (empty($relevantPages)) {
                return [
                    'success' => false,
                    'data' => [],
                    'count' => 0,
                    'message' => 'No se encontraron páginas con el header "Precios de los Materiales"',
                    'raw' => null,
                    'debug' => [
                        'total_pages' => $this->pdfExtractor->getTotalPages($filePath),
                        'relevant_pages' => []
                    ]
                ];
            }

            // PASO 2: Extraer y limpiar el texto
            $textoPaginas = $this->extraerYProcesarTexto($filePath, $relevantPages);

            if (empty($textoPaginas)) {
                return [
                    'success' => false,
                    'data' => [],
                    'count' => 0,
                    'message' => 'No se pudo extraer texto válido de las páginas relevantes',
                    'debug' => [
                        'total_pages' => $this->pdfExtractor->getTotalPages($filePath),
                        'relevant_pages' => $relevantPages
                    ]
                ];
            }

            // PASO 3: Dividir en chunks PEQUEÑOS y procesar
            $chunks = $this->dividirEnChunksPequenos($textoPaginas);

            error_log("🔄 Procesando " . count($chunks) . " chunk(s) con Gemini");

            $allTables = [];
            $totalRows = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                error_log("📤 Chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " - Enviando " . strlen($chunk['text']) . " chars");

                $prompt = $this->buildOptimizedPrompt($chunk);
                $rawResponse = $this->gemini->ask($prompt);

                error_log("📥 Chunk " . ($chunkIndex + 1) . " - Respuesta: " . strlen($rawResponse) . " chars");

                // Verificar si la respuesta fue truncada
                if ($this->isResponseTruncated($rawResponse)) {
                    error_log("⚠️ Chunk " . ($chunkIndex + 1) . " - Respuesta TRUNCADA, intentando procesar lo disponible");
                }

                // Parsear con reparación automática
                $parsed = $this->parseAndRepairJSON($rawResponse);

                if ($parsed !== null && isset($parsed['tables'])) {
                    $tablesCount = count($parsed['tables']);
                    $rowsInChunk = array_sum(array_map(function ($t) {
                        return count($t['rows'] ?? []);
                    }, $parsed['tables']));

                    $allTables = array_merge($allTables, $parsed['tables']);
                    $totalRows += $rowsInChunk;

                    error_log("✅ Chunk " . ($chunkIndex + 1) . " - " . $tablesCount . " tabla(s), " . $rowsInChunk . " fila(s)");
                } else {
                    error_log("❌ Chunk " . ($chunkIndex + 1) . " - No se pudo parsear");
                    error_log("📄 Respuesta: " . substr($rawResponse, 0, 300));
                }
            }

            error_log("🎉 Total procesado: " . count($allTables) . " tabla(s), " . $totalRows . " fila(s)");

            return [
                'success' => true,
                'data' => $allTables,
                'count' => count($allTables),
                'message' => count($allTables) > 0
                    ? "Se encontraron " . count($allTables) . " tabla(s) con " . $totalRows . " materiales en " . count($relevantPages) . " página(s)"
                    : "No se encontraron tablas en las páginas con el header especificado",
                'raw' => null,
                'debug' => [
                    'total_pages' => $this->pdfExtractor->getTotalPages($filePath),
                    'relevant_pages' => $relevantPages,
                    'chunks_processed' => count($chunks),
                    'total_rows' => $totalRows
                ]
            ];
        } catch (\Exception $e) {
            error_log("❌ Exception en extractMaterialPrices: " . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Error al procesar PDF: ' . $e->getMessage(),
                'raw' => null
            ];
        }
    }

    /**
     * Extrae y procesa el texto de forma más eficiente
     */
    private function extraerYProcesarTexto($filePath, $relevantPages)
    {
        $textoPaginas = [];

        foreach ($relevantPages as $pageNum) {
            $textoPage = $this->pdfExtractor->getPageText($filePath, $pageNum);
            if (!$textoPage) {
                continue;
            }

            // Limpiar el texto
            $textoLimpio = $this->limpiarTextoTabla($textoPage);

            if (!empty($textoLimpio)) {
                $textoPaginas[] = [
                    'page' => $pageNum,
                    'text' => $textoLimpio,
                    'length' => strlen($textoLimpio)
                ];
            }
        }

        $totalChars = array_sum(array_column($textoPaginas, 'length'));
        error_log("📝 Texto extraído: " . count($textoPaginas) . " páginas, ~" . round($totalChars / 1024, 1) . "KB");

        return $textoPaginas;
    }

    /**
     * Limpia el texto conservando solo lo relevante para tablas
     */
    private function limpiarTextoTabla($texto)
    {
        $lineas = explode("\n", $texto);
        $lineasRelevantes = [];
        $enTabla = false;
        $lineasSinDatos = 0;

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);

            if (strlen($lineaTrim) < 3) {
                $lineasSinDatos++;
                // Si hay muchas líneas vacías, probablemente salimos de la tabla
                if ($lineasSinDatos > 3) {
                    $enTabla = false;
                }
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            // Detectar header de tabla
            if (
                preg_match('/(?:insumo|material|descripci)/ui', $lineaLower) &&
                preg_match('/(?:und|unidad)/ui', $lineaLower) &&
                preg_match('/(?:prec|precio|p\.u)/ui', $lineaLower)
            ) {
                $enTabla = true;
                $lineasRelevantes[] = $lineaTrim;
                $lineasSinDatos = 0;
                continue;
            }

            // Si estamos en tabla, incluir líneas con datos
            if ($enTabla) {
                // Incluir líneas con precios o datos de productos
                if (
                    preg_match('/\d+[.,]\d{2}/', $lineaTrim) ||
                    (strlen($lineaTrim) > 5 && !preg_match('/^[A-Z\s]{30,}$/', $lineaTrim))
                ) {
                    $lineasRelevantes[] = $lineaTrim;
                    $lineasSinDatos = 0;
                }
            }
        }

        return implode("\n", $lineasRelevantes);
    }

    /**
     * Divide el texto en chunks PEQUEÑOS para evitar truncamiento
     */
    private function dividirEnChunksPequenos($textoPaginas)
    {
        $chunks = [];
        $currentText = "";
        $currentPages = [];

        foreach ($textoPaginas as $pagina) {
            $lineas = explode("\n", $pagina['text']);
            $tempText = "";
            $tempLines = [];

            foreach ($lineas as $linea) {
                $tempLines[] = $linea;
                $tempText = implode("\n", $tempLines);

                // Si el chunk actual + esta línea excede el límite, crear nuevo chunk
                if (strlen($currentText . "\n" . $tempText) > self::MAX_CHUNK_SIZE) {
                    if (!empty($currentText)) {
                        $chunks[] = [
                            'text' => $currentText,
                            'pages' => $currentPages
                        ];
                    }

                    $currentText = $tempText;
                    $currentPages = [$pagina['page']];
                    $tempText = "";
                    $tempLines = [];
                } else {
                    if (!in_array($pagina['page'], $currentPages)) {
                        $currentPages[] = $pagina['page'];
                    }
                }
            }

            // Agregar el resto de la página
            if (!empty($tempText)) {
                if (strlen($currentText . "\n" . $tempText) > self::MAX_CHUNK_SIZE && !empty($currentText)) {
                    $chunks[] = [
                        'text' => $currentText,
                        'pages' => $currentPages
                    ];
                    $currentText = $tempText;
                    $currentPages = [$pagina['page']];
                } else {
                    $currentText .= "\n" . $tempText;
                }
            }
        }

        // Agregar el último chunk
        if (!empty($currentText)) {
            $chunks[] = [
                'text' => $currentText,
                'pages' => $currentPages
            ];
        }

        return $chunks;
    }

    /**
     * Construye un prompt optimizado para respuestas cortas
     */
    private function buildOptimizedPrompt($chunk)
    {
        $pagesInfo = implode(", ", $chunk['pages']);

        return <<<PROMPT
Extrae la tabla de precios del siguiente texto. Responde SOLO con JSON, sin markdown.

TEXTO (Páginas {$pagesInfo}):
{$chunk['text']}

Formato (copia exacto):
{"tables":[{"page":1,"columns":["INSUMO","UND.","PREC."],"rows":[{"INSUMO":"Material X","UND.":"UND","PREC.":"10.50"}]}]}

IMPORTANTE:
- Solo JSON puro (sin ``` ni explicaciones)
- Si hay muchas filas, incluye todas las que puedas
- Si no hay tabla, devuelve: {"tables":[]}
PROMPT;
    }

    /**
     * Verifica si la respuesta de Gemini fue truncada
     */
    private function isResponseTruncated($response)
    {
        // Una respuesta truncada típicamente termina de forma abrupta
        $lastChars = substr(trim($response), -20);

        // Buscar patrones de truncamiento
        return (
            !preg_match('/\}\s*\]\s*\}\s*$/', $lastChars) && // No termina con }]}
            (preg_match('/[^}\]]\s*$/', $lastChars) || // Termina sin cerrar
             preg_match('/[",]\s*$/', $lastChars)) // Termina con coma o comilla
        );
    }

    /**
     * Parsea y repara JSON truncado o mal formado
     */
    private function parseAndRepairJSON($response)
    {
        if (empty($response)) {
            return null;
        }

        // Limpiar markdown
        $cleaned = preg_replace('/^```json\s*/i', '', $response);
        $cleaned = preg_replace('/```\s*$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        // Buscar el JSON principal
        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');

        if ($start === false || $end === false) {
            return null;
        }

        $jsonStr = substr($cleaned, $start, $end - $start + 1);

        // Intentar decodificar directamente
        $decoded = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Si falla, intentar reparar
        error_log("🔧 Intentando reparar JSON...");

        // Reparación 1: Cerrar arrays y objetos abiertos
        $repaired = $this->repairTruncatedJSON($jsonStr);
        $decoded = json_decode($repaired, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            error_log("✅ JSON reparado exitosamente");
            return $decoded;
        }

        error_log("❌ No se pudo reparar el JSON: " . json_last_error_msg());
        return null;
    }

    /**
     * Intenta reparar un JSON truncado
     */
    private function repairTruncatedJSON($json)
    {
        // Contar llaves y brackets abiertos
        $openBraces = substr_count($json, '{') - substr_count($json, '}');
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');

        // Si hay comillas abiertas en el último valor, cerrarlas
        if (preg_match('/"[^"]*$/', $json)) {
            $json .= '"';
        }

        // Cerrar objetos abiertos
        for ($i = 0; $i < $openBraces; $i++) {
            $json .= '}';
        }

        // Cerrar arrays abiertos
        for ($i = 0; $i < $openBrackets; $i++) {
            $json .= ']';
        }

        return $json;
    }

    /**
     * Transforma las tablas extraídas al formato esperado por el frontend
     */
    public function transformToMaterialList(array $tables)
    {
        $materiales = [];

        foreach ($tables as $table) {
            $rows = $table['rows'] ?? [];

            foreach ($rows as $row) {
                $material = [
                    'nombre' => $this->findColumnValue($row, ['INSUMO', 'DESCRIPCION', 'MATERIAL', 'ITEM', 'DESCRIPCIÓN']),
                    'unidad' => $this->findColumnValue($row, ['UND.', 'UND', 'UNIDAD', 'UM', 'U.M.']),
                    'precio' => $this->parsePrice($this->findColumnValue($row, ['PREC.', 'PRECIO', 'P.U.', 'PRECIO_UNITARIO', 'PRECIO UNITARIO'])),
                    'observaciones' => ''
                ];

                if (!empty($material['nombre']) && $material['precio'] > 0) {
                    $materiales[] = $material;
                }
            }
        }

        return $materiales;
    }

    private function findColumnValue(array $row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                return trim($row[$key]);
            }
        }
        return '';
    }

    private function parsePrice($value)
    {
        if (empty($value)) {
            return 0;
        }

        $cleaned = preg_replace('/[^\d.,]/', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return floatval($cleaned);
    }
}
