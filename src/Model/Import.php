<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/DetalleInsumos.php');

namespace App\Model;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\DetalleInsumos;

class Import extends Mysql
{
    private $_file;
    private $_type;
    private $_id;
    private $_proyecto_generales_id;
    public function __construct($request, $file)
    {
        $this->_file = $file;
        $this->_type = $request->type;
        if (isset($request->proyecto_generales_id)) {
            $this->_proyecto_generales_id = $request->proyecto_generales_id;
        }
    }

    public function getImportType()
    {

        try {
            $file_type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($this->_file["tmp_name"]);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($file_type);
            $spreadsheet = $reader->load($this->_file["tmp_name"]);

            $var = [
                "tipo" => $this->_type,
                "date" => date("Y-m-d H:i:s"),
            ];

            $insert =   self::insert("importacion", $var);
            $this->_id = $insert["lastInsertId"];
            switch ($this->_type) {
                case 1:
                    $data = $spreadsheet->getActiveSheet()->toArray();
                    $sql_um = "SELECT
                                    id,
                                    descripcion,
                                    alias,
                                    apu_cantidad
                                FROM unidad_medidas ";
                    $unidadMedida = self::fetchAllObj($sql_um);
                    $sql_insumos = "SELECT
                                        id,
                                        codigo,
                                        iu,
                                        indice_unificado,
                                        tipo,
                                        insumos,
                                        precio,                                        
                                        unidad_medidas_id
                                    FROM insumos";
                    $insumos = self::fetchAllObj($sql_insumos);
                    $info = $this->getInsumo($data, $insumos, $unidadMedida);
                    break;

                case 2:
                    $dataPartidas = $spreadsheet->getSheet(0)->toArray();
                    $dataApu = $spreadsheet->getSheet(1)->toArray();
                    $sql_insumos = "SELECT
                                        id,
                                        codigo
                                    FROM insumos";
                    $insumos = self::fetchAllObj($sql_insumos);
                    $sql_um = "SELECT
                                    id,
                                    descripcion,
                                    alias,
                                    apu_cantidad
                                FROM unidad_medidas ";
                    $unidadMedida = self::fetchAllObj($sql_um);
                    $sql_partidas = "SELECT
                                        id,
                                        partida,
                                        rendimiento,
                                        rendimiento_unid,
                                        unidad_medidas_id
                                FROM partidas";
                    $partidas = self::fetchAllObj($sql_partidas);
                    $sql_apus = "SELECT
                                        id,
                                        cuadrilla,
                                        cantidad,
                                        insumo_id,
                                        partida_id,
                                        unidad_medidas_id
                                FROM apus_partidas";
                    $apus = self::fetchAllObj($sql_apus);
                    $info = $this->getPartida($dataPartidas, $dataApu, $partidas, $apus, $unidadMedida, $insumos);
                    break;
                case 3:
                    $data = $spreadsheet->getActiveSheet()->toArray();
                    $sql_subcategorias = "SELECT
                                            id,
                                            descripcion                                    
                                    FROM subcategorias";
                    $subcategorias = self::fetchAllObj($sql_subcategorias);
                    $this->getSubProyectos($data, $subcategorias);
                    $info = [];
                    break;
                case 4:
                    $data = $spreadsheet->getActiveSheet()->toArray();
                    $sql_titulos = "SELECT
                                            id,
                                            titulo                                    
                                    FROM presupuestos_title";
                    $titulos = self::fetchAllObj($sql_titulos);
                    $this->getTitulos($data, $titulos);
                    $info = [];
                    break;
            }
            return ["success" => true, "message" => "Procesos realizado", "data" => $info];
        } catch (\Throwable $th) {
            return ["success" => false, "message" => "Procesos invalido"];
        }
    }

