<?php

//require_once(__DIR__ . '/../model/src/Archivo.php');

namespace App\Controllers;

use App\Model\Archivo;

class ArchivoController
{
    public function getArchivoUploadStorage()
    {
        $archivo = new Archivo();
        return $archivo->getArchivoUploadStorage($_POST);
    }
    // Listar archivos
    /**
     * Parsear el request según el tipo de contenido
     */
    private function parseRequest($request)
    {
        error_log("=== parseRequest() ===");
        error_log("Request type: " . gettype($request));

        // Si ya es un objeto, devolverlo
        if (is_object($request)) {
            error_log("Request ya es objeto");
            return $request;
        }

        // Si es un array, convertirlo a objeto
        if (is_array($request)) {
            error_log("Request es array, convirtiendo a objeto");
            return (object)$request;
        }

        // Si viene como JSON string
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        error_log("Content-Type: $contentType");

        if (strpos($contentType, 'application/json') !== false) {
            error_log("Content-Type es JSON");
            $input = file_get_contents('php://input');
            error_log("Input raw: $input");

            $decoded = json_decode($input);
            if ($decoded) {
                error_log("JSON decodificado exitosamente");
                return $decoded;
            }
        }

        // Si viene como POST normal
        if (!empty($_POST)) {
            error_log("Usando \$_POST");
            return (object)$_POST;
        }

        error_log("Retornando request original");
        return $request;
    }


    /**
     * Listar archivos
     */
    public function listar($request)
    {
        error_log("=== Backend Controller listar() ===");
        $archivo = new Archivo();
        return $archivo->listarArchivos();
    }

    /**
     * Crear archivo
     */
    public function crear($request)
    {
        error_log("=== Backend Controller crear() ===");
        error_log("POST: " . print_r($_POST, true));
        error_log("FILES: " . print_r($_FILES, true));

        $archivo = new Archivo();
        $parsedRequest = $this->parseRequest($request);

        return $archivo->crearArchivoInsumo($parsedRequest);
    }

    /**
     * Actualizar archivo
     */
    public function actualizar($request)
    {
        error_log("=== Backend Controller actualizar() ===");
        error_log("Request original: " . print_r($request, true));
        error_log("POST: " . print_r($_POST, true));
        error_log("FILES: " . print_r($_FILES, true));

        $archivo = new Archivo();
        $parsedRequest = $this->parseRequest($request);

        error_log("Request parseado: " . print_r($parsedRequest, true));

        return $archivo->actualizarArchivoInsumo($parsedRequest);
    }

    /**
     * Eliminar archivo
     */
    public function eliminar($request)
    {
        error_log("=== Backend Controller eliminar() ===");
        error_log("Request original: " . print_r($request, true));
        error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'no definido'));

        $archivo = new Archivo();
        $parsedRequest = $this->parseRequest($request);

        error_log("Request parseado: " . print_r($parsedRequest, true));

        return $archivo->eliminarArchivoInsumo($parsedRequest);
    }

    /**
     * Obtener archivo por ID
     */
    public function obtener($request)
    {
        error_log("=== Backend Controller obtener() ===");
        error_log("Request original: " . print_r($request, true));

        $archivo = new Archivo();
        $parsedRequest = $this->parseRequest($request);

        error_log("Request parseado: " . print_r($parsedRequest, true));

        return $archivo->obtenerArchivoInsumo($parsedRequest);
    }
}
