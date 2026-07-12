<?php

namespace App\Model;

use App\Model\Persistence\Mysql;
use App\Model\{Categoria, RecalculoPrespuesto};
use Aws\S3\S3Client;

class Resumen extends Mysql
{
    public function save($request)
    {
        $exteriores = [
            "AREAS VERDES" => 72,
            "LOSA DEPORTIVA" => 73,
            "COBERTURA LOSA DEPORTIVA" => 74,
            "PATIO DE INICIAL" => 75,
            "COBERTURA PATIO DE INICIAL" => 76,
            "VEREDAS Y RAMPAS DE CONCRETO" => 78,
            "PAVIMENTO RIGIDO VEHICULAR" => 79,
            "CERCO PERIMETRICO H=3.00m" => 82,
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

        $proyectoId = $request->proyecto_generales_id;
        $sqlUnidad  = 'SELECT id FROM unidad_medidas WHERE alias = :unidad';

        $unidadM2  = self::fetchObj($sqlUnidad, ['unidad' => 'M2']);
        $unidadUnd = self::fetchObj($sqlUnidad, ['unidad' => 'UND']);
        $unidadId  = $unidadM2->id ?? ($unidadUnd->id ?? null);

        $items = array_merge(
            array_map(
                fn($a) => array_merge($a, ['origen' => 'ambiente']),
                $request->ambientes  ?? []
            ),
            array_map(fn($e) => array_merge($e, ['origen' => 'exterior']), $request->exteriores ?? []),
        );

        //Insertar con costo_precio_mercado calculado
        foreach ($items as $item) {
            $descripcion = trim($item['tipo'] ?? '');
            $area        = (float) ($item['area']     ?? 0);
            $cantidad    = (int)   ($item['cantidad'] ?? 1);

            if (empty($descripcion) || $area <= 0) {
                continue;
            }

            $categoria  = new Categoria();
            $tipoFactor = $categoria->determinarTipoFactor($descripcion, $ambientes, $exteriores);

            self::insert('presupuesto_resumen', [
                'descripcion'           => $descripcion,
                'tipo_factor'           => 'INFRAESTRUCTURA',
                'u_fisica_um'           => $tipoFactor,
                'u_fisica_meta'         => $cantidad,
                'o_um_id'               => $unidadId,
                'o_meta'                => $cantidad * $area,
                'costo_precio_mercado'  => 0,
                'proyecto_generales_id' => $proyectoId,
            ]);
        }

        return ['success' => true];
    }

    public function probudgetPdfSave($request)
    {
        $proyectoId       = $request->proyecto_generales_id ?? null;
        $tipos            = $_POST['tipos'] ?? [];
        $especialidadesIds = $_POST['especialidades_ids'] ?? [];

        if (empty($proyectoId) || !ctype_digit((string) $proyectoId)) {
            return ['success' => false, 'message' => 'proyecto_generales_id inválido'];
        }

        if (empty($_FILES['archivos']) || empty($tipos)) {
            return ['success' => false, 'message' => 'Archivos o tipos faltantes'];
        }

        $archivos      = $_FILES['archivos'];
        $totalArchivos = count($archivos['tmp_name']);

        if ($totalArchivos !== count($tipos)) {
            return ['success' => false, 'message' => 'La cantidad de archivos y tipos no coincide'];
        }

        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['REGION_AWS'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_S3'],
                'secret' => $_ENV['AWS_SECRET_KEY_S3'],
            ],
        ]);

        $bucket = $_ENV['BUCKET_NAME'];
        $folder = trim($_ENV['AWS_FOLDER_S3'], '/');
        $urls   = [];

