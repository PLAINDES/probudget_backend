<?php

//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Model\Proyectogeneral;
use Exception;

class Categoria extends Mysql
{
    public function getList()
    {
        $sql =
            "SELECT id, descripcion, icono FROM categorias WHERE deleted_at IS NULL";
        $resp["success"] = true;
        $resp["data"] = [];
        $result = self::fetchAllObj($sql);
        if ($result) {
            $resp["data"] = $result;
        }
        return $resp;
    }

    public function getSave($request)
    {
        try {
            if ($request->id) {
                $sql = "SELECT COUNT(id) FROM categorias WHERE id = :id";
                $cat = self::fetchObj($sql, ["id" => $request->id]);
                if ($cat) {
                    $update = self::update(
                        "categorias",
                        [
                            "descripcion" => $request->descripcion,
                            "icono" => $request->icono,
                            "updated_at" => date("Y-m-d H:i:s"),
                        ],
                        ["id" => $request->id],
                    );
                    $resp["success"] = true;
                    $resp["message"] = "Se ha actualizado";
                    $resp["data"] = [
                        "id" => $request->id,
                        "descripcion" => $request->descripcion,
                        "icono" => $request->icono,
                    ];
                } else {
                    $resp["success"] = false;
                    $resp["message"] = "Categoría no existe";
                }
            } else {
                $insert = self::insert("categorias", [
                    "descripcion" => $request->descripcion,
                    "icono" => $request->icono,
                    "created_at" => date("Y-m-d H:i:s"),
                ]);
                if ($insert && $insert["lastInsertId"]) {
                    $resp["success"] = true;
                    $resp["message"] = "Categoría registrada";
                    $resp["data"] = [
                        "id" => $insert["lastInsertId"],
                        "descripcion" => $request->descripcion,
                        "icono" => $request->icono,
                    ];
                } else {
                    $resp["success"] = false;
                    $resp["message"] = "Error al registrar categoría";
                }
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp["success"] = false;
            $resp["message"] = $th->getMessage();
            return $resp;
        }
    }

    public function setCategoryToBudget($request)
    {
        try {
            $sql =
                "SELECT COUNT(id) AS id FROM proyecto_generales WHERE id = :id";
            $proyectoGeneral = self::fetchObj($sql, ["id" => $request->id]);
            if ($proyectoGeneral && $proyectoGeneral->id) {
                $update = self::update(
                    "proyecto_generales",
                    [
                        "categoriaId" => $request->categoryId,
                    ],
                    ["id" => $request->id],
                );
                $resp["success"] = true;
                $resp["message"] = "Presupuesto actualizado";
                $resp["data"] = [
                    "categoriaId" => $request->categoryId,
                ];
            } else {
                $resp["success"] = false;
                $resp["message"] = "Presupuesto no existe";
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp["success"] = false;
            $resp["message"] = $th->getMessage();
            return $resp;
        }
    }

    public function getDelete($request)
    {
        try {
            $sql = 'SELECT COUNT(id) AS id FROM categorias
                    WHERE id = :id';
            $cat = self::fetchObj($sql, ["id" => $request->id]);
            if ($cat && $cat->id) {
                self::update(
                    "categorias",
                    ["deleted_at" => date("Y-m-d H:i:s")],
                    ["id" => $request->id],
                );
                $resp["success"] = true;
                $resp["message"] = "Categoría eliminada";
            } else {
                $resp["success"] = false;
                $resp["message"] = "Categoría no existe";
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp["success"] = false;
            $resp["message"] = "No se puede eliminar la categoría";
            return $resp;
        }
    }

    public function getListProyectoGeneral($categoriaId = null)
    {
        $params = [];
        $where = "WHERE deleted_at IS NULL";

        if (!empty($categoriaId)) {
            $where .= " AND categoriaId = :categoriaId";
            $params["categoriaId"] = $categoriaId;
        }

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
                {$where}
                ORDER BY id ASC";

        $result = self::fetchAllObj($sql, $params);

        return $result ?: [];
    }

    public function crearPresupuestoColegio($request)
    {
        ini_set("memory_limit", "1024M");
        set_time_limit(300);
        error_log("=== crearPresupuestoColegio ===");

        $sql = "SELECT id FROM categorias WHERE id = :id";
        $categoria = self::fetchObj($sql, ["id" => $request->categoriaId]);

        if (!$categoria) {
            return ["success" => false, "message" => "Categoría no encontrada"];
        }

        try {
            $templatePath =
                dirname(__DIR__, 2) .
                "/resources/templates/plantilla-colegios.xlsx";

            if (!file_exists($templatePath)) {
                throw new Exception("No se encontró la plantilla de colegios");
            }

            $generatedPath = dirname(__DIR__, 2) . "/storage/generated";

            if (!is_dir($generatedPath)) {
                mkdir($generatedPath, 0777, true);
            }

            $tempFile = $generatedPath . "/temp_" . time() . ".xlsx";

            if (!copy($templatePath, $tempFile)) {
                throw new Exception("No se pudo copiar la plantilla");
            }

            error_log("Copia creada: $tempFile");

            $reader = IOFactory::createReader("Xlsx");
            $reader->setReadDataOnly(true);
            $spreadsheetInfo = $reader->listWorksheetNames($tempFile);

            error_log("Hojas encontradas: " . implode(", ", $spreadsheetInfo));

            if (!isset($spreadsheetInfo[1]) || !isset($spreadsheetInfo[2])) {
                throw new Exception(
                    "La plantilla no tiene las hojas necesarias",
                );
            }

            $nombreHoja2 = $spreadsheetInfo[1]; // Datos iniciales
            $nombreHoja3 = $spreadsheetInfo[2]; // PROVISIONALES

            error_log(
                "Hoja datos: $nombreHoja2 | Hoja resultados: $nombreHoja3",
            );

            // DEFINIR MAPAS de HOJAS
            $hojasPresupuesto = [
                "PROVISIONALES" => "ESTRUCTURAS",
                "ESTRUCTURA" => "ESTRUCTURAS",
                "ARQUITECTURA" => "ARQUITECTURA",
                "SANITARIAS" => "SANITARIAS",
                "ELECTRICAS" => "INSTALACIONES ELÉCTRICAS",
                "COMUNICACIONES" => "COMUNICACIONES",
            ];

            $mapaHojasApu = [
                "PROVISIONALES" => "APU PROVISIONALES",
                "ESTRUCTURA" => "APU ESTRUCTURAS",
                "ARQUITECTURA" => "APUS ARQUITECTURA",
                "SANITARIAS" => "APUS SANITARIAS",
                "ELECTRICAS" => "APUS ELECTRICAS",
                "COMUNICACIONES" => "APUS COMUNICACIONES",
            ];

            $gruposValidos = [
                "MANO DE OBRA" => "mo",
                "MATERIALES" => "mt",
                "EQUIPOS" => "eq",
                "SUBCONTRATOS" => "sc",
                "SUBPARTIDAS" => "sp",
            ];

            $reader->setReadDataOnly(false);

            $spreadsheet = $reader->load($tempFile);

            $sheet = $spreadsheet->getSheetByName($nombreHoja2);

            if (!$sheet) {
                throw new Exception("No se encontró la hoja: $nombreHoja2");
            }

            $sheet->getProtection()->setSheet(false);

            // OBRAS PROVISIONALES
            $sheet->setCellValue("B3", $request->areaTechada);
            $sheet->setCellValue("C3", $request->areaTerreno);
            $sheet->setCellValue("D3", $request->plazoEjecucion);
            $sheet->setCellValue(
                "E3",
                $request->incluyeDemoliciones ? "SI" : "NO",
            );

            // ESTRUCTURAS
            $sheet->setCellValue("B11", $request->areaTechada);
            $sheet->setCellValue("C11", $request->areaEscalera);

            // CIMENTACIONES
            foreach ($request->cimentaciones ?? [] as $index => $cimentacion) {
                if ($index >= 4) {
                    break;
                }

                $fila = 5 + $index;
                $sheet->setCellValue("B{$fila}", $cimentacion["area"]);
                $sheet->setCellValue("D{$fila}", $cimentacion["tipo"]);
            }

            // COLUMNETAS Y VIGUETAS
            $sheet->setCellValue(
                "D10",
                $request->incluyeColumnetasViguetas ? "SI" : "NO",
            );

            // AMBIENTES
            $this->agregarFilasAmbiente($request->ambientes, $sheet);

            // EXTERIORES
            $this->agregarFilasExteriores($request->exteriores, $sheet);

            error_log("Datos escritos en hoja 2");

            // Guardar temporal para forzar recálculo
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            unset($writer);

            gc_collect_cycles();

            error_log("Excel con datos guardado: {$tempFile}");

            error_log("Recalculando fórmulas con LibreOffice...");

            $recalculadoPath = $generatedPath . "/recalculado";

            if (!is_dir($recalculadoPath)) {
                mkdir($recalculadoPath, 0777, true);
            }

            $perfilLibreOffice = sys_get_temp_dir() . "/lo_" . uniqid();
            mkdir($perfilLibreOffice, 0777, true);

            $comando = sprintf(
                "HOME=%s libreoffice --headless --nologo --nodefault --invisible --convert-to xlsx --outdir %s -env:UserInstallation=file://%s %s 2>&1",
                escapeshellarg($perfilLibreOffice),
                escapeshellarg($recalculadoPath),
                $perfilLibreOffice,
                escapeshellarg($tempFile),
            );

            error_log("COMANDO LIBREOFFICE:");
            error_log($comando);

            exec($comando, $salida, $codigo);

            error_log(print_r($salida, true));

            if ($codigo !== 0) {
                throw new Exception("LibreOffice falló");
            }

            $tempFileRecalculado = $recalculadoPath . "/" . basename($tempFile);

            if (!file_exists($tempFileRecalculado)) {
                throw new Exception("No se generó el Excel recalculado");
            }

            sleep(1);
            clearstatcache();

            // Recargar solo las hojas necesarias para lectura
            $hojasACargar = array_unique(
                array_merge(
                    [$nombreHoja3],
                    array_values($mapaHojasApu),
                    array_keys($hojasPresupuesto),
                ),
            );

            sleep(1);
            clearstatcache();

            $reader2 = IOFactory::createReader("Xlsx");
            $reader2->setReadDataOnly(true);
            $reader2->setLoadSheetsOnly($hojasACargar);

            $spreadsheetFinal = $reader2->load($tempFileRecalculado);

            $nombresFinales = [];
            foreach ($spreadsheetFinal->getAllSheets() as $s) {
                $nombresFinales[] = $s->getTitle();
            }
            error_log(
                "Hojas en spreadsheetFinal: " . implode(", ", $nombresFinales),
            );

            // Leer nombre del proyecto
            $hojaResultados = $spreadsheetFinal->getSheetByName($nombreHoja3);

            if (!$hojaResultados) {
                throw new Exception(
                    "No se encontró la hoja de resultados: $nombreHoja3",
                );
            }

            $nombreProyecto = $hojaResultados->getCell("B3")->getValue();
            error_log("Nombre proyecto leído: " . $nombreProyecto);

            // GUARDAR proyecto general
            $args = (object) [
                "users_id" => $request->users_id,
                "proyecto" => $nombreProyecto,
                "categoriaId" => $request->categoriaId,
            ];

            $proyectoGeneral = new Proyectogeneral($args);
            $result = $proyectoGeneral->save();
            $proyectoId = $result["data"];

            error_log("Proyecto guardado con id: $proyectoId");

            // Cargar catálogos
            $todasUnidades = self::fetchAllObj(
                "SELECT id, alias FROM unidad_medidas",
                [],
            );
            $todasSubcategorias = self::fetchAllObj(
                "SELECT id, descripcion FROM subcategorias",
                [],
            );

            $mapaUnidades = [];
            foreach ($todasUnidades as $u) {
                $clave = mb_strtoupper(
                    trim(preg_replace("/\s+/", " ", $u->alias)),
                );
                $mapaUnidades[$clave] = $u->id;
            }

            $unidadDefectoId = $mapaUnidades["UND"] ?? null;

            if (!$unidadDefectoId) {
                throw new Exception(
                    "No existe la unidad UND en la tabla unidad_medidas",
                );
            }

            $mapaSubcategorias = [];
            foreach ($todasSubcategorias as $s) {
                $clave = mb_strtoupper(
                    trim(preg_replace("/\s+/", " ", $s->descripcion)),
                );
                $mapaSubcategorias[$clave] = $s->id;
            }

            error_log(
                "Subcategorias cargadas: " .
                    json_encode(array_keys($mapaSubcategorias)),
            );

            // Mapa para no insertar subcategoria duplicada
            $subcategoriasInsertadas = [];

            $nroOrden = 1;

            foreach ($hojasPresupuesto as $nombreHoja => $nombreSubcategoria) {
                error_log("NOMBRE HOJA DE HOJAS PRESUPUESTO $nombreHoja");
                $hojaPresupuesto = $spreadsheetFinal->getSheetByName(
                    $nombreHoja,
                );
                if (!$hojaPresupuesto) {
                    error_log("Hoja no encontrada: $nombreHoja");
                    continue;
                }

                $nombreHojaApu = $mapaHojasApu[$nombreHoja] ?? null;
                error_log("NOMBRE HOJA APU: $nombreHojaApu");
                $hojaApu = $nombreHojaApu
                    ? $spreadsheetFinal->getSheetByName($nombreHojaApu)
                    : null;

                $mapaApus = [];

                if ($hojaApu) {
                    $gruposValidos = [
                        "MANO DE OBRA" => "mo",
                        "MATERIALES" => "mt",
                        "EQUIPOS" => "eq",
                        "SUBCONTRATOS" => "sc",
                        "SUBPARTIDAS" => "sp",
                    ];

                    $maxFilaApu = $hojaApu->getHighestRow();
                    $codigoActual = null;
                    $grupoActual = null;

                    for ($filaApu = 1; $filaApu <= $maxFilaApu; $filaApu++) {
                        $codigo = trim(
                            $hojaApu
                                ->getCell("C{$filaApu}")
                                ->getFormattedValue(),
                        );

                        // ── NUEVA APU ──────────────────────────────────────────────────
                        if (preg_match('/^\d+(\.\d+)+$/', $codigo)) {
                            $filaRendimiento = $filaApu + 1;
                            $codigoActual = $codigo;

                            $mapaApus[$codigoActual] = [
                                "fila" => $filaApu,
                                "rendimiento_unid" => trim(
                                    $hojaApu
                                        ->getCell("C{$filaRendimiento}")
                                        ->getFormattedValue(),
                                ),
                                "rendimiento" => $hojaApu
                                    ->getCell("E{$filaRendimiento}")
                                    ->getValue(),
                                "cu" => $hojaApu
                                    ->getCell("K{$filaApu}")
                                    ->getValue(),
                                "mo" => null,
                                "mt" => null,
                                "eq" => null,
                                "sc" => null,
                                "sp" => null,
                                "insumos" => [], // aquí acumula los insumos de esta APU
                            ];

                            $grupoActual = null;
                            continue;
                        }

                        if (!$codigoActual) {
                            continue;
                        }

                        // ── DETECTAR GRUPO ─────────────────────────────────────────────
                        $texto = mb_strtoupper(
                            trim(
                                preg_replace(
                                    "/\s+/",
                                    " ",
                                    $hojaApu
                                        ->getCell("C{$filaApu}")
                                        ->getFormattedValue(),
                                ),
                            ),
                        );

                        if (isset($gruposValidos[$texto])) {
                            $grupoActual = $gruposValidos[$texto];
                            continue;
                        }

                        if (!$grupoActual) {
                            continue;
                        }
                        $valorK = $hojaApu->getCell("K{$filaApu}")->getValue();
                        $esNegrita = $hojaApu
                            ->getStyle("K{$filaApu}")
                            ->getFont()
                            ->getBold();

                        // ── FILA TOTAL DEL GRUPO (negrita) → cerrar grupo ─────────────
                        if ($esNegrita && is_numeric($valorK)) {
                            $mapaApus[$codigoActual][$grupoActual] = $valorK;
                            $grupoActual = null;
                            continue;
                        }

                        // ── FILA DE INSUMO ─────────────────────────────────────────────
                        // Solo capturamos si tiene nombre en C y precio numérico en J
                        $nombreInsumo = trim(
                            $hojaApu
                                ->getCell("C{$filaApu}")
                                ->getFormattedValue(),
                        );
                        $precioInsumo = $hojaApu
                            ->getCell("J{$filaApu}")
                            ->getValue();

                        if (
                            empty($nombreInsumo) ||
                            !is_numeric($precioInsumo)
                        ) {
                            continue;
                        }
                        $unidadInsumo = trim(
                            $hojaApu
                                ->getCell("G{$filaApu}")
                                ->getFormattedValue(),
                        );
                        $cuadrillaInsumo = $hojaApu
                            ->getCell("H{$filaApu}")
                            ->getValue();
                        $cantidadInsumo = $hojaApu
                            ->getCell("I{$filaApu}")
                            ->getValue();
                        $parcialInsumo = $hojaApu
                            ->getCell("K{$filaApu}")
                            ->getValue();

                        $mapaApus[$codigoActual]["insumos"][] = [
                            "grupo" => $grupoActual,
                            "nombre" => $nombreInsumo,
                            "unidad" => $unidadInsumo,
                            "cuadrilla" => is_numeric($cuadrillaInsumo)
                                ? (float) $cuadrillaInsumo
                                : null,
                            "cantidad" => is_numeric($cantidadInsumo)
                                ? (float) $cantidadInsumo
                                : null,
                            "precio" => (float) $precioInsumo,
                            "parcial" => is_numeric($parcialInsumo)
                                ? (float) $parcialInsumo
                                : null,
                            "tipo" => strtoupper($grupoActual),
                        ];
                    }
                }

                error_log("MAPA APUS INSERTADOS: " . json_encode($mapaApus));

                // ── PRESUPUESTO ────────────────────────────────────────────────────────
                $maxFila = $hojaPresupuesto->getHighestRow();

                $subpresupuestoId =
                    $mapaSubcategorias[
                        mb_strtoupper(trim($nombreSubcategoria))
                    ] ?? null;

                if (!isset($subcategoriasInsertadas[$nombreSubcategoria])) {
                    $resultSubcategoria = self::insert(
                        "subcategorias_proyecto_general",
                        [
                            "descripcion" => $nombreSubcategoria,
                            "orden" => count($subcategoriasInsertadas) + 1,
                            "subcategorias_master_id" => $subpresupuestoId,
                            "proyecto_generales_id" => $proyectoId,
                        ],
                    );
                    $subcategoriasInsertadas[$nombreSubcategoria] =
                        $resultSubcategoria["lastInsertId"];
                    error_log(
                        "Subcategoria insertada: $nombreSubcategoria | id: {$subcategoriasInsertadas[$nombreSubcategoria]}",
                    );
                }

                $subpresupuestoProyectoId =
                    $subcategoriasInsertadas[$nombreSubcategoria];
                $padreNivel1 = null;
                $padreNivel2 = null;
                $fila = 3;

                while ($fila <= $maxFila) {
                    $item = trim(
                        $hojaPresupuesto->getCell("A{$fila}")->getValue(),
                    );
                    $descripcion = trim(
                        $hojaPresupuesto->getCell("B{$fila}")->getValue(),
                    );
                    error_log("Item: {$item} | Descripcion: {$descripcion}");

                    if (empty($item) && empty($descripcion)) {
                        $fila++;
                        continue;
                    }
                    if (strpos($item, ".") === false) {
                        $fila++;
                        continue;
                    }

                    $partes = array_filter(
                        explode(".", rtrim($item, ".")),
                        fn($p) => $p !== "" && is_numeric($p),
                    );
                    $niveles = count($partes);

                    if ($niveles === 0) {
                        $fila++;
                        continue;
                    }

                    $unidad = trim(
                        $hojaPresupuesto->getCell("H{$fila}")->getValue(),
                    );
                    $metrado =
                        $hojaPresupuesto
                            ->getCell("I{$fila}")
                            ->getOldCalculatedValue() ??
                        $hojaPresupuesto->getCell("I{$fila}")->getValue();

                    $cu =
                        $hojaPresupuesto
                            ->getCell("J{$fila}")
                            ->getOldCalculatedValue() ??
                        $hojaPresupuesto->getCell("J{$fila}")->getValue();

                    error_log("METRADO {$metrado} | CU {$cu}");

                    $unidadNorm = mb_strtoupper(
                        trim(preg_replace("/\s+/", " ", $unidad)),
                    );
                    $unidadMedidaId =
                        $mapaUnidades[$unidadNorm] ?? $unidadDefectoId;

                    if (!is_numeric($metrado) && !empty($metrado)) {
                        error_log(
                            "Metrado inválido. Hoja: {$nombreHoja}, Fila: {$fila}, Item: {$item}, Valor: {$metrado}",
                        );
                    }

                    // ── PARTIDA (tiene unidad) ─────────────────────────────────────────
                    if (!empty($unidad)) {
                        $apu = $mapaApus[$item] ?? null;

                        $resultPartida = self::insert("partidas_proyecto", [
                            "partida" => $descripcion,
                            "proyectos_generales_id" => $proyectoId,
                            "unidad_medidas_id" => $unidadMedidaId,
                            "rendimiento" => $apu["rendimiento"] ?? null,
                            "rendimiento_unid" =>
                                $apu["rendimiento_unid"] ?? null,
                        ]);
                        $partidaId = $resultPartida["lastInsertId"];

                        $resultPresupuesto = self::insert("presupuestos", [
                            "nro_orden" => $nroOrden,
                            "descripcion" => $descripcion,
                            "type_item" => 3,
                            "proyecto_generales_id" => $proyectoId,
                            "presupuestos_proyecto_generales_id" =>
                                $padreNivel2 ?? $padreNivel1,
                            "subpresupuestos_id" => $subpresupuestoProyectoId,
                            "partidas_id" => $partidaId,
                            "metrado" => $metrado,
                            "cu" => $cu,
                            "mo" => $apu["mo"] ?? null,
                            "mt" => $apu["mt"] ?? null,
                            "eq" => $apu["eq"] ?? null,
                            "sc" => $apu["sc"] ?? null,
                            "sp" => $apu["sp"] ?? null,
                            "unidad_medidas_id" => $unidadMedidaId,
                        ]);
                        $presupuestoId = $resultPresupuesto["lastInsertId"];

                        error_log(
                            "MAPA UNIDADES: " . json_encode($mapaUnidades),
                        );
                        error_log("APU: " . json_encode($apu));
                        error_log(
                            "MAPA APU INSUMOS " . json_encode($apu["insumos"]),
                        );

                        // ── INSUMOS DE ESTA PARTIDA ────────────────────────────────────
                        if ($apu && !empty($apu["insumos"])) {
                            foreach ($apu["insumos"] as $insumo) {
                                // 1) Resolver unidad_medidas_id del insumo
                                $unidadInsumoNorm = mb_strtoupper(
                                    trim(
                                        preg_replace(
                                            "/\s+/",
                                            " ",
                                            $insumo["unidad"],
                                        ),
                                    ),
                                );
                                error_log(
                                    "UNIDAD INSUMO NORM: {$unidadInsumoNorm}",
                                );
                                $unidadInsumoId =
                                    $mapaUnidades[$unidadInsumoNorm] ??
                                    $unidadDefectoId;

                                // 2) Buscar en tabla maestra "insumos" por nombre
                                $nombreBusqueda = addslashes(
                                    trim($insumo["nombre"]),
                                );
                                $insumoMaestro = self::fetchObj(
                                    "SELECT * FROM insumos WHERE UPPER(TRIM(insumos)) = UPPER(TRIM(:nombre)) LIMIT 1",
                                    ["nombre" => trim($insumo["nombre"])],
                                );

                                // 3) Construir datos para insumos_proyecto
                                //    Prioridad: Excel > maestro > null
                                error_log(
                                    "INSUMO MAESTRO UNIDAD MEDIDA: " .
                                        json_encode($insumoMaestro),
                                );
                                error_log(
                                    "UNIDAD MEDIDA INSUMOS " . $unidadInsumoId,
                                );
                                $datosInsumoProyecto = [
                                    "codigo" => $insumoMaestro->codigo ?? null,
                                    "iu" => $insumoMaestro->iu ?? null,
                                    "indice_unificado" =>
                                        $insumoMaestro->indice_unificado ??
                                        null,
                                    "tipo" => $insumo["tipo"] ?? null,
                                    "insumos" => $insumo["nombre"],
                                    "precio" => $insumo["precio"], // del Excel
                                    "unidad_medidas_id" =>
                                        $unidadInsumoId ??
                                        ($insumoMaestro->unidad_medidas_id ??
                                            $unidadDefectoId),
                                    "master_insumo_id" =>
                                        $insumoMaestro->id ?? null,
                                    "proyectos_generales_id" => $proyectoId,
                                ];

                                // 4) Cache: evitar duplicar el mismo insumo en el mismo proyecto
                                //    Clave: nombre normalizado (puedes usar master_id si existe)
                                $cacheKey =
                                    $proyectoId .
                                    "_" .
                                    mb_strtoupper(trim($insumo["nombre"]));

                                $esSubpartida = strtoupper($insumo["tipo"]) == "SP";

                                if (!isset($mapaInsumosProyecto[$cacheKey]) && !$esSubpartida) {
                                    $resultInsumo = self::insert(
                                        "insumos_proyecto",
                                        $datosInsumoProyecto,
                                    );
                                    $mapaInsumosProyecto[$cacheKey] =
                                        $resultInsumo["lastInsertId"];
                                    error_log(
                                        "insumos_proyecto insertado: {$insumo["nombre"]} | id: {$mapaInsumosProyecto[$cacheKey]}",
                                    );
                                }

                                $insumoProyectoId =
                                    $mapaInsumosProyecto[$cacheKey];

                                // 5) Insertar en apus_partida_presupuestos
                                $resultApus = self::insert("apus_partida_presupuestos", [
                                    "cuadrilla" => !$esSubpartida ? $insumo["cuadrilla"] : null,
                                    "cantidad" => $insumo["cantidad"],
                                    "precio" => $insumo["precio"],
                                    "insumo_id" => !$esSubpartida ? $insumoProyectoId : null,
                                    "unidad_medidas_id" =>
                                        $unidadInsumoId ??
                                        ($insumoMaestro->unidad_medidas_id ??
                                            $unidadDefectoId),
                                    "proyectos_generales_id" => $proyectoId,
                                    "presupuestos_id" => $presupuestoId,
                                    "subpresupuestos_id" => $subpresupuestoProyectoId,
                                    "partida_id" => $esSubpartida ? $partidaId : null,
                                    "subpartida_id" => null,
                                    "iu" => $insumoMaestro->iu ?? null,
                                    "monomio" => null,
                                ]);

                                $apusId = $resultApus["lastInsertId"];

                                // INSERTAR en presupuestos_partida
                                self::insert("presupuestos_partida", [
                                    'rendimiento' => $insumo["rendimiento"],
                                    'rendimiento_unid' => $insumo["rendimiento_unid"],
                                    'presupuestos_id' => $presupuestoId,
                                    'proyectos_generales_id' => $proyectoId,
                                    'partida_id' => null,
                                    'subpartida_id' => null
                                    //'subpartida_id' => $proyectoId,
                                    //'partida_id' => $partidaId
                                ]);

                                error_log(
                                    "apus_partida_presupuestos insertado: partida={$partidaId} insumo={$insumoProyectoId}",
                                );
                            }
                        }

                        // ── TÍTULO nivel 1-2 ──────────────────────────────────────────────
                    } elseif ($niveles <= 2) {
                        $result = self::insert("presupuestos", [
                            "nro_orden" => $nroOrden,
                            "descripcion" => $descripcion,
                            "type_item" => 1,
                            "proyecto_generales_id" => $proyectoId,
                            "presupuestos_proyecto_generales_id" => null,
                            "subpresupuestos_id" => $subpresupuestoProyectoId,
                            "partidas_id" => null,
                            "metrado" => null,
                            "cu" => null,
                            "mo" => null,
                            "mt" => null,
                            "eq" => null,
                            "sc" => null,
                            "sp" => null,
                            "unidad_medidas_id" => null,
                        ]);
                        $padreNivel1 = $result["lastInsertId"];
                        $padreNivel2 = null;

                        // ── SUBTÍTULO nivel 3+ ────────────────────────────────────────────
                    } else {
                        $result = self::insert("presupuestos", [
                            "nro_orden" => $nroOrden,
                            "descripcion" => $descripcion,
                            "type_item" => 2,
                            "proyecto_generales_id" => $proyectoId,
                            "presupuestos_proyecto_generales_id" => $padreNivel1,
                            "subpresupuestos_id" => $subpresupuestoProyectoId,
                            "partidas_id" => null,
                            "metrado" => null,
                            "cu" => null,
                            "mo" => null,
                            "mt" => null,
                            "eq" => null,
                            "sc" => null,
                            "sp" => null,
                            "unidad_medidas_id" => null,
                        ]);
                        $padreNivel2 = $result["lastInsertId"];
                    }

                    $nroOrden++;
                    $fila++;
                }
            }

            error_log("Presupuestos guardados. Total: " . ($nroOrden - 1));

            $spreadsheetFinal->disconnectWorksheets();
            unset($spreadsheetFinal);

            return [
                "success" => true,
                "message" => "Proyecto creado correctamente",
                "id" => $proyectoId,
            ];
        } catch (Exception $e) {
            error_log(
                "Error en crearPresupuestoColegio(): " . $e->getMessage(),
            );
            error_log("Traza: " . $e->getTraceAsString());
            return [
                "success" => false,
                "message" => $e->getMessage(),
            ];
        } finally {
            if ($perfilLibreOffice && is_dir($perfilLibreOffice)) {
                exec("rm -rf " . escapeshellarg($perfilLibreOffice));
            }

            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }

            if ($tempFileRecalculado && file_exists($tempFileRecalculado)) {
                unlink($tempFileRecalculado);
            }
        }
    }

    private function agregarFilasAmbiente($ambientes, $sheet)
    {
        $ambienteFilas = [
            "BIBLIOTECA" => 16,
            "LABORATORIO" => 17,
            "ALMACÉN MAT. DEP." => 18,
            "SUM" => 19,
            "MÓDULO DE CONECTIVIDAD" => 20,
            "DIRECCIÓN ADM" => 21,
            "SUBDIRECCIÓN" => 22,
            "SALA DE REUNIONES" => 23,
            "SECRETARÍA" => 24,
            "SALA DE ESPERA" => 25,
            "COORDINACIÓN ADMINISTRATIVA" => 26,
            "ARCHIVOS" => 27,
            "TALLER CREATIVO PRIM" => 28,
            "TALLER CREATIVO SEC" => 29,
            "ECONOMATO" => 30,
            "COORDINACIÓN PEDAGÓGICA" => 31,
            "TÓPICO" => 32,
            "SALA DE PROFESORES" => 33,
            "TIENDA ESCOLAR" => 34,
            "ALMACÉN GENERAL" => 35,
            "SSHH ADM - HOMBRES" => 36,
            "SSHH ADM - MUJERES" => 37,
            "SSHH INICIAL - HOMBRES" => 38,
            "SSHH INICIAL - MUJERES" => 39,
            "SSHH PRIMARIA - HOMBRES" => 40,
            "SSHH PRIMARIA - MUJERES" => 41,
            "SSHH SECUNDARIA - HOMBRES" => 42,
            "SSHH SECUNDARIA - MUJERES" => 43,
            "VESTUARIOS - HOMBRES" => 44,
            "VESTUARIOS - MUJERES" => 45,
            "COCINA INICIAL" => 46,
            "COCINA PRIM -SEC" => 47,
            "OFICINA DE BIENESTAR" => 48,
            "LACTARIO" => 49,
            "CTO ELÉCTRICO" => 50,
            "DEP. MATERIAL DEPORTIVO" => 51,
            "DEPÓSITO" => 52,
            "GUARDIANÍA" => 53,
            "CUARTO DE LIMPIEZA" => 54,
            "AULA CICLO I" => 55,
            "AULA CICLO II" => 56,
            "AULA PRIMARIA" => 57,
            "AULA SECUNDARIA" => 58,
            "AULA PSICOMOTRICIDAD" => 59,
            "AULA DE INNOVACIÓN PEDAGÓGICA PRIM" => 60,
            "AULA DE INNOVACIÓN PEDAGÓGICA SEC" => 61,
            "TALLER EPT" => 62,
            "MAESTRANZA" => 63,
            "RESIDUOS" => 64,
            "CIRCULACIÓN" => 65,
            "ESCALERA PRIM" => 66,
            "ESCALERA SEC" => 67,
            "AREA DE INGRESO" => 68,
            "#AMBIENTES" => 69,
        ];

        $normalizar = function ($str) {
            return mb_strtoupper(trim(preg_replace("/\s+/", " ", $str)));
        };

        // Normalizar las claves del mapa
        $ambienteFilasNormalizadas = array_combine(
            array_map($normalizar, array_keys($ambienteFilas)),
            array_values($ambienteFilas),
        );

        foreach ($ambientes ?? [] as $ambiente) {
            $tipo = $normalizar($ambiente["tipo"]);

            if (!isset($ambienteFilasNormalizadas[$tipo])) {
                continue;
            }

            $fila = $ambienteFilasNormalizadas[$tipo];
            $sheet->setCellValue("B{$fila}", $ambiente["cantidad"]);
            $sheet->setCellValue("C{$fila}", $ambiente["area"]);
        }
    }

    private function agregarFilasExteriores($exteriores, $sheet)
    {
        $exterioresFilas = [
            "AREAS VERDES" => 72,
            "LOSA DEPORTIVA" => 73,
            "COBERTURA LOSA DEPORTIVA" => 74,
            "PATIO DE INICIAL" => 75,
            "COBERTURA PATIO DE INICIAL" => 76,
            "ASTA DE BANDERA" => 77,
            "VEREDAS Y RAMPAS DE CONCRETO" => 78,
            "PAVIMENTO RIGIDO VEHICULAR" => 79,
            "LAMAS EN PASADIZOS" => 80,
            "ESTACIONAMIENTO DE BICICLETAS" => 81,
            "CERCO PERIMETRICO H=3.00m" => 82,
            "PORTADA DE INGRESO  (PORTON METALICO)" => 83,
        ];

        $normalizar = function ($str) {
            return mb_strtoupper(trim(preg_replace("/\s+/", " ", $str)));
        };

        // Normalizar las claves del mapa
        $exterioresFilasNormalizadas = array_combine(
            array_map($normalizar, array_keys($exterioresFilas)),
            array_values($exterioresFilas),
        );

        foreach ($exteriores ?? [] as $ext) {
            $tipo = $normalizar($ext["tipo"]);

            if (!isset($exterioresFilasNormalizadas[$tipo])) {
                continue;
            }

            $fila = $exterioresFilasNormalizadas[$tipo];
            if (
                $tipo == $normalizar("ASTA DE BANDERA") ||
                $tipo == $normalizar("ESTACIONAMIENTO DE BICICLETAS") ||
                $tipo == $normalizar("PORTADA DE INGRESO (PORTON METALICO)")
            ) {
                $sheet->setCellValue("B{$fila}", $ext["cantidad"]);
            } elseif ($tipo == $normalizar("CERCO PERIMETRICO H=3.00m")) {
                $sheet->setCellValue("B{$fila}", $ext["cantidad"]);
                $sheet->setCellValue("C{$fila}", $ext["ml"]);
            } else {
                $sheet->setCellValue("B{$fila}", $ext["cantidad"]);
                $sheet->setCellValue("C{$fila}", $ext["area"]);
            }
        }
    }
}