    public function getInsumo($data, $insumos, $unidad_medida)
    {

        try {
            $cont = 0;
            $created = 0;
            $updated = 0;
            $array_update = [];
            foreach ($data as $row) {
                if ($cont) {
                    if (!$row[0]) {
                        continue;
                    }
                    if ($row[0]) {
                        $searchedInsumo = $row[0];
                        $objectInsumo =   array_filter(
                            $insumos,
                            function ($e) use ($searchedInsumo) {
                                return $e->codigo == $searchedInsumo;
                            }
                        );

                        if (!$objectInsumo) {
                            $type = "INSERT";
                        } else {
                            $type = "UPDATE";
                        }
                    } else {
                        $type = "INSERT";
                    }

                    if ($row[5]) {
                        $searchedUM = $row[5];
                        $objectUM =   array_filter(
                            $unidad_medida,
                            function ($e) use ($searchedUM) {
                                return $e->alias == $searchedUM;
                            }
                        );

                        if ($objectUM) {
                            $array = [];
                            foreach ($objectUM as $item) {
                                array_push($array, $item);
                            }
                            $idUM = $array[0]->id;
                        } else {
                            $var_un = [
                                "descripcion" => $searchedUM,
                                "alias" => $searchedUM
                            ];
                            if ($searchedUM == '%MO') {
                                $var_un['apu_cantidad'] = 1;
                            }
                            $insert = self::insert("unidad_medidas", $var_un);
                            $idUM = $insert["lastInsertId"];
                            $sql_um = "SELECT
                                            id,
                                            descripcion,
                                            alias,
                                            apu_cantidad
                                        FROM unidad_medidas ";
                            $unidad_medida = self::fetchAllObj($sql_um);
                        }
                    }

                    $var = [
                        "codigo" => $row[0],
                        "insumos" => $row[4],
                        "tipo" => $row[3],
                        "indice_unificado" => $row[2],
                        "unidad_medidas_id" => $idUM,
                        "importacion_id" => $this->_id,
                    ];

                    if ($row[1]) {
                        $var['iu'] = (int)$row[1];
                    }
                    if ($row[6]) {
                        $var['precio'] = number_format($row[6], 2, '.', '');
                    }

                    if ($type == "INSERT") {
                        self::insert("insumos", $var);
                        $created++;
                    } else {
                        $key = array_keys($objectInsumo)[0];
                        $objectInsumo = $objectInsumo[$key];
                        $isChanged = $this->checkChangesSupply($objectInsumo, $row, $idUM);
                        if ($isChanged) {
                            $var = [
                                "id" => $objectInsumo->id,
                                "codigo" => $row[0],
                                "insumos" => $row[4],
                                "precio" => $row[6],
                                "tipo" => $row[3],
                                "indice_unificado" => $row[2],
                                "iu" => $row[1],
                                "unidad_medidas_id" => $idUM,
                                "importacion_id" => $this->_id,
                                "uni" => $row[5]
                            ];
                            array_push($array_update, $var);
                            $updated++;
                        }
                    }
                }
                $cont++;
            }
            return [
                'success' => true,
                'data' => $array_update,
                'total' => $cont - 1,
                'created' => $created,
                'updated' => $updated,
                'message' => ($array_update) ? 'Se encontraron los registros, reenviar para actualizar' : ''
            ];
        } catch (\Throwable $th) {
            return ["success" => false, "message" => 'Ocurrió un error al importar insumos, verifique que su codigo de insumo sea único'];
        }
    }

