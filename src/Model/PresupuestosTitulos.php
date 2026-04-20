<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;

class PresupuestosTitulos extends Mysql
{
    private $_titulo;
    public function getpartidas()
    {
        return $this->_partidas;
    }
    private $titulos;
    private $_proyecto_generales_id;


    public function __construct($array = [])
    {
        $this->_titulo = FG::validateMatrizKey('title', $array);
        $this->titulos = FG::validateMatrizKey('titulos', $array);
        $this->_proyecto_generales_id = FG::validateMatrizKey('proyecto_generales_id', $array);
    }

    public function getSave()
    {
        if ($this->titulos) {
            $titleData = json_decode($this->titulos);
            if ($titleData) {
                if ($titleData->id != 0) {
                    $sql = 'SELECT COUNT(id) AS id FROM titulos_proyecto WHERE id = :id  AND proyectos_generales_id = :proyectos_generales_id';
                    $presupuestos_title = self::fetchObj($sql, ['id' => $titleData->id, 'proyectos_generales_id' => $this->_proyecto_generales_id]);
                    $var = ["titulo" => $this->_titulo, 'proyectos_generales_id' => $this->_proyecto_generales_id];
                    if ($titleData->masterid) {
                        $var['master_title_id'] = $titleData->masterid;
                    }
                    if ($presupuestos_title && $presupuestos_title->id == 0) {
                        $insert = self::insert("titulos_proyecto", $var);
                        if ($insert && $insert["lastInsertId"]) {
                            return $insert["lastInsertId"];
                        }
                        return 0;
                    } else {
                        self::update("titulos_proyecto", $var, ['id' => $titleData->id]);
                    }
                    return $titleData->id;
                } elseif ($titleData->id == 0 && $titleData->masterid != 0) {
                    $sql = 'SELECT COUNT(id) AS id FROM titulos_proyecto WHERE master_title_id = :master_title_id  AND proyectos_generales_id = :proyectos_generales_id';
                    $presupuestos_title = self::fetchObj($sql, ['master_title_id' => $titleData->masterid, 'proyectos_generales_id' => $this->_proyecto_generales_id]);
                    if ($presupuestos_title && $presupuestos_title->id == 0) {
                        $insert = self::insert("titulos_proyecto", [
                            'titulo' => $this->_titulo,
                            'proyectos_generales_id' => $this->_proyecto_generales_id,
                            'master_title_id' => $titleData->masterid
                        ]);
                        if ($insert && $insert["lastInsertId"]) {
                            return $insert["lastInsertId"];
                        }
                    } else {
                        self::update("titulos_proyecto", [
                            'titulo' => $this->_titulo,
                        ], [
                            'proyectos_generales_id' => $this->_proyecto_generales_id,
                            'master_title_id' => $titleData->masterid
                        ]);
                    }
                } elseif ($titleData->id == 0 && $titleData->masterid == 0) {
                    $var = [
                        'titulo' => $this->_titulo,
                        'proyectos_generales_id' => $this->_proyecto_generales_id
                    ];
                    $insert = self::insert("titulos_proyecto", $var);
                    if ($insert && $insert["lastInsertId"]) {
                        return $insert["lastInsertId"];
                    }
                }
            }
        }
        return 0;
    }


    public function getListTitle($request)
    {

        $sql = 'SELECT id, titulo FROM presupuestos_title';
        $titulosMaster = self::fetchAllObj($sql);

        $sql = 'SELECT id, titulo FROM titulos_proyecto WHERE proyectos_generales_id = :id AND master_title_id IS NULL';
        $titulosProyecto = self::fetchAllObj($sql, ['id' => $request->proyectos_generales_id]);

        $data = array(
            'master' => $titulosMaster,
            'proyecto' => $titulosProyecto
        );

        $resp['success'] = true;
        $resp['message'] = '';
        $resp['data'] = $data;

        return $resp;
    }
}
