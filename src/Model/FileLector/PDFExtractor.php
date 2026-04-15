<?php

namespace App\Model\FileLector;

use Smalot\PdfParser\Parser as PdfParser;
use setasign\Fpdi\Fpdi;

/**
 * Clase para extraer páginas específicas de un PDF sin usar IA
 * Reduce drásticamente el tamaño del request a Gemini
 */

class PDFExtractor
{
    private $parser;
    private $tempDir;

    public function __construct()
    {
        $this->parser = new PdfParser();
        $this->tempDir = sys_get_temp_dir();
    }

/**
 * Encuentra las páginas que contienen un header específico (con DEBUG)
 *
 * @param string $filePath Ruta del PDF
 * @param string $headerText Texto a buscar en el header (case-insensitive)
 * @return array Números de página (1-indexed)
 */
    public function findPagesByHeader(string $filePath, string $headerText)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Archivo no encontrado: $filePath");
        }

        try {
            error_log("=== DEBUG: Iniciando análisis de PDF ===");
            error_log("Archivo: $filePath");
            error_log("Buscando header: $headerText");

            // 🔍 Parsear PDF
            $pdf = $this->parser->parseFile($filePath);

            // 🔎 Log de estructura general del PDF (sin imprimir miles de líneas)
            error_log("PDF parsed:\n" . print_r([
            'pages_count' => count($pdf->getPages()),
            'details'     => get_class($pdf)
            ], true));

            $pages = $pdf->getPages();
            $relevantPages = [];
            $searchText = mb_strtolower($headerText, 'UTF-8');

            // 🔁 Recorrer páginas
            foreach ($pages as $pageNum => $page) {
                error_log("---- Página " . ($pageNum + 1) . " ----");

                // Obtener texto completo de la página
                $text = $page->getText();

                // Debugear solo primeros 1000 caracteres para evitar log gigante
                $preview = mb_substr($text, 0, 1000, 'UTF-8');

                error_log("Texto extraído (primeros 1000 chars):\n" . $preview);

                // Convertir a minúsculas
                $textLower = mb_strtolower($text, 'UTF-8');

                // Leer solo primeras 500 chars (zona del header)
                $headerZone = mb_substr($textLower, 0, 500, 'UTF-8');

                error_log("HeaderZone (primeros 500 chars):\n" . $headerZone);

                // Buscar coincidencia
                if (mb_strpos($headerZone, $searchText, 0, 'UTF-8') !== false) {
                    error_log("✔ Coincidencia encontrada en página " . ($pageNum + 1));
                    $relevantPages[] = $pageNum + 1;
                } else {
                    error_log("✘ No coincide");
                }
            }

            error_log("=== Páginas relevantes ===");
            error_log(print_r($relevantPages, true));

            return $relevantPages;
        } catch (\Exception $e) {
            error_log("❌ ERROR en findPagesByHeader: " . $e->getMessage());
            throw new \Exception("Error al analizar PDF: " . $e->getMessage());
        }
    }

    /**
     * Extrae páginas específicas y crea un nuevo PDF temporal
     *
     * @param string $filePath PDF original
     * @param array $pageNumbers Números de página a extraer (1-indexed)
     * @return string Ruta del PDF temporal generado
     */
    public function extractPages(string $filePath, array $pageNumbers)
    {
        if (empty($pageNumbers)) {
            throw new \Exception("No se especificaron páginas para extraer");
        }

        if (!file_exists($filePath)) {
            throw new \Exception("Archivo no encontrado: $filePath");
        }

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);

            // Validar números de página
            $validPages = array_filter($pageNumbers, function ($p) use ($pageCount) {
                return $p > 0 && $p <= $pageCount;
            });

            if (empty($validPages)) {
                throw new \Exception("Ninguna página válida para extraer");
            }

            // Extraer cada página
            foreach ($validPages as $pageNum) {
                $templateId = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Guardar PDF temporal
            $tempFileName = 'extracted_' . uniqid() . '_' . time() . '.pdf';
            $tempPath = $this->tempDir . DIRECTORY_SEPARATOR . $tempFileName;

            $pdf->Output($tempPath, 'F');

            return $tempPath;
        } catch (\Exception $e) {
            throw new \Exception("Error al extraer páginas: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el número total de páginas de un PDF
     */
    public function getTotalPages(string $filePath)
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            return count($pages);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Extrae todo el texto de una página específica
     */
    public function getPageText(string $filePath, int $pageNumber)
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();

            if (!isset($pages[$pageNumber - 1])) {
                return null;
            }

            return $pages[$pageNumber - 1]->getText();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Limpia archivos temporales antiguos (> 1 hora)
     */
    public function cleanupOldTempFiles()
    {
        $pattern = $this->tempDir . DIRECTORY_SEPARATOR . 'extracted_*.pdf';
        $files = glob($pattern);
        $now = time();
        $maxAge = 3600; // 1 hora

        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                @unlink($file);
            }
        }
    }
}