    public function getPartida($dataPartidas, $dataApu, $partidas, $apus, $unidad_medida, $insumos)
    {
        try {
            $newUnidMeds = [];
            $newInsumos = [];
            $cont = 0;
            $created = 0;
            $array_update = [];
            foreach ($dataPartidas as $row) {
                if ($cont) {
                    if (!$row[0]) {
                        continue;
                    }
                    if ($row[1]) {
                        $searchedInsumo = $row[1];
                        $objectInsumo =   array_filter(
                            $partidas,
                            function ($e) use ($searchedInsumo) {
                                return $e->id == $searchedInsumo;
                            }
                        );

                        if (!$objectInsumo) {
                            $type = "INSERT";
                        } else {
                            $type = "UPDATE";
                        }
                    } else {
                        $type = "INSERT";
                    }

                    if ($row[3]) {
                        $searchedUM = $row[3];
                        $idUM = $this->getUnidadeMedidas($searchedUM, $unidad_medida, $newUnidMeds);
                    }

                    $var = [
                        "partida" => $row[2],
                        "rendimiento_unid" => $row[5],
                        "unidad_medidas_id" => $idUM,
                        "importacion_id" => $this->_id,
                    ];

                    if ($row[4]) {
                        $var['rendimiento'] = number_format($row[4], 2, '.', '');
                    }

                    $searchedPartida = $row[0];
                    $objectApus = array_filter(
                        $dataApu,
                        function ($e) use ($searchedPartida) {
                            return $e[0] == $searchedPartida;
                        }
                    );

                    if ($type == "INSERT") {
                        $lastInsert = self::insert("partidas", $var);
                        foreach ($objectApus as $item) {
                            $idUM2 = $this->getUnidadeMedidas($item[4], $unidad_medida, $newUnidMeds);
                            $insumoid = $this->findInsumo($insumos, $item, $idUM2, $newInsumos);
                            $create = [
                                'insumo_id' => $insumoid,
                                'partida_id' => $lastInsert['lastInsertId'],
                                'unidad_medidas_id' => $idUM2,
                            ];
                            if ($item[5]) {
                                $create['cuadrilla'] = number_format($item[5], 2, '.', '');
                            }
                            if ($item[6]) {
                                $cant = $item[6];
                                if (strtoupper($item[4]) == '%MO') {
                                    $cant = $cant / 100;
                                }
                                $create['cantidad'] = number_format($cant, 4, '.', '');
                            }
                            self::insert("apus_partidas", $create);
                        }
                        $created++;
                    } else {
                        $key = array_keys($objectInsumo)[0];
                        $objectInsumo = $objectInsumo[$key];
                        $var = [
                            "id" =>  $objectInsumo->id,
                            "partida" => $row[2],
                            "rendimiento" => $row[4],
                            "rendimiento_unid" => $row[5],
                            "unidad_medidas_id" => $idUM,
                            "importacion_id" => $this->_id,
                        ];
                        $pid = $objectInsumo->id;
                        $arrayApus = array();
                        foreach ($objectApus as $item) {
                            $idUM2 = $this->getUnidadeMedidas($item[4], $unidad_medida, $newUnidMeds);
                            $insumoid = $this->findInsumo($insumos, $item, $idUM2, $newInsumos);
                            $searchedApu = $insumoid;
                            $objectUpApu = array_filter($apus, function ($e) use ($searchedApu, $pid) {
                                return $e->insumo_id == $searchedApu && $pid == $e->partida_id;
                            });
                            $napu = array(
                                'id' => 0,
                                'codigo' => $item[1],
                                'tipo' => $item[2],
                                'insumo' => $item[3],
                                'cuadrilla' => $item[5],
                                'cantidad' => $item[6],
                                'precio' => $item[7],
                                'partida_id' => $objectInsumo->id
                            );
                            if ($item[6] && strtoupper($item[4]) == '%MO') {
                                $napu['cantidad'] = $item[6] / 100;
                            }
                            if ($objectUpApu && count($objectUpApu) > 0) {
                                $key = array_keys($objectUpApu)[0];
                                $napu['id'] = $objectUpApu[$key]->id;
                            }
                            $napu['unidad_medidas_id'] = $idUM2;
                            $napu['insumo_id'] = $insumoid;
                            $napu['uni'] = $item[4];
                            $napu['parcial'] = $item[8];
                            array_push($arrayApus, $napu);
                        }
                        $var['apus'] = $arrayApus;
                        array_push($array_update, $var);
                    }
                }
                $cont++;
            }
            $response = array(
                'partidas' => $array_update,
                'insumos' => $newInsumos
            );
            return [
                'success' => true,
                'total' => $cont - 1,
                'created' => $created,
                'data' => $response,
                'message' => ($array_update) ? 'Se contro registro, reenviar para actualizar' : ''
            ];
        } catch (\Throwable $th) {
            return ["success" => false, "message" => $th->getMessage()];
        }
    }

