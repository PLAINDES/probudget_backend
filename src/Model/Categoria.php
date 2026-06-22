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

            $reader = IOFactory::createReader("Xlsx");
            $reader->setReadDataOnly(true);
            $spreadsheetInfo = $reader->listWorksheetNames($tempFile);

            if (!isset($spreadsheetInfo[1]) || !isset($spreadsheetInfo[2])) {
                throw new Exception(
                    "La plantilla no tiene las hojas necesarias",
                );
            }

            $nombreHoja2 = $spreadsheetInfo[1]; // Datos iniciales
            $nombreHoja3 = $spreadsheetInfo[2]; // PROVISIONALES

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

            // CIMENTACIONES
            foreach ($request->cimentaciones ?? [] as $index => $cimentacion) {
                if ($index >= 4) {
                    break;
                }

                $fila = 5 + $index;
                $sheet->setCellValue("B{$fila}", $cimentacion["area"]);
                $sheet->setCellValue("D{$fila}", strtolower($cimentacion["tipo"]));
            }

            // ESTRUCTURAS
            $sheet->setCellValue("B11", $request->areaTechada);
            $sheet->setCellValue("B13", $request->areaEscalera);
            // COLUMNETAS Y VIGUETAS
            $sheet->setCellValue(
                "D11",
                $request->incluyeColumnetasViguetas ? "SI" : "NO",
            );

            // AMBIENTES
            $this->agregarFilasAmbiente($request->ambientes, $sheet);

            // EXTERIORES
            $this->agregarFilasExteriores($request->exteriores, $sheet);

            // Guardar temporal para forzar recálculo
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            unset($writer);

            gc_collect_cycles();

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

            exec($comando, $salida, $codigo);

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
                    ['Obras provisionales']
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

            // Leer nombre del proyecto
            $hojaResultados = $spreadsheetFinal->getSheetByName($nombreHoja3);

            if (!$hojaResultados) {
                throw new Exception(
                    "No se encontró la hoja de resultados: $nombreHoja3",
                );
            }

            $nombreProyecto = $hojaResultados->getCell("B3")->getValue();

            // GUARDAR proyecto general
            $args = (object) [
                "users_id" => $request->users_id,
                "proyecto" => $nombreProyecto,
                "categoriaId" => $request->categoriaId,
            ];

            $proyectoGeneral = new Proyectogeneral($args);
            $result = $proyectoGeneral->save();
            error_log("Proyecto general guardado: " . json_encode($result));

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            $proyectoId = $result["data"];
            error_log("id proyecto general guardado: " . $proyectoId);

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

            // Mapa para no insertar subcategoria duplicada
            $subcategoriasInsertadas = [];

            $nroOrden = 1;

            foreach ($hojasPresupuesto as $nombreHoja => $nombreSubcategoria) {
                $hojaPresupuesto = $spreadsheetFinal->getSheetByName($nombreHoja);
                if (!$hojaPresupuesto) {
                    error_log("Hoja no encontrada: $nombreHoja");
                    continue;
                }

                $nombreHojaApu = $mapaHojasApu[$nombreHoja] ?? null;
                $hojaApu = $nombreHojaApu
                    ? $spreadsheetFinal->getSheetByName($nombreHojaApu)
                    : null;

                $mapaApus = [];

                if ($hojaApu) {
                    $gruposValidos = [
                        "MANO DE OBRA" => "mo",
                        "MATERIALES"   => "mt",
                        "EQUIPOS"      => "eq",
                        "SUBCONTRATOS" => "sc",
                        "SUBPARTIDAS"  => "sp",
                    ];

                    $maxFilaApu   = $hojaApu->getHighestRow();
                    $codigoActual = null;
                    $grupoActual  = null;

                    $mapaSubpartidas = [];

                    for ($fila = 1; $fila <= $maxFilaApu; $fila++) {
                        $titulo = mb_strtoupper(
                            trim($hojaApu->getCell("R{$fila}")->getFormattedValue())
                        );

                        if ($titulo !== "PARTIDA") {
                            continue;
                        }

                        $codigoSub = trim(
                            $hojaApu->getCell("S{$fila}")->getFormattedValue()
                        );

                        if (empty($codigoSub)) {
                            continue;
                        }

                        $mapaSubpartidas[$codigoSub] = [
                            'fila_inicio' => $fila,
                        ];
                    }

                    for ($filaApu = 1; $filaApu <= $maxFilaApu; $filaApu++) {
                        $codigo = trim($hojaApu->getCell("B{$filaApu}")->getFormattedValue());

                        // ── NUEVA APU ──────────────────────────────────────────────────────────
                        if (preg_match('/^\d+(\.\d+)+$/', $codigo)) {
                            $filaRendimiento = $filaApu + 1;
                            $codigoActual    = $codigo;

                            $mapaApus[$codigoActual] = [
                                "fila"             => $filaApu,
                                "rendimiento_unid" => trim($hojaApu->getCell("B{$filaRendimiento}")->getFormattedValue()),
                                "rendimiento"      => $hojaApu->getCell("D{$filaRendimiento}")->getValue(),
                                "cu"               => $hojaApu->getCell("J{$filaApu}")->getValue(),
                                "mo"               => null,
                                "mt"               => null,
                                "eq"               => null,
                                "sc"               => null,
                                "sp"               => null,
                                "insumos"          => [],
                            ];

                            $grupoActual = null;
                            continue;
                        }

                        if (!$codigoActual) {
                            continue;
                        }

                        // ── DETECTAR GRUPO ─────────────────────────────────────────────────────
                        $valorCelda = $hojaApu
                        ->getCell("B{$filaApu}")
                        ->getFormattedValue();

                        $texto = mb_strtoupper(
                            trim(
                                preg_replace('/\s+/', ' ', $valorCelda)
                            )
                        );

                        if (isset($gruposValidos[$texto])) {
                            $grupoActual = $gruposValidos[$texto];
                            continue;
                        }

                        if (!$grupoActual) {
                            continue;
                        }

                        $valorJ   = $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("J{$filaApu}")->getValue();
                        $esNegrita = $hojaApu->getStyle("J{$filaApu}")->getFont()->getBold();

                        // ── FILA TOTAL DEL GRUPO (negrita) → cerrar grupo ─────────────────────
                        if ($esNegrita && is_numeric($valorJ)) {
                            $mapaApus[$codigoActual][$grupoActual] = $valorJ;
                            $grupoActual = null;
                            continue;
                        }

                        // ── INSUMO TIPO SUBPARTIDA ─────────────────────────────────────────────
                        if ($grupoActual === 'sp') {
                            $nombreInsumoSp = trim($hojaApu->getCell("B{$filaApu}")->getFormattedValue());

                            $codigoSubpartida = trim(
                                $hojaApu->getCell("A{$filaApu}")->getFormattedValue()
                            );

                            $precioInsumoSp = $hojaApu->getCell("I{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("I{$filaApu}")->getValue();
                            $cantidadSp     = $hojaApu->getCell("H{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("H{$filaApu}")->getValue();
                            $parcialSp      = $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("J{$filaApu}")->getValue();
                            $unidadSp       = trim($hojaApu->getCell("F{$filaApu}")->getFormattedValue());

                            if (empty($nombreInsumoSp) || !is_numeric($precioInsumoSp)) {
                                continue;
                            }

                            // Buscar bloque de subpartida a la derecha: localizar fila con "PARTIDA" en col R
                            $filaInicioSub = null;

                            if (isset($mapaSubpartidas[$codigoSubpartida])) {
                                $filaInicioSub = $mapaSubpartidas[$codigoSubpartida]['fila_inicio'];
                            }

                            $insumosSubpartida  = [];
                            $rendimientoSub     = null;
                            $rendimientoUnidSub = null;
                            $nombreSubpartida   = $nombreInsumoSp;

                            if ($filaInicioSub) {
                                $nombreSubpartida = trim(
                                    $hojaApu->getCell("T{$filaInicioSub}")->getFormattedValue()
                                );

                                $filaRendSub = $filaInicioSub + 1;
                                $filaDataSub = $filaInicioSub + 3;

                                $rendimientoSub = $hojaApu->getCell("T{$filaRendSub}")->getOldCalculatedValue()
                                    ?? $hojaApu->getCell("T{$filaRendSub}")->getValue();

                                $rendimientoUnidSub = trim(
                                    $hojaApu->getCell("S{$filaRendSub}")->getFormattedValue()
                                );

                                $grupoSubActual = null;

                                $gruposValidosSub = [
                                    "MANO DE OBRA" => "mo",
                                    "MATERIALES"   => "mt",
                                    "EQUIPOS"      => "eq",
                                    "SUBCONTRATOS" => "sc",
                                ];

                                for ($filaSub = $filaDataSub; $filaSub <= $maxFilaApu; $filaSub++) {
                                    // Nueva subpartida en R
                                    $rCheck = mb_strtoupper(trim(
                                        $hojaApu->getCell("R{$filaSub}")->getFormattedValue()
                                    ));

                                    if ($rCheck === 'PARTIDA') {
                                        break;
                                    }

                                    // Detectar grupos
                                    $valorSub = $hojaApu
                                    ->getCell("S{$filaSub}")
                                    ->getFormattedValue();

                                    $textoSub = mb_strtoupper(
                                        trim(
                                            preg_replace('/\s+/', ' ', $valorSub) ?? ''
                                        )
                                    );

                                    if (isset($gruposValidosSub[$textoSub])) {
                                        $grupoSubActual = $gruposValidosSub[$textoSub];
                                        continue;
                                    }

                                    if (!$grupoSubActual) {
                                        continue;
                                    }

                                    // Total del grupo
                                    $valorW    = $hojaApu->getCell("AA{$filaSub}")
                                        ->getOldCalculatedValue()
                                        ?? $hojaApu->getCell("AA{$filaSub}")->getValue();

                                    $esNegritaSub = $hojaApu->getStyle("AA{$filaSub}")
                                        ->getFont()
                                        ->getBold();

                                    if ($esNegritaSub && is_numeric($valorW)) {
                                        $grupoSubActual = null;
                                        continue;
                                    }

                                    // Insumo
                                    $nombreInsumoSub = trim($hojaApu->getCell("S{$filaSub}")->getFormattedValue());

                                    $precioInsumoSub = $hojaApu->getCell("Z{$filaSub}")             ->getOldCalculatedValue() ?? $hojaApu->getCell("Z{$filaSub}")->getValue();

                                    if (empty($nombreInsumoSub) || !is_numeric($precioInsumoSub)) {
                                        continue;
                                    }

                                    $unidadInsumoSub = trim($hojaApu->getCell("W{$filaSub}")->getFormattedValue());

                                    $cuadrillaInsumoSub = $hojaApu->getCell("X{$filaSub}")->getValue();

                                    $cantidadInsumoSub = $hojaApu->getCell("Y{$filaSub}")->getOldCalculatedValue()
                                            ?? $hojaApu->getCell("Y{$filaSub}")->getValue();

                                    $parcialInsumoSub = $hojaApu->getCell("AA{$filaSub}")->getOldCalculatedValue()
                                            ?? $hojaApu->getCell("AA{$filaSub}")->getValue();

                                    $insumosSubpartida[] = [
                                        "nombre"    => $nombreInsumoSub,
                                        "unidad"    => $unidadInsumoSub,
                                        "cuadrilla" => is_numeric($cuadrillaInsumoSub) ? (float) $cuadrillaInsumoSub : null,
                                        "cantidad"  => is_numeric($cantidadInsumoSub) ? (float) $cantidadInsumoSub : null,
                                        "precio"    => (float) $precioInsumoSub,
                                        "parcial"   => is_numeric($parcialInsumoSub) ? (float) $parcialInsumoSub : null,
                                        "tipo"      => strtoupper($grupoSubActual),
                                    ];
                                }
                            }

                            $mapaApus[$codigoActual]["insumos"][] = [
                                "grupo"              => "sp",
                                "nombre"             => $nombreSubpartida,
                                "unidad"             => $unidadSp,
                                "cuadrilla"          => null,
                                "cantidad"           => is_numeric($cantidadSp) ? (float)$cantidadSp : null,
                                "precio"             => (float)$precioInsumoSp,
                                "parcial"            => is_numeric($parcialSp)  ? (float)$parcialSp  : null,
                                "tipo"               => "SP",
                                "rendimiento"        => is_numeric($rendimientoSub) ? (float)$rendimientoSub : null,
                                "rendimiento_unid"   => $rendimientoUnidSub,
                                "insumos_subpartida" => $insumosSubpartida,
                            ];

                            continue; // no caer al bloque genérico de insumo
                        }

                        // ── FILA DE INSUMO NORMAL (MO / MT / EQ / SC) ─────────────────────────
                        $nombreInsumo = trim($hojaApu->getCell("B{$filaApu}")->getFormattedValue());
                        $precioInsumo = $hojaApu->getCell("I{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("I{$filaApu}")->getValue();

                        if (empty($nombreInsumo) || !is_numeric($precioInsumo)) {
                            continue;
                        }

                        $unidadInsumo    = trim($hojaApu->getCell("F{$filaApu}")->getFormattedValue());
                        $cuadrillaInsumo = $hojaApu->getCell("G{$filaApu}")->getValue();
                        $cantidadInsumo  = $hojaApu->getCell("H{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("H{$filaApu}")->getValue();
                        $parcialInsumo   = $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("J{$filaApu}")->getValue();

                        $mapaApus[$codigoActual]["insumos"][] = [
                            "grupo"     => $grupoActual,
                            "nombre"    => $nombreInsumo,
                            "unidad"    => $unidadInsumo,
                            "cuadrilla" => is_numeric($cuadrillaInsumo) ? (float)$cuadrillaInsumo : null,
                            "cantidad"  => is_numeric($cantidadInsumo)  ? (float)$cantidadInsumo  : null,
                            "precio"    => (float)$precioInsumo,
                            "parcial"   => is_numeric($parcialInsumo)   ? (float)$parcialInsumo   : null,
                            "tipo"      => strtoupper($grupoActual),
                        ];
                    }
                }

                // ── PRESUPUESTO ────────────────────────────────────────────────────────────────
                $maxFila = $hojaPresupuesto->getHighestRow();

                $subpresupuestoId = $mapaSubcategorias[mb_strtoupper(trim($nombreSubcategoria))] ?? null;

                if (!isset($subcategoriasInsertadas[$nombreSubcategoria])) {
                    $resultSubcategoria = self::insert("subcategorias_proyecto_general", [
                        "descripcion"             => $nombreSubcategoria,
                        "orden"                   => count($subcategoriasInsertadas) + 1,
                        "subcategorias_master_id" => $subpresupuestoId,
                        "proyecto_generales_id"   => $proyectoId,
                    ]);
                    $subcategoriasInsertadas[$nombreSubcategoria] = $resultSubcategoria["lastInsertId"];
                }

                $subpresupuestoProyectoId = $subcategoriasInsertadas[$nombreSubcategoria];
                $padreNivel1 = null;
                $padreNivel2 = null;
                $fila        = 3;

                while ($fila <= $maxFila) {
                    $item        = trim($hojaPresupuesto->getCell("A{$fila}")->getValue());
                    $descripcion = trim($hojaPresupuesto->getCell("B{$fila}")->getValue());

                    if (empty($item) && empty($descripcion)) {
                        $fila++;
                        continue;
                    }
                    if (strpos($item, ".") === false) {
                        $fila++;
                        continue;
                    }

                    $partes  = array_filter(
                        explode(".", rtrim($item, ".")),
                        fn($p) => $p !== "" && is_numeric($p)
                    );
                    $niveles = count($partes);

                    if ($niveles === 0) {
                        $fila++;
                        continue;
                    }

                    $unidad  = trim($hojaPresupuesto->getCell("G{$fila}")->getValue());
                    $metrado = $hojaPresupuesto->getCell("H{$fila}")->getOldCalculatedValue();
                    $cu      = $hojaPresupuesto->getCell("I{$fila}")->getOldCalculatedValue();

                    $unidadNorm     = mb_strtoupper(trim(preg_replace("/\s+/", " ", $unidad)));
                    $unidadMedidaId = $mapaUnidades[$unidadNorm] ?? $unidadDefectoId;

                    // ── PARTIDA (tiene unidad) ─────────────────────────────────────────────────
                    if (!empty($unidad)) {
                        $apu = $mapaApus[$item] ?? null;

                        $resultPartida = self::insert("partidas_proyecto", [
                            "partida"                => $descripcion,
                            "proyectos_generales_id" => $proyectoId,
                            "unidad_medidas_id"      => $unidadMedidaId,
                            "rendimiento"            => $apu["rendimiento"] ?? null,
                            "rendimiento_unid"       => $apu["rendimiento_unid"] ?? null,
                        ]);
                        $partidaId = $resultPartida["lastInsertId"];

                        $resultPresupuesto = self::insert("presupuestos", [
                            "nro_orden"                          => $nroOrden,
                            "descripcion"                        => $descripcion,
                            "type_item"                          => 3,
                            "proyecto_generales_id"              => $proyectoId,
                            "presupuestos_proyecto_generales_id" => $padreNivel2 ?? $padreNivel1,
                            "subpresupuestos_id"                 => $subpresupuestoProyectoId,
                            "partidas_id"                        => $partidaId,
                            "metrado"                            => $metrado,
                            "cu"                                 => $cu,
                            "mo"                                 => $apu["mo"] ?? null,
                            "mt"                                 => $apu["mt"] ?? null,
                            "eq"                                 => $apu["eq"] ?? null,
                            "sc"                                 => $apu["sc"] ?? null,
                            "sp"                                 => $apu["sp"] ?? null,
                            "unidad_medidas_id"                  => $unidadMedidaId,
                        ]);
                        $presupuestoId = $resultPresupuesto["lastInsertId"];

                        self::insert("presupuestos_partida", [
                            "rendimiento"            => $apu["rendimiento"] ?? null,
                            "rendimiento_unid"       => $apu["rendimiento_unid"] ?? null,
                            "presupuestos_id"        => $presupuestoId,
                            "proyectos_generales_id" => $proyectoId,
                            "partida_id"             => $partidaId,
                            "subpartida_id"          => null,
                        ]);

                        // ── INSUMOS DE ESTA PARTIDA ────────────────────────────────────────────
                        if ($apu && !empty($apu["insumos"])) {
                            foreach ($apu["insumos"] as $insumo) {
                                $esSubpartida = strtoupper($insumo["tipo"]) === "SP";

                                $unidadInsumoNorm = mb_strtoupper(trim(preg_replace("/\s+/", " ", $insumo["unidad"])));
                                $unidadInsumoId   = $mapaUnidades[$unidadInsumoNorm] ?? $unidadDefectoId;

                                // ── SUBPARTIDA ────────────────────────────────────────────────
                                if ($esSubpartida) {
                                    // 1. Crear la partida de la subpartida
                                    $resultSubpartidaPartida = self::insert("partidas_proyecto", [
                                        "partida"                => $insumo["nombre"],
                                        "rendimiento"            => $insumo["rendimiento"] ?? null,
                                        "rendimiento_unid"       => $insumo["rendimiento_unid"] ?? null,
                                        "unidad_medidas_id"      => $unidadInsumoId,
                                        "proyectos_generales_id" => $proyectoId,
                                        "master_partida_id"      => null,
                                    ]);

                                    $subpartidaPartidaId = $resultSubpartidaPartida["lastInsertId"];

                                    // 2. Registrar la subpartida dentro del APU principal
                                    $resultApuSub = self::insert("apus_partida_presupuestos", [
                                        "cuadrilla"              => null,
                                        "cantidad"               => $insumo["cantidad"],
                                        "precio"                 => $insumo["precio"],
                                        "insumo_id"              => null,
                                        "unidad_medidas_id"      => $unidadInsumoId,
                                        "proyectos_generales_id" => $proyectoId,
                                        "presupuestos_id"        => $presupuestoId,
                                        "subpresupuestos_id"     => $subpresupuestoProyectoId,
                                        "partida_id"             => $subpartidaPartidaId,
                                        "subpartida_id"          => null,
                                        "iu"                     => null,
                                        "monomio"                => null,
                                    ]);

                                    $apuSubId = $resultApuSub["lastInsertId"];

                                    // 3. Crear la cabecera del APU hijo
                                    self::insert("presupuestos_partida", [
                                        "rendimiento"            => $insumo["rendimiento"] ?? null,
                                        "rendimiento_unid"       => $insumo["rendimiento_unid"] ?? null,
                                        "presupuestos_id"        => $presupuestoId,
                                        "proyectos_generales_id" => $proyectoId,
                                        "partida_id"             => $subpartidaPartidaId,
                                        "subpartida_id"          => $apuSubId,
                                    ]);

                                    // 4. Insertar los insumos de la subpartida
                                    foreach ($insumo["insumos_subpartida"] ?? [] as $insumoSub) {
                                        $unidadSubInsumoNorm = mb_strtoupper(
                                            trim(preg_replace("/\s+/", " ", $insumoSub["unidad"]))
                                        );

                                        $unidadSubInsumoId = $mapaUnidades[$unidadSubInsumoNorm]
                                            ?? $unidadDefectoId;

                                        $insumoMaestroSub = self::fetchObj(
                                            "SELECT *
                                             FROM insumos
                                             WHERE UPPER(TRIM(insumos)) = UPPER(TRIM(:nombre))
                                             LIMIT 1",
                                            [
                                                "nombre" => trim($insumoSub["nombre"])
                                            ]
                                        );

                                        $cacheKeySub = $proyectoId . "_"
                                            . mb_strtoupper(trim($insumoSub["nombre"]));

                                        if (!isset($mapaInsumosProyecto[$cacheKeySub])) {
                                            $resultInsumoSub = self::insert("insumos_proyecto", [
                                                "codigo"                 => $insumoMaestroSub->codigo ?? null,
                                                "iu"                     => $insumoMaestroSub->iu ?? null,
                                                "indice_unificado"       => $insumoMaestroSub->indice_unificado ?? null,
                                                "tipo"                   => $insumoSub["tipo"] ?? null,
                                                "insumos"                => $insumoSub["nombre"],
                                                "precio"                 => $insumoSub["precio"],
                                                "unidad_medidas_id"      => $unidadSubInsumoId
                                                    ?? ($insumoMaestroSub->unidad_medidas_id ?? $unidadDefectoId),
                                                "master_insumo_id"       => $insumoMaestroSub->id ?? null,
                                                "proyectos_generales_id" => $proyectoId,
                                            ]);

                                            $mapaInsumosProyecto[$cacheKeySub] =
                                                $resultInsumoSub["lastInsertId"];
                                        }

                                        self::insert("apus_partida_presupuestos", [
                                            "cuadrilla"              => $insumoSub["cuadrilla"],
                                            "cantidad"               => $insumoSub["cantidad"],
                                            "precio"                 => $insumoSub["precio"],
                                            "insumo_id"              => $mapaInsumosProyecto[$cacheKeySub],
                                            "unidad_medidas_id"      => $unidadSubInsumoId,
                                            "proyectos_generales_id" => $proyectoId,
                                            "presupuestos_id"        => $presupuestoId,
                                            "subpresupuestos_id"     => $subpresupuestoProyectoId,
                                            "partida_id"             => null,
                                            "subpartida_id"          => $apuSubId,
                                            "iu"                     => $insumoMaestroSub->iu ?? null,
                                            "monomio"                => null,
                                        ]);
                                    }

                                    continue;
                                }

                                // ── INSUMO NORMAL (MO / MT / EQ / SC) ────────────────────────
                                $insumoMaestro = self::fetchObj(
                                    "SELECT * FROM insumos WHERE UPPER(TRIM(insumos)) = UPPER(TRIM(:nombre)) LIMIT 1",
                                    ["nombre" => trim($insumo["nombre"])]
                                );

                                $cacheKey = $proyectoId . "_" . mb_strtoupper(trim($insumo["nombre"]));

                                if (!isset($mapaInsumosProyecto[$cacheKey])) {
                                    $resultInsumo = self::insert("insumos_proyecto", [
                                        "codigo"                 => $insumoMaestro->codigo ?? null,
                                        "iu"                     => $insumoMaestro->iu ?? null,
                                        "indice_unificado"       => $insumoMaestro->indice_unificado ?? null,
                                        "tipo"                   => $insumo["tipo"] ?? null,
                                        "insumos"                => $insumo["nombre"],
                                        "precio"                 => $insumo["precio"],
                                        "unidad_medidas_id"      => $unidadInsumoId ?? ($insumoMaestro->unidad_medidas_id ?? $unidadDefectoId),
                                        "master_insumo_id"       => $insumoMaestro->id ?? null,
                                        "proyectos_generales_id" => $proyectoId,
                                    ]);
                                    $mapaInsumosProyecto[$cacheKey] = $resultInsumo["lastInsertId"];
                                }

                                $insumoProyectoId = $mapaInsumosProyecto[$cacheKey];

                                // apus_partida_presupuestos para insumo normal:
                                //   presupuestos_id = presupuesto padre
                                //   subpartida_id   = null
                                //   partida_id      = null
                                //   insumo_id       = el insumo de proyecto
                                self::insert("apus_partida_presupuestos", [
                                    "cuadrilla"              => $insumo["cuadrilla"],
                                    "cantidad"               => $insumo["cantidad"],
                                    "precio"                 => $insumo["precio"],
                                    "insumo_id"              => $insumoProyectoId,
                                    "unidad_medidas_id"      => $unidadInsumoId
                                            ?? ($insumoMaestro->unidad_medidas_id
                                            ?? $unidadDefectoId),
                                    "proyectos_generales_id" => $proyectoId,
                                    "presupuestos_id"        => $presupuestoId,   // ← padre
                                    "subpresupuestos_id"     => $subpresupuestoProyectoId,
                                    "partida_id"             => null,
                                    "subpartida_id"          => null,
                                    "iu"                     => $insumoMaestro->iu ?? null,
                                    "monomio"                => null,
                                ]);
                            }
                        }
                    // ── TÍTULO nivel 1-2 ──────────────────────────────────────────────────────
                    } elseif ($niveles <= 2) {
                        $result = self::insert("presupuestos", [
                            "nro_orden"                          => $nroOrden,
                            "descripcion"                        => $descripcion,
                            "type_item"                          => 1,
                            "proyecto_generales_id"              => $proyectoId,
                            "presupuestos_proyecto_generales_id" => null,
                            "subpresupuestos_id"                 => $subpresupuestoProyectoId,
                            "partidas_id"                        => null,
                            "metrado"                            => null,
                            "cu"                                 => null,
                            "mo"                                 => null,
                            "mt"                                 => null,
                            "eq"                                 => null,
                            "sc"                                 => null,
                            "sp"                                 => null,
                            "unidad_medidas_id"                  => null,
                        ]);
                        $padreNivel1 = $result["lastInsertId"];
                        $padreNivel2 = null;

                    // ── SUBTÍTULO nivel 3+ ────────────────────────────────────────────────────
                    } else {
                        $result = self::insert("presupuestos", [
                            "nro_orden"                          => $nroOrden,
                            "descripcion"                        => $descripcion,
                            "type_item"                          => 2,
                            "proyecto_generales_id"              => $proyectoId,
                            "presupuestos_proyecto_generales_id" => $padreNivel1,
                            "subpresupuestos_id"                 => $subpresupuestoProyectoId,
                            "metrado"                            => null,
                            "cu"                                 => null,
                            "mo"                                 => null,
                            "mt"                                 => null,
                            "eq"                                 => null,
                            "sc"                                 => null,
                            "sp"                                 => null,
                            "unidad_medidas_id"                  => null,
                        ]);
                        $padreNivel2 = $result["lastInsertId"];
                    }

                    $nroOrden++;
                    $fila++;
                }
            }

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
