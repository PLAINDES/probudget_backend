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
        $sql = 'SELECT id, descripcion, icono FROM categorias WHERE deleted_at IS NULL';
        $resp['success'] = true;
        $resp['data'] = [];
        $result = self::fetchAllObj($sql);
        if ($result) {
            $resp['data'] = $result;
        }
        return $resp;
    }

    public function getSave($request)
    {
        try {
            if ($request->id) {
                $sql = 'SELECT COUNT(id) FROM categorias WHERE id = :id';
                $cat = self::fetchObj($sql, ['id' => $request->id]);
                if ($cat) {
                    $update = self::update("categorias", [
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono,
                        'updated_at' => date("Y-m-d H:i:s")
                    ], ['id' => $request->id]);
                    $resp['success'] = true;
                    $resp['message'] = 'Se ha actualizado';
                    $resp['data'] = [
                        'id' => $request->id,
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono
                    ];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Categoría no existe';
                }
            } else {
                $insert = self::insert("categorias", [
                    'descripcion' => $request->descripcion,
                    'icono' => $request->icono,
                    'created_at' => date("Y-m-d H:i:s")
                ]);
                if ($insert && $insert["lastInsertId"]) {
                    $resp['success'] = true;
                    $resp['message'] = 'Categoría registrada';
                    $resp['data'] = [
                        'id' => $insert["lastInsertId"],
                        'descripcion' => $request->descripcion,
                        'icono' => $request->icono,
                    ];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Error al registrar categoría';
                }
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function setCategoryToBudget($request)
    {
        try {
            $sql = 'SELECT COUNT(id) AS id FROM proyecto_generales WHERE id = :id';
            $proyectoGeneral = self::fetchObj($sql, ['id' => $request->id]);
            if ($proyectoGeneral && $proyectoGeneral->id) {
                $update = self::update("proyecto_generales", [
                    'categoriaId' => $request->categoryId
                ], ['id' => $request->id]);
                $resp['success'] = true;
                $resp['message'] = 'Presupuesto actualizado';
                $resp['data'] = [
                    'categoriaId' => $request->categoryId
                ];
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Presupuesto no existe';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getDelete($request)
    {
        try {
            $sql = 'SELECT COUNT(id) AS id FROM categorias
                    WHERE id = :id';
            $cat = self::fetchObj($sql, ['id' => $request->id]);
            if ($cat && $cat->id) {
                self::update('categorias', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $request->id]);
                $resp['success'] = true;
                $resp['message'] = 'Categoría eliminada';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Categoría no existe';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar la categoría';
            return $resp;
        }
    }

    public function getListProyectoGeneral($categoriaId = null)
    {
        $params = [];
        $where = "WHERE deleted_at IS NULL";

        if (!empty($categoriaId)) {
            $where .= " AND categoriaId = :categoriaId";
            $params['categoriaId'] = $categoriaId;
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
        error_log("=== crearPresupuestoColegio ===");

        error_log("request: " . print_r($request, true));

        $sql = 'SELECT id FROM categorias WHERE id = :id';
        $categoria = self::fetchObj($sql, ['id' => $request->categoriaId]);

        if (!$categoria) {
            return ['success' => false, 'message' => 'Categoría no encontrada'];
        }

        try {
            $templatePath = dirname(__DIR__, 2)
                . '/resources/templates/plantilla-colegios.xlsx';

            if (!file_exists($templatePath)) {
                throw new Exception('No se encontró la plantilla de colegios');
            }

            $generatedPath = dirname(__DIR__, 2) . '/storage/generated';

            if (!is_dir($generatedPath)) {
                mkdir($generatedPath, 0777, true);
            }

            $tempFile = $generatedPath . '/temp_' . time() . '.xlsx';

            if (!copy($templatePath, $tempFile)) {
                throw new Exception('No se pudo copiar la plantilla');
            }

            error_log("Copia creada: $tempFile");

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheetInfo = $reader->listWorksheetNames($tempFile);

            error_log("Hojas encontradas: " . implode(', ', $spreadsheetInfo));

            if (!isset($spreadsheetInfo[1]) || !isset($spreadsheetInfo[2])) {
                throw new Exception('La plantilla no tiene las hojas necesarias');
            }

            $nombreHoja2 = $spreadsheetInfo[1]; // Datos iniciales
            $nombreHoja3 = $spreadsheetInfo[2]; // PROVISIONALES

            error_log("Hoja datos: $nombreHoja2 | Hoja resultados: $nombreHoja3");

            // DEFINIR AQUI, antes del primer load
            $hojasPresupuesto = [
                'PROVISIONALES'  => 'ESTRUCTURAS',
                'ESTRUCTURA'     => 'ESTRUCTURAS',
                'ARQUITECTURA'   => 'ARQUITECTURA',
                'SANITARIAS'     => 'SANITARIAS',
                'ELECTRICAS'     => 'INSTALACIONES ELÉCTRICAS',
                'COMUNICACIONES' => 'COMUNICACIONES',
            ];

            $reader->setReadDataOnly(false);
            $reader->setLoadSheetsOnly(array_unique(array_merge(
                [$nombreHoja2, $nombreHoja3],
                array_keys($hojasPresupuesto)
            )));
            $spreadsheet = $reader->load($tempFile);

            unlink($tempFile);
            error_log("Copia eliminada del disco");

            $sheet = $spreadsheet->getSheetByName($nombreHoja2);

            if (!$sheet) {
                throw new Exception("No se encontró la hoja: $nombreHoja2");
            }

            $sheet->getProtection()->setSheet(false);

            // OBRAS PROVISIONALES
            $sheet->setCellValue('B3', $request->areaTechada);
            $sheet->setCellValue('C3', $request->areaTerreno);
            $sheet->setCellValue('D3', $request->plazoEjecucion);
            $sheet->setCellValue('E3', $request->incluyeDemoliciones ? 'SI' : 'NO');

            // ESTRUCTURAS
            $sheet->setCellValue('B11', $request->areaTechada);
            $sheet->setCellValue('C11', $request->areaEscalera);

            // CIMENTACIONES
            foreach ($request->cimentaciones ?? [] as $index => $cimentacion) {
                if ($index >= 4) {
                    break;
                }

                $fila = 5 + $index;
                $sheet->setCellValue("B{$fila}", $cimentacion['area']);
                $sheet->setCellValue("D{$fila}", $cimentacion['tipo']);
            }

            // COLUMNETAS Y VIGUETAS
            $sheet->setCellValue('D10', $request->incluyeColumnetasViguetas ? 'SI' : 'NO');

            // AMBIENTES
            $this->agregarFilasAmbiente($request->ambientes, $sheet);

            // EXTERIORES
            $this->agregarFilasExteriores($request->exteriores, $sheet);

            error_log("Datos escritos en hoja 2");

            // Guardar temporal para forzar recálculo
            $tempFile2 = $generatedPath . '/temp2_' . time() . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile2);

            error_log("Temporal guardado: $tempFile2");

            // Recargar solo las hojas necesarias para lectura
            $hojasACargar = array_unique(array_merge(
                [$nombreHoja3],
                array_keys($hojasPresupuesto)
            ));

            $reader2 = IOFactory::createReader('Xlsx');
            $reader2->setReadDataOnly(true);
            $reader2->setLoadSheetsOnly($hojasACargar);
            $spreadsheetFinal = $reader2->load($tempFile2);

            unlink($tempFile2);
            error_log("Temporal eliminado");

            $nombresFinales = [];
            foreach ($spreadsheetFinal->getAllSheets() as $s) {
                $nombresFinales[] = $s->getTitle();
            }
            error_log("Hojas en spreadsheetFinal: " . implode(', ', $nombresFinales));

            // Leer nombre del proyecto
            $hojaResultados = $spreadsheetFinal->getSheetByName($nombreHoja3);

            if (!$hojaResultados) {
                throw new Exception("No se encontró la hoja de resultados: $nombreHoja3");
            }

            $nombreProyecto = $hojaResultados->getCell('B3')->getCalculatedValue();
            error_log("Nombre proyecto leído: " . $nombreProyecto);

            // GUARDAR proyecto general
            $args = (object) [
                'users_id'    => $request->users_id,
                'proyecto'    => $nombreProyecto,
                'categoriaId' => $request->categoriaId,
            ];

            $proyectoGeneral = new Proyectogeneral($args);
            $result = $proyectoGeneral->save();
            $proyectoId = $result['data'];

            error_log("Proyecto guardado con id: $proyectoId");

            // Cargar catálogos
            $todasUnidades = self::fetchAllObj('SELECT id, alias FROM unidad_medidas', []);
            $todasSubcategorias = self::fetchAllObj('SELECT id, descripcion FROM subcategorias', []);

            $mapaUnidades = [];
            foreach ($todasUnidades as $u) {
                $clave = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $u->alias)));
                $mapaUnidades[$clave] = $u->id;
            }

            $mapaSubcategorias = [];
            foreach ($todasSubcategorias as $s) {
                $clave = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $s->descripcion)));
                $mapaSubcategorias[$clave] = $s->id;
            }

            error_log("Subcategorias cargadas: " . json_encode(array_keys($mapaSubcategorias)));

            // Mapa para no insertar subcategoria duplicada
            $subcategoriasInsertadas = [];

            $nroOrden = 1;

            foreach ($hojasPresupuesto as $nombreHoja => $nombreSubcategoria) {
                $hojaPresupuesto = $spreadsheetFinal->getSheetByName($nombreHoja);

                if (!$hojaPresupuesto) {
                    error_log("Hoja no encontrada: $nombreHoja");
                    continue;
                }

                $subpresupuestoId = isset($mapaSubcategorias[$nombreSubcategoria])
                    ? $mapaSubcategorias[$nombreSubcategoria]
                    : null;

                // Insertar subcategoria solo si no se insertó antes
                if (!isset($subcategoriasInsertadas[$nombreSubcategoria])) {
                    $resultSubcategoria = self::insert('subcategorias_proyecto_general', [
                        'descripcion'             => $nombreSubcategoria,
                        'orden'                   => count($subcategoriasInsertadas) + 1,
                        'subcategorias_master_id' => $subpresupuestoId,
                        'proyecto_generales_id'   => $proyectoId,
                    ]);

                    $subcategoriasInsertadas[$nombreSubcategoria] = $resultSubcategoria['lastInsertId'];
                    error_log("Subcategoria insertada: $nombreSubcategoria | id: {$subcategoriasInsertadas[$nombreSubcategoria]}");
                }

                $subpresupuestoProyectoId = $subcategoriasInsertadas[$nombreSubcategoria];

                $maxFila = $hojaPresupuesto->getHighestRow();
                $fila    = 3;

                while ($fila <= $maxFila) {
                    $item        = trim($hojaPresupuesto->getCell("A{$fila}")->getValue());
                    $descripcion = trim($hojaPresupuesto->getCell("B{$fila}")->getValue());
                    $unidad      = trim($hojaPresupuesto->getCell("H{$fila}")->getValue());

                    if (empty($item) && empty($descripcion)) {
                        $fila++;
                        continue;
                    }

                    if (!empty($unidad)) {
                        $fila++;
                        continue;
                    }

                    if (strpos($item, '.') === false) {
                        $fila++;
                        continue;
                    }

                    $partes = array_filter(explode('.', rtrim($item, '.')), function ($p) {
                        return $p !== '' && is_numeric($p);
                    });
                    $niveles = count($partes);

                    if ($niveles === 0) {
                        $fila++;
                        continue;
                    }

                    $typeItem = $niveles <= 2 ? 1 : 2;

                    $unidadNorm     = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $unidad)));
                    $unidadMedidaId = isset($mapaUnidades[$unidadNorm]) ? $mapaUnidades[$unidadNorm] : null;

                    error_log("Insertando type_item=$typeItem | item=$item | desc=$descripcion | subpresupuestos_id=$subpresupuestoId");

                    self::insert('presupuestos', [
                        'nro_orden'                          => $nroOrden,
                        'descripcion'                        => $descripcion,
                        'type_item'                          => $typeItem,
                        'proyecto_generales_id'              => $proyectoId,
                        'presupuestos_proyecto_generales_id' => null,
                        'subpresupuestos_id'                 => $subpresupuestoProyectoId,
                        'partidas_id'                        => null,
                        'metrado'                            => null,
                        'cu'                                 => null,
                        'mo'                                 => null,
                        'mt'                                 => null,
                        'eq'                                 => null,
                        'sc'                                 => null,
                        'sp'                                 => null,
                        'unidad_medidas_id'                  => $unidadMedidaId,
                    ]);

                    $nroOrden++;
                    $fila++;
                }
            }

            error_log("Presupuestos guardados. Total: " . ($nroOrden - 1));

            return [
                'success' => true,
                'message' => 'Proyecto creado correctamente',
                'id'      => $proyectoId
            ];
        } catch (Exception $e) {
            error_log('Error en crearPresupuestoColegio(): ' . $e->getMessage());
            error_log('Traza: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function agregarFilasAmbiente($ambientes, $sheet)
    {
        $ambienteFilas = [
            'BIBLIOTECA'                                => 16,
            'LABORATORIO'                               => 17,
            'ALMACÉN MAT. DEP.'                         => 18,
            'SUM'                                       => 19,
            'MÓDULO DE CONECTIVIDAD'                    => 20,
            'DIRECCIÓN ADM'                             => 21,
            'SUBDIRECCIÓN'                              => 22,
            'SALA DE REUNIONES'                         => 23,
            'SECRETARÍA'                                => 24,
            'SALA DE ESPERA'                            => 25,
            'COORDINACIÓN ADMINISTRATIVA'               => 26,
            'ARCHIVOS'                                  => 27,
            'TALLER CREATIVO PRIM'                      => 28,
            'TALLER CREATIVO SEC'                       => 29,
            'ECONOMATO'                                 => 30,
            'COORDINACIÓN PEDAGÓGICA'                   => 31,
            'TÓPICO'                                    => 32,
            'SALA DE PROFESORES'                        => 33,
            'TIENDA ESCOLAR'                            => 34,
            'ALMACÉN GENERAL'                           => 35,
            'SSHH ADM - HOMBRES'                        => 36,
            'SSHH ADM - MUJERES'                        => 37,
            'SSHH INICIAL - HOMBRES'                    => 38,
            'SSHH INICIAL - MUJERES'                    => 39,
            'SSHH PRIMARIA - HOMBRES'                   => 40,
            'SSHH PRIMARIA - MUJERES'                   => 41,
            'SSHH SECUNDARIA - HOMBRES'                 => 42,
            'SSHH SECUNDARIA - MUJERES'                 => 43,
            'VESTUARIOS - HOMBRES'                      => 44,
            'VESTUARIOS - MUJERES'                      => 45,
            'COCINA INICIAL'                            => 46,
            'COCINA PRIM -SEC'                          => 47,
            'OFICINA DE BIENESTAR'                      => 48,
            'LACTARIO'                                  => 49,
            'CTO ELÉCTRICO'                             => 50,
            'DEP. MATERIAL DEPORTIVO'                   => 51,
            'DEPÓSITO'                                  => 52,
            'GUARDIANÍA'                                => 53,
            'CUARTO DE LIMPIEZA'                        => 54,
            'AULA CICLO I'                              => 55,
            'AULA CICLO II'                             => 56,
            'AULA PRIMARIA'                             => 57,
            'AULA SECUNDARIA'                           => 58,
            'AULA PSICOMOTRICIDAD'                      => 59,
            'AULA DE INNOVACIÓN PEDAGÓGICA PRIM'        => 60,
            'AULA DE INNOVACIÓN PEDAGÓGICA SEC'         => 61,
            'TALLER EPT'                                => 62,
            'MAESTRANZA'                                => 63,
            'RESIDUOS'                                  => 64,
            'CIRCULACIÓN'                               => 65,
            'ESCALERA PRIM'                             => 66,
            'ESCALERA SEC'                              => 67,
            'AREA DE INGRESO'                           => 68,
            '#AMBIENTES'                                => 69,
        ];

        $normalizar = function ($str) {
            return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $str)));
        };

        // Normalizar las claves del mapa
        $ambienteFilasNormalizadas = array_combine(
            array_map($normalizar, array_keys($ambienteFilas)),
            array_values($ambienteFilas)
        );

        foreach ($ambientes ?? [] as $ambiente) {
            $tipo = $normalizar($ambiente['tipo']);

            if (!isset($ambienteFilasNormalizadas[$tipo])) {
                continue;
            }

            $fila = $ambienteFilasNormalizadas[$tipo];
            $sheet->setCellValue("B{$fila}", $ambiente['cantidad']);
            $sheet->setCellValue("C{$fila}", $ambiente['area']);
        }
    }

    private function agregarFilasExteriores($exteriores, $sheet)
    {
        $exterioresFilas = [
            'AREAS VERDES' => 72,
            'LOSA DEPORTIVA' => 73,
            'COBERTURA LOSA DEPORTIVA' => 74,
            'PATIO DE INICIAL' => 75,
            'COBERTURA PATIO DE INICIAL' => 76,
            'ASTA DE BANDERA' => 77,
            'VEREDAS Y RAMPAS DE CONCRETO' => 78,
            'PAVIMENTO RIGIDO VEHICULAR' => 79,
            'LAMAS EN PASADIZOS' => 80,
            'ESTACIONAMIENTO DE BICICLETAS' => 81,
            'CERCO PERIMETRICO H=3.00m' => 82,
            'PORTADA DE INGRESO  (PORTON METALICO)' => 83,
        ];

        $normalizar = function ($str) {
            return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $str)));
        };

        // Normalizar las claves del mapa
        $exterioresFilasNormalizadas = array_combine(
            array_map($normalizar, array_keys($exterioresFilas)),
            array_values($exterioresFilas)
        );

        foreach ($exteriores ?? [] as $ext) {
            $tipo = $normalizar($ext['tipo']);

            if (!isset($exterioresFilasNormalizadas[$tipo])) {
                continue;
            }

            $fila = $exterioresFilasNormalizadas[$tipo];
            if (
                $tipo == $normalizar('ASTA DE BANDERA') ||
                $tipo == $normalizar('ESTACIONAMIENTO DE BICICLETAS') ||
                $tipo == $normalizar('PORTADA DE INGRESO (PORTON METALICO)')
            ) {
                $sheet->setCellValue("B{$fila}", $ext['cantidad']);
            } elseif ($tipo == $normalizar('CERCO PERIMETRICO H=3.00m')) {
                $sheet->setCellValue("B{$fila}", $ext['cantidad']);
                $sheet->setCellValue("C{$fila}", $ext['ml']);
            } else {
                $sheet->setCellValue("B{$fila}", $ext['cantidad']);
                $sheet->setCellValue("C{$fila}", $ext['area']);
            }
        }
    }
}