    public function getSubProyectos($data, $subProyectos)
    {
        $cont = 0;
        foreach ($data as $row) {
            if ($cont) {
                if ($row[0]) {
                    $searchedInsumo = $row[0];
                    $objectInsumo =   array_filter(
                        $subProyectos,
                        function ($e) use ($searchedInsumo) {
                            return $e->id == $searchedInsumo;
                        }
                    );

                    if (!$objectInsumo) {
                        $type = "INSERT";
                    } else {
                        $type = "UPDATE";
                    }
                } else {
                    $type = "INSERT";
                }

                $var = [
                    "descripcion" => $row[1],
                ];

                if ($type == "INSERT") {
                    self::insert("subcategorias", $var);
                } else {
                    self::update("subcategorias", $var, ['id' =>  $objectInsumo->id]);
                }
            }
            $cont++;
        }
        return true;
    }


    public function getTitulos($data, $titulos)
    {
        $cont = 0;
        foreach ($data as $row) {
            if ($cont) {
                if (!$row[1]) {
                    continue;
                }
                if ($row[0]) {
                    $searchedTitulo = $row[0];
                    $objectTitulo =   array_filter(
                        $titulos,
                        function ($e) use ($searchedTitulo) {
                            return $e->id == $searchedTitulo;
                        }
                    );

                    if (!$objectTitulo) {
                        $type = "INSERT";
                    } else {
                        $type = "UPDATE";
                    }
                } else {
                    $type = "INSERT";
                }

                $var = [
                    "titulo" => $row[1],
                ];

                if ($type == "INSERT") {
                    self::insert("presupuestos_title", $var);
                } else {
                    self::update("presupuestos_title", $var, ['id' =>  $objectTitulo->id]);
                }
            }
            $cont++;
        }
        return true;
    }

    public function getUpdateRegsitro($data)
    {
        try {
            switch ($this->_type) {
                case 1:
                    if (!empty($data)) {
                        $messge = 'Se han encontrado nuevas actualizaciónes de insumos. ¿Desea actualizar sus insumos?.';
                        $mtype = 'Insumos';
                        $idnotify = $this->createNotificationProjects($messge, $mtype);
                    }
                    foreach ($data as $value) {
                        $var = [
                            "insumos" => $value->insumos,
                            "tipo" => $value->tipo,
                            "indice_unificado" => $value->indice_unificado,
                            "unidad_medidas_id" => $value->unidad_medidas_id,
                            "importacion_id" => $value->importacion_id,
                            "notify_id" => $idnotify
                        ];
                        if ($value->iu) {
                            $var['iu'] = (int)$value->iu;
                        }
                        if ($value->precio) {
                            $var['precio'] = number_format($value->precio, 2, '.', '');
                        }
                        self::update("insumos", $var, ['id' =>  $value->id]);
                        if ($value->precio == null) {
                            self::ex("UPDATE insumos SET precio = NULL WHERE id = " . $value->id);
                        }
                    }
                    break;

                case 2:
                    foreach ($data as $value) {
                        $var = [
                            "id" =>  $value->id,
                            "partida" => $value->partida,
                            "rendimiento_unid" => $value->rendimiento_unid,
                            "unidad_medidas_id" => $value->unidad_medidas_id,
                            "importacion_id" => $value->importacion_id,
                        ];
                        if ($value->rendimiento) {
                            $var['rendimiento'] = number_format($value->rendimiento, 2, '.', '');
                        }
                        self::update("partidas", $var, ['id' => $value->id]);
                        foreach ($value->apus as $row) {
                            $up = [
                                'insumo_id' => $row->insumo_id,
                                'partida_id' => $row->partida_id,
                                'unidad_medidas_id' => $row->unidad_medidas_id
                            ];
                            if ($row->cuadrilla) {
                                $up['cuadrilla'] = number_format($row->cuadrilla, 2, '.', '');
                            }
                            if ($row->cantidad) {
                                $up['cantidad'] = number_format($row->cantidad, 4, '.', '');
                            }
                            if ($row->id == 0) {
                                self::insert("apus_partidas", $up);
                                continue;
                            }
                            self::update("apus_partidas", $up, ['id' => $row->id]);
                        }
                    }
                    break;
            }

            return ["success" => true];
        } catch (\Throwable $th) {
            return ["success" => false];
        }
    }

