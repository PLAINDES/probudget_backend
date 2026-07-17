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
                error_log("No se pudo copiar la plantilla");
                throw new Exception("No se pudo copiar la plantilla");
            }

            $reader = IOFactory::createReader("Xlsx");
            $reader->setReadDataOnly(true);
            $spreadsheetInfo = $reader->listWorksheetNames($tempFile);

            if (!isset($spreadsheetInfo[1]) || !isset($spreadsheetInfo[2]) || !isset($spreadsheetInfo[3])) {
                error_log("La plantilla no tiene las hojas necesarias");
                throw new Exception(
                    "La plantilla no tiene las hojas necesarias",
                );
            }

            $nombreHoja2 = $spreadsheetInfo[1]; // Datos iniciales
            $nombreHoja3 = $spreadsheetInfo[2]; // RESUMEN
            $nombreHoja4 = $spreadsheetInfo[3]; // PROVISIONALES

            // DEFINIR MAPAS de HOJAS
            $hojasPresupuesto = [
                "PROVISIONALES" => "ESTRUCTURAS",
                "ESTRUCTURA" => "ESTRUCTURAS",
                "ARQUITECTURA" => "ARQUITECTURA",
                "SANITARIAS" => "SANITARIAS",
                "ELECTRICAS" => "INSTALACIONES ELÉCTRICAS",
                "COMUNICACIONES" => "COMUNICACIONES",
            ];

            $mapaHojasMetrado = [
                "PROVISIONALES"  => "Metrado Provisionales",
                "ESTRUCTURA"     => "Metrado Estructuras",
                "ARQUITECTURA"   => "Metrado Arquitectura",
                "SANITARIAS"     => "Metrado Sanitarias",
                "ELECTRICAS"     => "Metrado Electricas",
                "COMUNICACIONES" => "Metrado Comunicaciones",
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
            $sheetResumen = $spreadsheet->getSheetByName($nombreHoja3);

            if (!$sheet) {
                throw new Exception("No se encontró la hoja: $nombreHoja2");
            }

            if (!$sheetResumen) {
                throw new Exception("No se encontró la hoja: $nombreHoja3");
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
            $sheet->setCellValue("B13", $request->areaEscalera);
            // COLUMNETAS Y VIGUETAS
            $sheet->setCellValue(
                "D11",
                $request->incluyeColumnetasViguetas ? "SI" : "NO",
            );

            // LIMPIAR CAMPOS ANTES DE INSERTAR
            $this->limpiarCampos($sheet);

            // CIMENTACIONES
            $this->agregarCimentaciones($request->cimentaciones, $sheet);

            // AMBIENTES
            $this->agregarFilasAmbiente($request->ambientes, $sheet);

            // EXTERIORES
            if (!empty($request->exteriores)) {
                $this->agregarFilasExteriores($request->exteriores, $sheet);
            }

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
                    [$nombreHoja4, $nombreHoja3],
                    array_values($mapaHojasApu),
                    array_values($mapaHojasMetrado),
                    array_keys($hojasPresupuesto),
                    ['Obras provisionales'],
                    ['Datos iniciales ', 'calculo r'],
                ),
            );

            sleep(1);
            clearstatcache();

            $reader2 = IOFactory::createReader("Xlsx");
            $reader2->setReadDataOnly(true);
            $reader2->setLoadSheetsOnly($hojasACargar);

            $spreadsheetFinal = $reader2->load($tempFileRecalculado);

            $todasLasHojas = $reader2->listWorksheetNames($tempFileRecalculado);
            //error_log("Hojas disponibles: " . implode(' | ', $todasLasHojas));

            $nombresFinales = [];
            foreach ($spreadsheetFinal->getAllSheets() as $s) {
                $nombresFinales[] = $s->getTitle();
            }

            // Leer nombre del proyecto
            $hojaResultados = $spreadsheetFinal->getSheetByName($nombreHoja4);
            $nombreProyecto = $hojaResultados->getCell("B3")->getValue() ?? "";

            $hojaResumen = $spreadsheetFinal->getSheetByName($nombreHoja3);

            if (!$hojaResultados) {
                throw new Exception(
                    "No se encontró la hoja de resultados: $nombreHoja4",
                );
            }

            if (!$hojaResumen) {
                throw new Exception(
                    "No se encontró la hoja de resumen: $nombreHoja3",
                );
            }

            // GUARDAR proyecto general
            $args = (object) [
                "users_id" => $request->users_id,
                "proyecto" => $request->proyecto ?? $nombreProyecto,
                "categoriaId" => $request->categoriaId,
                "cliente" => $request->cliente,
                "provincia" => $request->provincia,
                "distrito" => $request->distrito,
                "departamento" => $request->departamento,
                'jornada_laboral' => 8,
                'uso_plantilla' => true,
                'fecha_base' => date('Y-m-d H:i:s'),
            ];

            //error_log("args: " . json_encode($args));

            $proyectoGeneral = new Proyectogeneral($args);
            $result = $proyectoGeneral->save();

            //error_log("result: " . print_r($result, true));

            /* TODO: descomentar luego
            if (!$result['success']) {
                throw new Exception($result['message']);
            }*/

            $proyectoId = $result["data"];

            //error_log("proyectoId: $proyectoId");

            $this->guardarResumen($hojaResumen, $proyectoId);

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
                                "rendimiento_unid" =>
                                    trim($hojaApu->getCell("B{$filaRendimiento}")
                                    ->getFormattedValue()),
                                "rendimiento"      => $hojaApu->getCell("D{$filaRendimiento}")->getValue(),
                                "cu"               =>
                                    $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                                    ?? $hojaApu->getCell("J{$filaApu}")->getValue(),
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
                        $nombreCelda = trim($hojaApu->getCell("B{$filaApu}")->getFormattedValue());

                        $esTotalGrupo = empty($nombreCelda) && is_numeric($valorJ) && $valorJ > 0;
                        //$esNegrita = $hojaApu->getStyle("J{$filaApu}")->getFont()->getBold();

                        // ── FILA TOTAL DEL GRUPO (negrita) → cerrar grupo ─────────────────────
                        if ($esTotalGrupo) {
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

                            $precioInsumoSp =
                                $hojaApu->getCell("I{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("I{$filaApu}")->getValue();

                            $cantidadSp =
                                $hojaApu->getCell("H{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("H{$filaApu}")->getValue();

                            $parcialSp =
                                $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                                ?? $hojaApu->getCell("J{$filaApu}")->getValue();

                            $unidadSp = trim($hojaApu->getCell("F{$filaApu}")->getFormattedValue());

                            $iuSp = trim($hojaApu->getCell("K{$filaApu}")->getFormattedValue());

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

                                $rendimientoSub = $hojaApu->getCell("U{$filaRendSub}")->getOldCalculatedValue()
                                    ?? $hojaApu->getCell("U{$filaRendSub}")->getValue();

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

                                    /*$esNegritaSub = $hojaApu->getStyle("AA{$filaSub}")
                                        ->getFont()
                                        ->getBold();

                                    if ($esNegritaSub && is_numeric($valorW)) {
                                        $grupoSubActual = null;
                                        continue;
                                    }*/

                                    $nombreCeldaSub = trim($hojaApu->getCell("S{$filaSub}")->getFormattedValue());
                                    $esTotalGrupoSub = empty($nombreCeldaSub) && is_numeric($valorW) && $valorW > 0;

                                    if ($esTotalGrupoSub) {
                                        $totalesSubpartida[$grupoSubActual] = (float) $valorW;
                                        $grupoSubActual = null;
                                        continue;
                                    }

                                    // Insumo
                                    $nombreInsumoSub =
                                        trim($hojaApu->getCell("S{$filaSub}")->getFormattedValue());

                                    $precioInsumoSub =
                                        $hojaApu->getCell("Z{$filaSub}")->getOldCalculatedValue()
                                        ?? $hojaApu->getCell("Z{$filaSub}")->getValue();

                                    if (empty($nombreInsumoSub) || !is_numeric($precioInsumoSub)) {
                                        continue;
                                    }

                                    $unidadInsumoSub = trim($hojaApu->getCell("W{$filaSub}")->getFormattedValue());

                                    $cuadrillaInsumoSub = $hojaApu->getCell("X{$filaSub}")->getValue();

                                    $cantidadInsumoSub = $hojaApu->getCell("Y{$filaSub}")->getOldCalculatedValue()
                                            ?? $hojaApu->getCell("Y{$filaSub}")->getValue();

                                    $parcialInsumoSub = $hojaApu->getCell("AA{$filaSub}")->getOldCalculatedValue()
                                            ?? $hojaApu->getCell("AA{$filaSub}")->getValue();

                                    $iuApuSub = $hojaApu->getCell("AB{$filaSub}")->getOldCalculatedValue()
                                        ?? $hojaApu->getCell("AB{$filaSub}")->getValue();

                                    $insumosSubpartida[] = [
                                        "nombre"    => $nombreInsumoSub,
                                        "unidad"    => $unidadInsumoSub,
                                        "cuadrilla" => is_numeric($cuadrillaInsumoSub) ? (float) $cuadrillaInsumoSub : null,
                                        "cantidad"  => is_numeric($cantidadInsumoSub) ? (float) $cantidadInsumoSub : null,
                                        "precio"    => (float) $precioInsumoSub,
                                        "parcial"   => is_numeric($parcialInsumoSub) ? (float) $parcialInsumoSub : null,
                                        "tipo"      => strtoupper($grupoSubActual),
                                        'iu' => $iuApuSub
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
                        $precioInsumo =
                            $hojaApu->getCell("I{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("I{$filaApu}")->getValue();

                        if (empty($nombreInsumo) || !is_numeric($precioInsumo)) {
                            continue;
                        }

                        $unidadInsumo    = trim($hojaApu->getCell("F{$filaApu}")->getFormattedValue());
                        $cuadrillaInsumo =
                            $hojaApu->getCell("G{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("G{$filaApu}")->getValue();

                        $cantidadInsumo  =
                            $hojaApu->getCell("H{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("H{$filaApu}")->getValue();

                        $parcialInsumo   =
                            $hojaApu->getCell("J{$filaApu}")->getOldCalculatedValue()
                            ?? $hojaApu->getCell("J{$filaApu}")->getValue();

                        $iuInsumo        = $hojaApu->getCell("K{$filaApu}")->getValue();

                        $mapaApus[$codigoActual]["insumos"][] = [
                            "grupo"     => $grupoActual,
                            "nombre"    => $nombreInsumo,
                            "unidad"    => $unidadInsumo,
                            "cuadrilla" => is_numeric($cuadrillaInsumo) ? (float)$cuadrillaInsumo : null,
                            "cantidad"  => is_numeric($cantidadInsumo)  ? (float)$cantidadInsumo  : null,
                            "precio"    => (float)$precioInsumo,
                            "parcial"   => is_numeric($parcialInsumo)   ? (float)$parcialInsumo   : null,
                            "tipo"      => strtoupper($grupoActual),
                            'iu' => $iuInsumo
                        ];
                    }
                }

                // ── CARGAR METRADOS DE ESTA HOJA ──────────────────────────────────────────
                $mapaMetrados = [];

                $nombreHojaMetrado = $mapaHojasMetrado[$nombreHoja] ?? null;
                $hojaMetrado = $nombreHojaMetrado
                    ? $spreadsheetFinal->getSheetByName($nombreHojaMetrado)
                    : null;

                if ($hojaMetrado) {
                    $maxFilaMetrado = $hojaMetrado->getHighestRow();

                    for ($filaMet = 1; $filaMet <= $maxFilaMetrado; $filaMet++) {
                        $codigoMet = trim($hojaMetrado->getCell("A{$filaMet}")->getFormattedValue());

                        // Debe ser un código tipo "1.1.1.1" (igual que en la hoja de presupuesto)
                        if (empty($codigoMet) || strpos($codigoMet, ".") === false) {
                            continue;
                        }

                        $unidadMet = trim($hojaMetrado->getCell("G{$filaMet}")->getFormattedValue());

                        // Solo nos interesan las filas de PARTIDA (las que tienen unidad, col G)
                        // Los títulos/subtítulos de la hoja de metrado no tienen unidad → se saltan
                        if (empty($unidadMet)) {
                            continue;
                        }

                        $cantidadMet = $hojaMetrado->getCell("M{$filaMet}")->getOldCalculatedValue()
                            ?? $hojaMetrado->getCell("M{$filaMet}")->getValue();

                        $mapaMetrados[$codigoMet] = [
                            "cantidad" => is_numeric($cantidadMet) ? (float) $cantidadMet : null,
                        ];
                    }
                }

                //error_log('-- Mapa metrados: ' . json_encode($mapaMetrados));

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
                    $metrado = $hojaPresupuesto->getCell("H{$fila}")->getOldCalculatedValue()
                        ?? $hojaPresupuesto->getCell("H{$fila}")->getValue();
                    $cu      = $hojaPresupuesto->getCell("I{$fila}")->getOldCalculatedValue()
                        ?? $hojaPresupuesto->getCell("I{$fila}")->getValue();

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

                        // ── METRADO DE ESTA PARTIDA ───────────────────────────────────────────────
                        $metradoPartida = $mapaMetrados[$item] ?? null;
                        //error_log("metradoPartida: " . json_encode($metradoPartida));

                        if ($metradoPartida) {
                            self::insert("metrado_partida_presupuestos", [
                                "presupuestos_id"       => $presupuestoId,
                                "proyecto_generales_id" => $proyectoId,
                                "metrado_cantidad"      => $metradoPartida["cantidad"],
                            ]);
                        }

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
                                        "iu"                     => $insumo["iu"] ?? null,
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
                                            . mb_strtoupper(trim($insumoSub["nombre"])) . "_"
                                            . (string)$insumoSub["precio"] . "_"
                                            . mb_strtoupper(trim($insumoSub["unidad"])) . "_"
                                            . mb_strtoupper($insumoSub["tipo"] ?? '');

                                        if (!isset($mapaInsumosProyecto[$cacheKeySub])) {
                                            $resultInsumoSub = self::insert("insumos_proyecto", [
                                                "codigo"                 => $insumoMaestroSub->codigo ?? null,
                                                "iu"                     => $insumoMaestroSub->iu ?? $insumoSub["iu"],
                                                "indice_unificado"       => $insumoMaestroSub->indice_unificado
                                                    ?? $grupoActual,
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

                                $cacheKey = $proyectoId . "_"
                                            . mb_strtoupper(trim($insumo["nombre"])) . "_"
                                            . (string)$insumo["precio"] . "_"
                                            . mb_strtoupper(trim($insumo["unidad"])) . "_"
                                            . mb_strtoupper($insumo["tipo"] ?? '');

                                if (!isset($mapaInsumosProyecto[$cacheKey])) {
                                    $resultInsumo = self::insert("insumos_proyecto", [
                                        "codigo"                 => $insumoMaestro->codigo ?? null,
                                        "iu"                     => $insumoMaestro->iu ?? $insumo["iu"],
                                        "indice_unificado"       => $insumoMaestro->indice_unificado ?? $grupoActual,
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

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = preg_replace('/\s+/', ' ', $texto);
        // quitar tildes
        $from = ['Á','É','Í','Ó','Ú','À','È','Ì','Ò','Ù','Ä','Ë','Ï','Ö','Ü','Ñ'];
        $to   = ['A','E','I','O','U','A','E','I','O','U','A','E','I','O','U','N'];
        return str_replace($from, $to, $texto);
    }

    public function determinarTipoFactor(string $descripcion, array $ambientes, array $exteriores): string
    {
        $desc = $this->normalizarTexto($descripcion);

        $aulasKeys = ['AULAS CICLO I', 'AULAS CICLO II', 'AULAS PRIMARIA', 'AULAS SECUNDARIA', 'AULAS PSICOMOTRICIDAD'];
        $aulasNorm = array_map(fn($k) => $this->normalizarTexto($k), $aulasKeys);

        if (in_array($desc, $aulasNorm)) {
            return 'AULAS';
        }

        $exterioresNorm = array_combine(
            array_map(fn($k) => $this->normalizarTexto($k), array_keys($exteriores)),
            array_values($exteriores)
        );

        if (isset($exterioresNorm[$desc])) {
            return 'ESPACIOS FISICOS';
        }

        $ambientesNorm = array_combine(
            array_map(fn($k) => $this->normalizarTexto($k), array_keys($ambientes)),
            array_values($ambientes)
        );

        if (isset($ambientesNorm[$desc])) {
            return 'AMBIENTES';
        }

        return 'INFRAESTRUCTURA';
    }

    private function guardarResumen($hojaResumen, $proyectoId)
    {
        //error_log("ID RECIBIDO EN GUARDAR RESUMEN: $proyectoId");
        $exteriores = [
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
            "PORTADA DE INGRESO (PORTON METALICO)" => 83,
        ];

        $ambientes = [
            "BIBLIOTECA" => 16,
            "LABORATORIO" => 17,
            "ALMACÉN MAT. DEP." => 18,
            "SUM" => 19,
            "MÓDULO DE CONECTIVIDAD" => 20,
            "DIRECCIÓN ADM." => 21,
            "SUBDIRECCIÓN" => 22,
            "SALA DE REUNIONES" => 23,
            "SECRETARÍA" => 24,
            "ÁREA DE ESPERA" => 25,
            "COORDINACIÓN ADMINISTRATIVA" => 26,
            "ARCHIVO" => 27,
            "TALLER CREATIVO PRIM" => 28,
            "TALLER CREATIVO SEC" => 29,
            "ECONOMATO" => 30,
            "COORDINACIÓN PEDAGÓGICA" => 31,
            "TOPICO" => 32,
            "SALA DE PROFESORES" => 33,
            "TIENDA ESCOLAR" => 34,
            "ALMACÉN" => 35,
            "SSHH ADM. - HOMBRES" => 36,
            "SSHH ADM. - MUJERES" => 37,
            "SSHH INICIAL - HOMBRES" => 38,
            "SSHH INICIAL - MUJERES" => 39,
            "SSHH PRIM - HOMBRES" => 40,
            "SSHH PRIM - MUJERES" => 41,
            "SSHH SEC - HOMBRES" => 42,
            "SSHH SEC - MUJERES" => 43,
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
            "AULAS CICLO I" => 55,
            "AULAS CICLO II" => 56,
            "AULAS PRIMARIA" => 57,
            "AULAS SECUNDARIA" => 58,
            "AULAS PSICOMOTRICIDAD" => 59,
            "AULA DE INNOVACIÓN PRIM" => 60,
            "AULA DE INNOVACIÓN SEC" => 61,
            "TALLER EPT" => 62,
            "MAESTRANZA" => 63,
            "RESIDUOS" => 64,
            "CIRCULACIÓN" => 65,
            "ESCALERA PRIM" => 66,
            "ESCALERA SEC" => 67,
            "AREA DE INGRESO" => 68,
        ];

        $resumen = [];

        for ($fila = 4; $fila <= 70; $fila++) {
            $descripcion = trim((string) $hojaResumen->getCell("A{$fila}")->getCalculatedValue());
            $cantidad   = (int) $hojaResumen->getCell("B{$fila}")->getCalculatedValue();
            $area       = (float) $hojaResumen->getCell("C{$fila}")->getCalculatedValue();
            $costo      = (float) $hojaResumen->getCell("D{$fila}")->getOldCalculatedValue();
            $unidad     = trim((string) $hojaResumen->getCell("E{$fila}")->getCalculatedValue());

            // Verificar que la fila no esté vacía hasta la fila 67
            // luego dejar de verificar
            if ($fila < 67) {
                if (
                    $descripcion === '' ||
                    $cantidad <= 0 ||
                    $area <= 0 ||
                    $costo <= 0
                ) {
                    continue;
                }
            }

            $seMultiplica = strtolower($unidad) == 'm2' || strtolower($unidad) == 'ml';
            $esUnidad = strtolower($unidad) == 'und';

            $oMeta = 0;

            if ($seMultiplica) {
                $oMeta = $cantidad * $area;
            } elseif ($esUnidad) {
                if ($cantidad == 0 && $area == 0 && $costo == 0) {
                    continue;
                }
                $oMeta = $cantidad;
            } else {
                $oMeta = $area;
            }

            $resumen[] = [
                'descripcion' => $descripcion,
                'u_fisica_um' => $unidad,
                'u_fisica_meta' => $cantidad,
                'o_um' => $unidad,
                'o_meta' => $oMeta,
                'costo_precio_mercado' => $costo,
            ];
        }

        $sql = 'SELECT id FROM unidad_medidas WHERE alias = :unidad';

        foreach ($resumen as $item) {
            $unidad = self::fetchObj($sql, ['unidad' => $item['o_um']]);

            if (!$unidad) {
                $unidad = self::fetchObj($sql, ['unidad' => 'UND']);
            }

            $tipoFactor = $this->determinarTipoFactor($item['descripcion'], $ambientes, $exteriores);

            self::insert('presupuesto_resumen', [
                'descripcion'          => $item['descripcion'],
                'tipo_factor'          => 'INFRAESTRUCTURA',
                'u_fisica_um'          => $tipoFactor,
                'u_fisica_meta'        => $item['u_fisica_meta'],
                'o_um_id'              => $unidad->id,
                'o_meta'               => $item['o_meta'],
                'costo_precio_mercado' => $item['costo_precio_mercado'],
                'proyecto_generales_id' => $proyectoId,
            ]);
        }
    }

    private function agregarFilasAmbiente($ambientes, $sheet)
    {
        //error_log('Ambientes: ' . json_encode($ambientes));
        $ambienteFilas = [
            "BIBLIOTECA" => 16,
            "LABORATORIO" => 17,
            "ALMACÉN MAT. DEP." => 18,
            "SUM" => 19,
            "MÓDULO DE CONECTIVIDAD" => 20,
            "DIRECCIÓN ADM." => 21,
            "SUBDIRECCIÓN" => 22,
            "SALA DE REUNIONES" => 23,
            "SECRETARÍA" => 24,
            "ÁREA DE ESPERA" => 25,
            "COORDINACIÓN ADMINISTRATIVA" => 26,
            "ARCHIVO" => 27,
            "TALLER CREATIVO PRIM" => 28,
            "TALLER CREATIVO SEC" => 29,
            "ECONOMATO" => 30,
            "COORDINACIÓN PEDAGÓGICA" => 31,
            "TOPICO" => 32,
            "SALA DE PROFESORES" => 33,
            "TIENDA ESCOLAR" => 34,
            "ALMACÉN" => 35,
            "SSHH ADM. - HOMBRES" => 36,
            "SSHH ADM. - MUJERES" => 37,
            "SSHH INICIAL - HOMBRES" => 38,
            "SSHH INICIAL - MUJERES" => 39,
            "SSHH PRIM - HOMBRES" => 40,
            "SSHH PRIM - MUJERES" => 41,
            "SSHH SEC - HOMBRES" => 42,
            "SSHH SEC - MUJERES" => 43,
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
            "AULAS CICLO I" => 55,
            "AULAS CICLO II" => 56,
            "AULAS PRIMARIA" => 57,
            "AULAS SECUNDARIA" => 58,
            "AULAS PSICOMOTRICIDAD" => 59,
            "AULA DE INNOVACIÓN PRIM" => 60,
            "AULA DE INNOVACIÓN SEC" => 61,
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
        //error_log('exteriores' . json_encode($exteriores));
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
            "PORTADA DE INGRESO (PORTON METALICO)" => 83,
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
                $sheet->setCellValue("D{$fila}", $ext["area"]);
            } else {
                $sheet->setCellValue("B{$fila}", $ext["cantidad"]);
                $sheet->setCellValue("C{$fila}", $ext["area"]);
            }
        }
    }

    private function agregarCimentaciones($cimentaciones, $sheet)
    {
        // Mapeo de pisos a filas del Excel
        $filasCimentaciones = [
            1 => 5,
            2 => 6,
            3 => 7,
            4 => 8,
        ];

        foreach ($cimentaciones ?? [] as $cimentacion) {
            $piso = (int) $cimentacion["piso"];

            if (!isset($filasCimentaciones[$piso])) {
                continue;
            }

            $fila = $filasCimentaciones[$piso];

            $tipo = strtolower(trim($cimentacion["tipo"]));

            switch ($tipo) {
                case "zapatas y vigas de cimentación":
                    $sheet->setCellValue("B{$fila}", $cimentacion["area"]);
                    $sheet->setCellValue("C{$fila}", $piso);
                    $sheet->setCellValue("D{$fila}", $tipo);
                    break;

                case "platea de cimentación":
                    $sheet->setCellValue("E{$fila}", $cimentacion["area"]);
                    $sheet->setCellValue("F{$fila}", $piso);
                    $sheet->setCellValue("G{$fila}", $tipo);
                    break;
            }
        }
    }

    private function limpiarCampos($sheet)
    {
        // Limpiar cimentaciones (filas 5-8)
        for ($i = 5; $i <= 8; $i++) {
            $sheet->setCellValue("B{$i}", null);
            $sheet->setCellValue("E{$i}", null);
        }

        // Limpiar ambientes (filas 16-68, columnas B y C)
        for ($i = 16; $i <= 68; $i++) {
            $sheet->setCellValue("B{$i}", null);
            $sheet->setCellValue("C{$i}", null);
        }

        // Limpiar exteriores (filas 72-83, columnas B y C)
        for ($i = 72; $i <= 83; $i++) {
            $sheet->setCellValue("B{$i}", null);
            $sheet->setCellValue("C{$i}", null);
            $sheet->setCellValue("D{$i}", null);
        }
    }
}
