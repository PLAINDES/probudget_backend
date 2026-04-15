<?php

require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../src/Plan.php');
require_once(__DIR__ . '/../utilitarian/Storage.php');
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/FileLector/FileLector.php');


class Archivo extends Mysql
{
    public function getArchivoUploadStorage($args)
    {
        $rsp = ['success' => false, 'data' => null, 'message' => 'Ocurrió un error en el sistema, intentelo más tarde'];
        try {
            $file = $_FILES['file'];
            $user_id = $args['user_id'];

            if (!$file || !($file['error'] == UPLOAD_ERR_OK)) {
                throw new \Exception("No se pudo subir el archivo a la nube");
            }

            $plan = new Plan();
            $result = $plan->getValidate(['modulo' => 3, 'user_id' => $user_id, 'peso' => $file['size']]);
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            $folder = "/uploads/";
            $basepath  = __DIR__ . "/../../public";
            $fullpath  = $basepath . $folder;
            if (!is_dir($fullpath)) {
                mkdir($fullpath, 0777, true);
            }

            $name = $file['name'];
            $type = $file['type'];
            $size = $file['size'];
            $tmp_name = $file['tmp_name'];

            $diskS3     =   new Storage();
            $key        =   'probudjet/archivos/' . time() . '_' . uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION);
            $result     =   $diskS3->storeAs($tmp_name, $key, $size);

            //$path = $folder . time() . strtoupper(FG::alfanumerico()) . '-' . FG::slugify($name);
            $data = [
                'nombre'    => $name,
                'formato'   => pathinfo($name, PATHINFO_EXTENSION),
                'tipo'      => $type,
                'peso'      => $size,
                'bucket'    => 'platform-owlfiles',
                'url'       => $_ENV['URL_IMAGES'] . $key,
                'size'      => FG::getZiseConvert($size),
                'user_id'   => $user_id
            ];
            $finalpath = $basepath . $path;
            // move_uploaded_file($file['tmp_name'], "$finalpath");

            $id = self::insert('archivos', $data)["lastInsertId"];

            $file = self::fetchObj("SELECT*FROM archivos WHERE id = :id", compact('id'));

            $rsp['data'] = compact('file');
            $rsp['success'] = true;
            $rsp['message'] = 'Se subió correctamente';
        } catch (Exception $e) {
            $rsp['message'] = $e->getMessage();
        }
        return $rsp;
    }

     /**
     * Listar todos los archivos de insumos (no eliminados)
     */
    public function listarArchivos()
    {
        error_log("=== DEBUG listarArchivos() ===");
        $rsp = ['success' => false, 'data' => null, 'message' => 'Ocurrió un error en el sistema'];

        try {
            $sql = "SELECT 
                        id,
                        nombre AS descripcion,
                        nombre,
                        url,
                        tipo,
                        formato AS extension,
                        formato,
                        peso AS tamanio,
                        peso,
                        size,
                        bucket,
                        user_id,
                        created_at AS fecha_creacion,
                        created_at,
                        updated_at
                    FROM archivos 
                    WHERE deleted_at IS NULL 
                    ORDER BY created_at DESC";

            $archivos = self::fetchAllObj($sql);
            error_log("Archivos encontrados: " . count($archivos));

            $rsp['success'] = true;
            $rsp['data'] = $archivos;
            $rsp['message'] = 'Listado obtenido correctamente';
        } catch (Exception $e) {
            error_log("Error en listarArchivos: " . $e->getMessage());
            $rsp['message'] = $e->getMessage();
        }

        return $rsp;
    }

    /**
     * Crear nuevo archivo de insumo (almacenamiento local)
     */
    public function crearArchivoInsumo($request)
    {
        error_log("=== DEBUG Backend crearArchivoInsumo() ===");
        error_log("Request raw: " . print_r($request, true));
        error_log("FILES backend: " . print_r($_FILES, true));
        error_log("POST backend: " . print_r($_POST, true));

        $rsp = ['success' => false, 'data' => null, 'message' => 'Ocurrió un error en el sistema'];

        try {
            if (!isset($_FILES['archivo'])) {
                error_log("ERROR: archivo no está en \$_FILES");
                throw new \Exception("No se recibió ningún archivo");
            }

            $file = $_FILES['archivo'];
            error_log("FILE recibido: " . print_r($file, true));

            $descripcion = isset($request->descripcion) ? $request->descripcion : '';
            $user_id = isset($request->user_id) ? $request->user_id : 1;

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("Error al subir el archivo. Código: " . $file['error']);
            }

            if ($file['size'] === 0) {
                throw new \Exception("El archivo está vacío");
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                throw new \Exception("El archivo no debe superar los 10MB");
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extensionesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

            if (!in_array($extension, $extensionesPermitidas)) {
                throw new \Exception("Formato de archivo no permitido. Solo se permiten: " . implode(', ', $extensionesPermitidas));
            }

            $folder = "/uploads/archivos-insumos/";
            $basepath = __DIR__ . "/../../public";
            $fullpath = $basepath . $folder;

            if (!is_dir($fullpath)) {
                if (!mkdir($fullpath, 0777, true)) {
                    throw new \Exception("No se pudo crear el directorio de archivos");
                }
            }

            $nombreUnico = time() . '_' . uniqid() . '.' . $extension;
            $rutaDestino = $fullpath . $nombreUnico;

            error_log("tmp_name: " . $file['tmp_name']);
            error_log("Destino: " . $rutaDestino);

            if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                throw new \Exception("No se pudo guardar el archivo en el servidor");
            }

            $data = [
            'nombre'    => $descripcion,
            'formato'   => $extension,
            'tipo'      => $file['type'],
            'peso'      => $file['size'],
            'bucket'    => 'local',
            'url'       => $folder . $nombreUnico,
            'size'      => FG::getZiseConvert($file['size']),
            'embebido'  => null,
            'user_id'   => $user_id
            ];

            error_log("Insertando en BD: " . print_r($data, true));

            $resultado = self::insert('archivos', $data);
            error_log("Resultado insert: " . print_r($resultado, true));

            if (!$resultado || !isset($resultado["lastInsertId"])) {
                if (file_exists($rutaDestino)) {
                    unlink($rutaDestino);
                }
                throw new \Exception("Error al guardar en la base de datos");
            }

            $id = $resultado["lastInsertId"];

            $archivo = self::fetchObj("SELECT 
            id,
            nombre AS descripcion,
            nombre,
            url,
            tipo,
            formato AS extension,
            formato,
            peso AS tamanio,
            peso,
            size,
            bucket,
            user_id,
            created_at AS fecha_creacion
        FROM archivos WHERE id = :id", compact('id'));

            // ✅ NUEVO: Si es PDF, extraer y guardar materiales automáticamente
            $materialesInsertados = 0;
            if ($extension === 'pdf') {
                error_log("📄 PDF detectado, extrayendo materiales automáticamente...");

                try {
                    $fileLector = new FileLector();
                    $extractResult = $fileLector->extractMaterialPrices($rutaDestino);

                    if ($extractResult['success'] && !empty($extractResult['data'])) {
                        error_log("✅ Materiales extraídos: " . count($extractResult['data']) . " tabla(s)");

                        // Guardar cada material en la tabla insumos
                        $materialesInsertados = $this->guardarMaterialesEnInsumos(
                            $extractResult['data'],
                            $id,
                            $user_id
                        );

                        error_log("💾 Materiales guardados en BD: " . $materialesInsertados);
                    } else {
                        error_log("⚠️ No se encontraron materiales en el PDF: " . ($extractResult['message'] ?? 'Sin mensaje'));
                    }
                } catch (\Exception $e) {
                    // No fallar todo el proceso si falla la extracción
                    error_log("⚠️ Error al extraer materiales (no crítico): " . $e->getMessage());
                }
            }

            $rsp['data'] = $archivo;
            $rsp['success'] = true;
            $rsp['message'] = 'Archivo creado correctamente';

            // Agregar info de materiales extraídos si aplica
            if ($materialesInsertados > 0) {
                $rsp['message'] .= ". Se extrajeron y guardaron {$materialesInsertados} materiales automáticamente.";
                $rsp['materiales_insertados'] = $materialesInsertados;
            }
        } catch (Exception $e) {
            error_log("Error en crearArchivoInsumo: " . $e->getMessage());
            $rsp['message'] = $e->getMessage();
        }

        return $rsp;
    }


    /**
     * Actualizar archivo de insumo
     */
    public function actualizarArchivoInsumo($request)
    {
        error_log("=== DEBUG actualizarArchivoInsumo() ===");
        error_log("Request: " . print_r($request, true));
        error_log("POST: " . print_r($_POST, true));
        error_log("FILES: " . print_r($_FILES, true));

        $rsp = ["success" => false, "message" => null];

        try {
            $id = isset($request->id) ? $request->id : 0;
            $descripcion = isset($request->descripcion) ? $request->descripcion : '';
            $user_id = isset($request->user_id) ? $request->user_id : 1;

            error_log("ID: $id, Descripcion: $descripcion");

            if (!$id) {
                throw new \Exception("ID de archivo no especificado");
            }

            $archivoActual = self::fetchObj("SELECT * FROM archivos WHERE id = :id AND deleted_at IS NULL", compact('id'));
            error_log("Archivo actual: " . print_r($archivoActual, true));

            if (!$archivoActual) {
                throw new \Exception("Archivo no encontrado");
            }

            $data = [
                'nombre' => $descripcion,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Si hay un nuevo archivo físico
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                error_log("Hay nuevo archivo físico");
                $file = $_FILES['archivo'];

                if ($file['size'] > 10 * 1024 * 1024) {
                    throw new \Exception("El archivo no debe superar los 10MB");
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $extensionesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

                if (!in_array($extension, $extensionesPermitidas)) {
                    throw new \Exception("Formato de archivo no permitido");
                }

                // Eliminar archivo anterior si existe y es local
                if (!empty($archivoActual->url) && $archivoActual->bucket === 'local') {
                    $rutaAnterior = __DIR__ . "/../../public" . $archivoActual->url;
                    error_log("Eliminando archivo anterior: $rutaAnterior");
                    if (file_exists($rutaAnterior)) {
                        unlink($rutaAnterior);
                    }
                }

                $folder = "/uploads/archivos-insumos/";
                $basepath = __DIR__ . "/../../public";
                $fullpath = $basepath . $folder;

                if (!is_dir($fullpath)) {
                    mkdir($fullpath, 0777, true);
                }

                $nombreUnico = time() . '_' . uniqid() . '.' . $extension;
                $rutaDestino = $fullpath . $nombreUnico;

                error_log("Guardando nuevo archivo en: $rutaDestino");

                if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                    throw new \Exception("No se pudo guardar el archivo");
                }

                $data['formato'] = $extension;
                $data['tipo'] = $file['type'];
                $data['peso'] = $file['size'];
                $data['url'] = $folder . $nombreUnico;
                $data['size'] = FG::getZiseConvert($file['size']);
                $data['bucket'] = 'local';
            } else {
                error_log("Solo se actualiza descripción, sin archivo nuevo");
            }

            error_log("Datos a actualizar: " . print_r($data, true));
            self::update('archivos', $data, ['id' => $id]);

            $archivo = self::fetchObj("SELECT 
                id,
                nombre AS descripcion,
                nombre,
                url,
                tipo,
                formato AS extension,
                formato,
                peso AS tamanio,
                peso,
                size,
                bucket,
                user_id,
                created_at AS fecha_creacion,
                updated_at
            FROM archivos WHERE id = :id", compact('id'));

            error_log("Archivo actualizado: " . print_r($archivo, true));

            $rsp['data'] = $archivo;
            $rsp['success'] = true;
            $rsp['message'] = 'Archivo actualizado correctamente';
        } catch (Exception $e) {
            error_log("Error en actualizarArchivoInsumo: " . $e->getMessage());
            $rsp['message'] = $e->getMessage();
        }

        return $rsp;
    }

    /**
     * Eliminar archivo (soft delete)
     */
    public function eliminarArchivoInsumo($request)
    {
        error_log("=== DEBUG eliminarArchivoInsumo() ===");
        error_log("Request completo: " . print_r($request, true));

        $rsp = ['success' => false, 'message' => 'Ocurrió un error en el sistema'];

        try {
            // El request puede venir como objeto o como array según el framework
            $id = 0;

            if (is_object($request)) {
                $id = isset($request->id) ? $request->id : 0;
            } elseif (is_array($request)) {
                $id = isset($request['id']) ? $request['id'] : 0;
            }

            error_log("ID extraído: $id");

            if (!$id) {
                throw new \Exception("ID de archivo no especificado");
            }

            $archivo = self::fetchObj("SELECT * FROM archivos WHERE id = :id AND deleted_at IS NULL", compact('id'));
            error_log("Archivo encontrado: " . print_r($archivo, true));

            if (!$archivo) {
                throw new \Exception("Archivo no encontrado");
            }

            $data = [
                'deleted_at' => date('Y-m-d H:i:s')
            ];

            error_log("Marcando como eliminado con fecha: " . $data['deleted_at']);
            self::update('archivos', $data, ['id' => $id]);

            $rsp['success'] = true;
            $rsp['message'] = 'Archivo eliminado correctamente';
            error_log("Eliminación exitosa");
        } catch (Exception $e) {
            error_log("Error en eliminarArchivoInsumo: " . $e->getMessage());
            $rsp['message'] = $e->getMessage();
        }

        return $rsp;
    }

    /**
     * Obtener archivo por ID
     */
    public function obtenerArchivoInsumo($request)
    {
        error_log("=== DEBUG obtenerArchivoInsumo() ===");
        error_log("Request completo: " . print_r($request, true));

        $rsp = ['success' => false, 'data' => null, 'message' => 'Ocurrió un error en el sistema'];

        try {
            // El request puede venir como objeto o como array según el framework
            $id = 0;

            if (is_object($request)) {
                $id = isset($request->id) ? $request->id : 0;
            } elseif (is_array($request)) {
                $id = isset($request['id']) ? $request['id'] : 0;
            }

            error_log("ID extraído: $id");

            if (!$id) {
                throw new \Exception("ID de archivo no especificado");
            }

            $sql = "SELECT 
                        id,
                        nombre AS descripcion,
                        nombre,
                        url,
                        tipo,
                        formato AS extension,
                        formato,
                        peso AS tamanio,
                        peso,
                        size,
                        bucket,
                        user_id,
                        created_at AS fecha_creacion,
                        created_at,
                        updated_at
                    FROM archivos 
                    WHERE id = :id AND deleted_at IS NULL";

            $archivo = self::fetchObj($sql, compact('id'));
            error_log("Archivo encontrado: " . print_r($archivo, true));

            if (!$archivo) {
                throw new \Exception("Archivo no encontrado");
            }

            $rsp['success'] = true;
            $rsp['data'] = $archivo;
            $rsp['message'] = 'Archivo obtenido correctamente';
        } catch (Exception $e) {
            error_log("Error en obtenerArchivoInsumo: " . $e->getMessage());
            $rsp['message'] = $e->getMessage();
        }

        return $rsp;
    }

    private function guardarMaterialesEnInsumos($tablas, $archivoId, $userId)
    {
        $insertados = 0;

        foreach ($tablas as $tabla) {
            if (!isset($tabla['rows']) || empty($tabla['rows'])) {
                continue;
            }

            foreach ($tabla['rows'] as $row) {
                try {
                    // Extraer datos del material
                    $nombre = $this->findColumnValue($row, ['INSUMO', 'DESCRIPCION', 'MATERIAL', 'ITEM', 'DESCRIPCIÓN']);
                    $unidadStr = $this->findColumnValue($row, ['UND.', 'UND', 'UNIDAD', 'UM', 'U.M.']);
                    $precio = $this->parsePrice($this->findColumnValue($row, ['PREC.', 'PRECIO', 'P.U.', 'PRECIO_UNITARIO', 'PRECIO UNITARIO']));

                    // Validar que tenga datos mínimos
                    if (empty($nombre) || $precio <= 0) {
                        continue;
                    }

                    // Obtener unidad_medidas_id (mapear de texto a ID)
                    $unidadId = $this->getUnidadMedidaId($unidadStr);

                    // Generar código único
                    $codigo = $this->generarCodigoInsumo($nombre);

                    // Preparar datos para insertar
                    $dataInsumo = [
                    'iu' => null, // Índice unificado (opcional)
                    'indice_unificado' => null,
                    'tipo' => 'Material', // Tipo por defecto
                    'insumos' => $nombre,
                    'precio' => $precio,
                    'unidad_medidas_id' => $unidadId,
                    'importacion_id' => null,
                    'notify_id' => null,
                    'archivo_id' => $archivoId // ✅ Relacionar con el archivo

                    ];

                    // Insertar en BD
                    $result = self::insert('insumos', $dataInsumo);

                    if ($result && isset($result['lastInsertId'])) {
                        $insertados++;
                        error_log("✅ Insumo insertado: {$nombre} - ID: {$result['lastInsertId']}");
                    } else {
                        error_log("❌ Error al insertar insumo: {$nombre}");
                    }
                } catch (\Exception $e) {
                    error_log("❌ Error al procesar material: " . $e->getMessage());
                    continue;
                }
            }
        }

        return $insertados;
    }

/**
 * Encuentra el valor de una columna usando múltiples posibles nombres
 */
    private function findColumnValue(array $row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                return trim($row[$key]);
            }
        }
        return '';
    }