    public function getInfoTransaccion()
    {
        $sql = "SELECT nt.id, nt.createdAt AS date FROM notificacion_proyecto np
        INNER JOIN notificaciones nt ON np.notificacion_id = nt.id
        WHERE np.estado = '1' AND np.proyectos_generales_id = :id LIMIT 1";
        $importacion = self::fetchObj($sql, ["id" => $this->_proyecto_generales_id]);

        if ($importacion) {
            switch ($this->_type) {
                case 1:
                    $sql = 'SELECT
                                    id,
	                                insumos,
	                                precio,
	                                tipo,
	                                indice_unificado,
	                                unidad_medidas_id,
	                                notify_id
                                FROM insumos
                                WHERE notify_id = :notify_id ';
                    $resp = self::fetchAllObj($sql, ["notify_id" => $importacion->id]);
                    break;
                case 2:
                    $sql = 'SELECT                                     
                                    id,
	                                partida,
	                                precio,
	                                unidad_medidas_id,
	                                importacion_id
                                FROM partidas
                                WHERE importacion_id = :importacion_id ';
                    $resp = self::fetchAllObj($sql, ["importacion_id" => $importacion->id]);
                    break;
            }
            return [
                "success" => true,
                "transaccion" => $importacion,
                "data" => $resp,
            ];
        } else {
            return [
                "success" => false,
                "message" => "No se encontro registro",
            ];
        }
    }

    public function getUpdateProyectoGeneralInsumo($proyecto_generales_id, $insumos)
    {
        try {
            $masterid = "";

            foreach ($insumos as $value) {
                $masterid .=  ",{$value->id}";
            }

            $masterid = ltrim($masterid, ',');

            if ($masterid) {
                $sql = "SELECT id, iu, indice_unificado, tipo, insumos, precio, unidad_medidas_id, notify_id FROM insumos WHERE id IN ($masterid)";
                $insumos = self::fetchAllObj($sql);
                if (!empty($insumos)) {
                    $notify_id = 0;
                    foreach ($insumos as $insumo) {
                        $var = array(
                            'iu' => $insumo->iu,
                            'indice_unificado' => $insumo->indice_unificado,
                            'tipo' => $insumo->tipo,
                            'insumos' => $insumo->insumos,
                            'unidad_medidas_id' => $insumo->unidad_medidas_id
                        );
                        if ($insumo->precio) {
                            $var['precio'] = number_format($insumo->precio, 2, '.', '');
                        }
                        self::update("insumos_proyecto", $var, ['master_insumo_id' => $insumo->id]);
                        if ($insumo->precio == null) {
                            self::ex("UPDATE insumos_proyecto SET precio = NULL WHERE master_insumo_id = " . $insumo->id);
                        }
                        $notify_id = $insumo->notify_id;
                    }

                    if ($notify_id) {
                        self::update("notificacion_proyecto", ['estado' => '0', 'omitir' => 'Si'], ['id' => $notify_id]);
                    }

                    $detalleInsumos = new DetalleInsumos(false);
                    $detalleInsumos->actualizarPartidas($proyecto_generales_id);
                }
                return [
                    "success" => true,
                    "message" => "proceso actualizado",
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Error al actualizar insumos",
                ];
            }
        } catch (\Throwable $th) {
            return [
                "success" => false,
                "message" => "proceso con errores",
            ];
        }
    }