        try {
            for ($i = 0; $i < $totalArchivos; $i++) {
                $tmpName = $archivos['tmp_name'][$i];
                $error   = $archivos['error'][$i];
                $tipo    = trim($tipos[$i]);

                // Normaliza: string vacío o "0" se guarda como NULL
                $especialidadId = trim($especialidadesIds[$i] ?? '');
                $especialidadId = ($especialidadId === '' ? null : (int) $especialidadId);

                if ($error !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException("Error subiendo archivo tipo {$tipo} (código {$error})");
                }

                $mime = mime_content_type($tmpName);
                if ($mime !== 'application/pdf') {
                    throw new \RuntimeException("El archivo tipo {$tipo} no es un PDF válido");
                }

                $sufijoNombre = $especialidadId ? "{$tipo}-{$especialidadId}" : $tipo;
                $key = "{$folder}/{$proyectoId}/{$sufijoNombre}-" . bin2hex(random_bytes(4)) . ".pdf";

                $result = $s3->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => $key,
                    'Body'        => fopen($tmpName, 'r'),
                    'ContentType' => 'application/pdf',
                ]);

                $url = $result['ObjectURL'];
                $urls[] = ['tipo' => $tipo, 'especialidad_id' => $especialidadId, 'url' => $url];

                // Upsert usando proyecto_id + tipo + especialidad_id
                $sqlBuscar = 'SELECT id FROM probudget_pdfs 
                            WHERE proyecto_id = :proyecto_id AND tipo = :tipo 
                            AND (especialidad_id <=> :especialidad_id)';

                $existente = self::fetchObj($sqlBuscar, [
                    'proyecto_id'     => $proyectoId,
                    'tipo'            => $tipo,
                    'especialidad_id' => $especialidadId,
                ]);