/**
 * Parsea un precio desde string
 */
    private function parsePrice($value)
    {
        if (empty($value)) {
            return 0;
        }

        // Remover símbolos de moneda y espacios
        $cleaned = preg_replace('/[^\d.,]/', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return floatval($cleaned);
    }

/**
 * Mapea una unidad en texto a su ID en la tabla unidad_medidas
 * Ajusta según tu BD
 */
    private function getUnidadMedidaId($unidadStr)
    {
        if (empty($unidadStr)) {
            return null;
        }

        $unidadStr = strtoupper(trim($unidadStr));

        // Mapeo común (ajusta según tu tabla unidad_medidas)
        $mapeo = [
        'PZA' => 1,
        'PIEZA' => 1,
        'UND' => 1,
        'UNIDAD' => 1,
        'KG' => 2,
        'KILOGRAMO' => 2,
        'M' => 3,
        'METRO' => 3,
        'M2' => 4,
        'M²' => 4,
        'METRO CUADRADO' => 4,
        'M3' => 5,
        'M³' => 5,
        'METRO CUBICO' => 5,
        'LT' => 6,
        'LITRO' => 6,
        'GL' => 7,
        'GALON' => 7,
        'GLN' => 7,
        'KIT' => 8,
        'JGO' => 9,
        'JUEGO' => 9,
        'CTO' => 10,
        'CIENTO' => 10,
        'MLL' => 11,
        'MILLAR' => 11,
        'BOL' => 12,
        'BOLSA' => 12
        ];

        // Buscar coincidencia
        foreach ($mapeo as $key => $id) {
            if ($unidadStr === $key || strpos($unidadStr, $key) !== false) {
                return $id;
            }
        }

        // Si no se encuentra, intentar buscar en la BD
        try {
            $result = self::fetchObj(
                "SELECT id FROM unidad_medidas WHERE UPPER(nombre) = :unidad OR UPPER(abreviatura) = :unidad LIMIT 1",
                ['unidad' => $unidadStr]
            );

            if ($result && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            error_log("Error al buscar unidad de medida: " . $e->getMessage());
        }

        // Por defecto, retornar ID de "Unidad" o null
        return 1; // Ajusta según tu BD
    }

/**
 * Genera un código único para el insumo
 */
    private function generarCodigoInsumo($nombre)
    {
        // Generar código basado en timestamp y primeras letras del nombre
        $iniciales = '';
        $palabras = explode(' ', $nombre);

        foreach ($palabras as $palabra) {
            if (!empty($palabra)) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
            }
            if (strlen($iniciales) >= 3) {
                break;
            }
        }

        // Formato: INS-XXX-TIMESTAMP
        $timestamp = time();
        $codigo = 'INS-' . $iniciales . '-' . $timestamp;

        // Verificar que no exista (poco probable con timestamp)
        $existe = self::fetchObj("SELECT id FROM insumos WHERE codigo = :codigo", ['codigo' => $codigo]);

        if ($existe) {
            // Si existe, agregar random
            $codigo .= '-' . rand(100, 999);
        }

        return $codigo;
    }
}