    private function getUnidadeMedidas($searchedUM, $unidad_medida, &$newUnidMeds)
    {
        $idUM = 0;
        $objectUM = array_filter(
            $unidad_medida,
            function ($e) use ($searchedUM) {
                return $e->alias == $searchedUM;
            }
        );

        if ($objectUM && count($objectUM) > 0) {
            $array = [];
            foreach ($objectUM as $item) {
                array_push($array, $item);
            }
            $idUM = $array[0]->id;
        } else {
            if (isset($newUnidMeds[bin2hex($searchedUM)])) {
                $idUM = $newUnidMeds[bin2hex($searchedUM)]['id'];
            } else {
                $var_un = [
                    "descripcion" => $searchedUM,
                    "alias" => $searchedUM,
                ];
                if (strtoupper($searchedUM) == '%MO') {
                    $var_un['apu_cantidad'] = 1;
                }
                $insert = self::insert("unidad_medidas", $var_un);
                $idUM = $insert["lastInsertId"];
                $newUnidMeds[bin2hex($searchedUM)] = array(
                    "id" => $idUM,
                    "descripcion" => $searchedUM,
                    "alias" => $searchedUM,
                );
            }
        }
        return $idUM;
    }

    private function findInsumo($insumos, $apu, $idUM2, &$newInsumos)
    {
        $codigo = $apu[1];
        $objectInsumo = array_filter(
            $insumos,
            function ($e) use ($codigo) {
                return $e->codigo == $codigo;
            }
        );
        if ($objectInsumo && count($objectInsumo)) {
            $key = array_keys($objectInsumo)[0];
            return $objectInsumo[$key]->id;
        } else {
            $nobjectInsumo = array_filter($newInsumos, function ($e) use ($codigo) {
                return $e['codigo'] == $codigo;
            });
            if ($nobjectInsumo && count($nobjectInsumo)) {
                $key = array_keys($nobjectInsumo)[0];
                return $nobjectInsumo[$key]['id'];
            } else {
                $var = [
                    'codigo' => $codigo,
                    'tipo' => $apu[2],
                    'insumos' => $apu[3],
                    'precio' => $apu[7],
                    'unidad_medidas_id' => $idUM2
                ];
                $insert = self::insert("insumos", $var);
                $id = $insert["lastInsertId"];
                $var['id'] = $id;
                $var['uni'] = $apu[4];
                array_push($newInsumos, $var);
                return $id;
            }
        }
    }

    private function checkChangesSupply($oldinfo, $newinfo, $uni)
    {
        if ($oldinfo->iu != $newinfo[1]) {
            return true;
        }
        if ($oldinfo->indice_unificado != $newinfo[2]) {
            return true;
        }
        if ($oldinfo->tipo != $newinfo[3]) {
            return true;
        }
        if ($oldinfo->insumos != $newinfo[4]) {
            return true;
        }
        if (empty($oldinfo->precio) && empty($newinfo[6])) {
            return false;
        }
        if ($oldinfo->precio != $newinfo[6]) {
            return true;
        }
        if ($oldinfo->unidad_medidas_id != $uni) {
            return true;
        }
        return false;
    }

    private function createNotificationProjects($messge, $mtype)
    {
        $lastInsert = self::insert('notificaciones', array(
            'message' => $messge,
            'title' => $mtype,
            'type_notify' => 1,
            'createdAt' => date("Y-m-d H:i:s")
        ));
        $sql = 'SELECT id FROM proyecto_generales WHERE deleted_at is NULL';
        $data = self::fetchAllObj($sql);
        foreach ($data as $item) {
            self::insert("notificacion_proyecto", array(
                'estado' => '1',
                'omitir' => 'No',
                'proyectos_generales_id' => $item->id,
                'notificacion_id' => $lastInsert['lastInsertId'],
            ));
        }
        return $lastInsert['lastInsertId'];
    }
}