                if ($existente) {
                    self::update(
                        'probudget_pdfs',
                        ['url' => $url, 'updated_at' => date('Y-m-d H:i:s')],
                        ['id' => $existente->id]
                    );
                } else {
                    self::insert('probudget_pdfs', [
                        'proyecto_id'     => $proyectoId,
                        'tipo'            => $tipo,
                        'especialidad_id' => $especialidadId,
                        'url'             => $url,
                        'created_at'      => date('Y-m-d H:i:s'),
                        'updated_at'      => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            error_log('Urls: ' . json_encode($urls));

            return [
                'success'     => true,
                'proyecto_id' => (int) $proyectoId,
                'urls'        => $urls,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getList($request)
    {
        $sql = 'SELECT
                    ps.id,
                    ps.descripcion,
                    ps.tipo_factor,
                    ps.u_fisica_um,
                    ps.u_fisica_meta,
                    ps.o_um_id,
                    um.alias,
                    ps.o_meta,
                    ps.costo_precio_mercado,
                    ps.proyecto_generales_id
                FROM presupuesto_resumen ps
                LEFT JOIN unidad_medidas um ON ps.o_um_id = um.id
                WHERE ps.proyecto_generales_id = :id';

        $items = self::fetchAllObj($sql, ['id' => $request->id]);

        $sqlProyecto = 'SELECT uso_plantilla FROM proyecto_generales WHERE id = :id AND deleted_at IS NULL';
        $proyecto    = self::fetchObj($sqlProyecto, ['id' => $request->id]);

        // Calcular total presupuesto replicando lógica del Twig
        $recalculo = new RecalculoPrespuesto();
        $pieData   = $recalculo->getPiePresupuesto(['id' => $request->id]);
        $totalPpto = 0;

        if (!empty($pieData['data']['pie'])) {
            $cd   = 0;
            $ggp  = 0.13;
            $utp  = 0.12;
            $igvp = 0.18;

            foreach ($pieData['data']['pie'] as $pieLine) {
                $variable  = is_object($pieLine) ? $pieLine->variable  : $pieLine['variable'];
                $monto     = is_object($pieLine) ? $pieLine->monto     : $pieLine['monto'];
                $porcentaje = is_object($pieLine) ? $pieLine->percentage : $pieLine['percentage'];

                if ($variable === 'CD') {
                    $cd = (float) $monto;
                }
                if ($variable === 'GG' && $porcentaje) {
                    $ggp = (float) $porcentaje;
                }
                if ($variable === 'UT' && $porcentaje) {
                    $utp = (float) $porcentaje;
                }
                if ($variable === 'IGV' && $porcentaje) {
                    $igvp = (float) $porcentaje;
                }
            }

            $subtotal  = $cd + ($cd * $utp) + ($cd * $ggp);
            $totalPpto = $subtotal + ($subtotal * $igvp);
        }

        $totalPpto = round($totalPpto, 2);

        // Sumar o_meta
        $sumaOmeta = array_sum(array_map(fn($i) => (float) $i->o_meta, $items));

        // Inyectar costo_precio_mercado calculado
        if (!$proyecto->uso_plantilla) {
            foreach ($items as $item) {
                $item->costo_precio_mercado = $sumaOmeta > 0
                    ? round(($totalPpto / $sumaOmeta) * (float) $item->o_meta, 2)
                    : 0;
            }
        }

        return [
            'uso_plantilla' => $proyecto->uso_plantilla ?? null,
            'items'         => $items,
        ];
    }

    public function getListProbudgetResumen($request)
    {
        error_log("ID: " . json_encode($request->id));
        if (empty($request->id)) {
            return [];
        }

        $sql = 'SELECT
                    ps.id,
                    ps.descripcion,
                    ps.tipo_factor,
                    ps.u_fisica_um,
                    ps.u_fisica_meta,
                    ps.o_um_id,
                    um.alias,
                    ps.o_meta,
                    ps.costo_precio_mercado,
                    ps.proyecto_generales_id
                FROM presupuesto_resumen ps
                LEFT JOIN unidad_medidas um ON ps.o_um_id = um.id
                WHERE ps.proyecto_generales_id = :id';

        $items = self::fetchAllObj($sql, ['id' => $request->id]);

        $sqlProyecto = 'SELECT 
                            id,
                            uso_plantilla
                        FROM proyecto_generales WHERE id = :id AND deleted_at IS NULL';
        $proyecto    = self::fetchObj($sqlProyecto, ['id' => $request->id]);

        // Calcular total presupuesto replicando lógica del Twig
        $recalculo = new RecalculoPrespuesto();
        $pieData   = $recalculo->getPiePresupuesto(['id' => $request->id]);
        $totalPpto = 0;

        if (!empty($pieData['data']['pie'])) {
            $cd   = 0;
            $ggp  = 0.13;
            $utp  = 0.12;
            $igvp = 0.18;

            foreach ($pieData['data']['pie'] as $pieLine) {
                $variable  = is_object($pieLine) ? $pieLine->variable  : $pieLine['variable'];
                $monto     = is_object($pieLine) ? $pieLine->monto     : $pieLine['monto'];
                $porcentaje = is_object($pieLine) ? $pieLine->percentage : $pieLine['percentage'];

                if ($variable === 'CD') {
                    $cd = (float) $monto;
                }
                if ($variable === 'GG' && $porcentaje) {
                    $ggp = (float) $porcentaje;
                }
                if ($variable === 'UT' && $porcentaje) {
                    $utp = (float) $porcentaje;
                }
                if ($variable === 'IGV' && $porcentaje) {
                    $igvp = (float) $porcentaje;
                }
            }

            $subtotal  = $cd + ($cd * $utp) + ($cd * $ggp);
            $totalPpto = $subtotal + ($subtotal * $igvp);
        }

        $totalPpto = round($totalPpto, 2);

        // Sumar o_meta
        $sumaOmeta = array_sum(array_map(fn($i) => (float) $i->o_meta, $items));

        // Inyectar costo_precio_mercado calculado
        if (!$proyecto->uso_plantilla) {
            foreach ($items as $item) {
                $item->costo_precio_mercado = $sumaOmeta > 0
                    ? round(($totalPpto / $sumaOmeta) * (float) $item->o_meta, 2)
                    : 0;
            }
        }

        $sql = 'SELECT
                    ppdf.id,
                    ppdf.proyecto_id,
                    ppdf.tipo,
                    ppdf.especialidad_id,
                    spg.descripcion AS especialidad,
                    ppdf.url,
                    ppdf.created_at
                FROM probudget_pdfs ppdf
                LEFT JOIN subcategorias_proyecto_general spg
                    ON ppdf.especialidad_id = spg.id
                WHERE proyecto_id = :id';
        $pdfs = self::fetchAllObj($sql, ['id' => $request->id]);

        return [
            'proyecto_generales_id' => $proyecto->id,
            'resumen' => $items,
            'pdfs' => $pdfs
        ];
    }
}
