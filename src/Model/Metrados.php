<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');
//require_once(__DIR__ . '/../utilitarian/Storage.php');
//require_once(__DIR__ . '/ApusPartidasProyecto.php');
//require_once(__DIR__ . '/../src/Plan.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;
use App\Model\Utilitarian\Storage;
use App\Model\ApusPartidasProyecto;
use App\Model\Plan;
use stdClass;

class Metrados extends Mysql
{
    private $_id;
    private $_proyecto_generales_id;
    private $_metrado_largo;
    private $_metrado_alto;
    private $_metrado_ancho;
    private $_metrado_area;
    private $_metrado_cantidad;
    private $_metrado_nro_elemto;
    private $_metrado_factor;
    private $_metrado_volumen;
    private $_presupuestos_id;
    private $_descripcion;
    private $_values = [];
    private $_subpresupuestos_id;
    private $_is_information;
    private $_position;
    private $_subpartida_id;

    public function __construct($request)
    {
        $column = [
            'id',
            'descripcion',
            'metrado_largo',
            'metrado_alto',
            'metrado_ancho',
            'metrado_area',
            'metrado_volumen',
            'metrado_cantidad',
            'metrado_nro_elemto',
            'metrado_factor',
            'presupuestos_id',
            'proyecto_generales_id',
            'is_information',
            'position'
        ];

        foreach ($column as $value) {
            if (isset($request->{$value}) && !empty($request->{$value})) {
                if ($value != 'id') {
                    $this->_values[$value] = $request->{$value};
                }
                $this->{"_$value"} = $request->{$value};
            }
            if ($value == 'is_information' && isset($request->is_information)) {
                $this->_values[$value] = 1;
                $this->_is_information = 1;
            }
            if (isset($request->subpresupuestos_id) && !empty($request->subpresupuestos_id)) {
                $this->_subpresupuestos_id = $request->subpresupuestos_id;
            }
        }
        if (isset($request->subpartida_id)) {
            $this->_subpartida_id = $request->subpartida_id;
        }
    }

    public function getSave()
    {
        try {
            $valuesNullable = $this->setNullableValues();

            if (isset($_FILES["img"]["name"]) && $_FILES["img"]["name"]) {
                $plan = new Plan();
                $proyecto = self::fetchObj("SELECT*FROM proyecto_generales WHERE id = :id", ['id' => $this->_values['proyecto_generales_id']]);
                if ($proyecto) {
                    $result = $plan->getValidate(['modulo' => 3, 'user_id' => $proyecto->users_id, 'peso' => $_FILES["img"]['size']]);
                    if (!$result['success']) {
                        $resp['success'] = false;
                        $resp['message'] = $result['message'];
                        return $resp;
                    }
                }
                $result = $this->uploadImage(@$proyecto->users_id);
                if ($result['success']) {
                    $this->_values["img"] = $result['key'];
                }
            }

            if ($this->_id) {
                $sql = 'SELECT COUNT(id) AS checked FROM metrado_partida_presupuestos WHERE id = :id';
                $metrado = self::fetchObj($sql, ['id' => $this->_id]);
                if ($metrado && $metrado->checked) {
                    self::update("metrado_partida_presupuestos", $this->_values, ['id' => $this->_id]);
                    $valueSets = [];
                    foreach ($valuesNullable as $key => $value) {
                        $valueSets[] = "$key = NULL";
                    }
                    $valuesNullable = implode(", ", $valueSets);
                    if ($valuesNullable) {
                        self::ex("UPDATE metrado_partida_presupuestos SET {$valuesNullable} WHERE id = " . $this->_id);
                    }
                    $resp['success'] = true;
                    $resp['message'] = 'Metrado actualizado registrado';
                    $this->_values['id'] = $this->_id;
                    if ($this->_position) {
                        $this->_position = $this->_position * 1;
                        $sql = "SET @norder = {$this->_position};
                                UPDATE metrado_partida_presupuestos SET position = (@norder:=@norder+1) WHERE presupuestos_id = {$this->_presupuestos_id}
                                AND position >= {$this->_position} AND id <> {$this->_id} ORDER BY position ASC";
                        self::ex($sql);
                    }
                    $resp['data'] = $this->_values;
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el metrado';
                }
            } else {
                $insert = self::insert("metrado_partida_presupuestos", $this->_values);
                if ($insert && $insert["lastInsertId"]) {
                    $this->_values['id'] = $insert["lastInsertId"];
                    if ($this->_position) {
                        $this->_position = $this->_position * 1;
                        $sql = "SET @norder = {$this->_position};
                                UPDATE metrado_partida_presupuestos SET position = (@norder:=@norder+1) WHERE presupuestos_id = {$this->_presupuestos_id}
                                AND position >= {$this->_position} AND id <> {$this->_values['id']} ORDER BY position ASC";
                        self::ex($sql);
                    }
                    $resp['success'] = true;
                    $resp['message'] = 'Metrado registrado';
                    $resp['data'] = $this->_values;
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Ocurrió un erro al registrar presupuesto';
                }
            }
            $req = new stdClass();
            $req->presupuestos_id = $this->_presupuestos_id;
            $req->subpartida_id = $this->_subpartida_id;
            $apusPartidasProyecto = new ApusPartidasProyecto();
            $apus = $apusPartidasProyecto->getListApus($req);
            $resp['apus'] = $apus;
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getListMetrado()
    {
        $resp = [];
        try {
            $sql_presupuesto = "SELECT
                                id,
                                descripcion,                                
                                metrado_largo,
                                metrado_ancho,
                                metrado_area,
                                metrado_alto,
                                metrado_volumen,
                                metrado_cantidad,
                                metrado_nro_elemto,
                                metrado_factor,
                                presupuestos_id,
                                proyecto_generales_id,
                                img,
                                is_information
                        FROM metrado_partida_presupuestos
                        WHERE presupuestos_id = :id ORDER BY position ASC";
            $metrados = self::fetchAllObj($sql_presupuesto, ['id' => $this->_id]);

            $metered = 0.00;

            foreach ($metrados as $key => $metrado) {
                $metrados[$key]->parcial = $this->getMetradoParcial($metrado);
                $metered += $metrados[$key]->parcial;
            }

            $this->updateMetradoPartida($metered);

            $region = $_ENV['REGION_AWS'];
            $bucket = $_ENV['BUCKET_NAME'];

            $resp['success'] = true;
            $resp['message'] = 'Lista de metrados';
            $resp['data'] = $metrados;
            $resp['url_imagen'] = "https://{$bucket}.s3.{$region}.amazonaws.com/";
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
        }
        return $resp;
    }

    private function uploadImage($user_id = 0)
    {
        $region = $_ENV['REGION_AWS'];
        $bucket = $_ENV['BUCKET_NAME'];

        $name       =   $_FILES["img"]["name"];
        $type       =   $_FILES["img"]["type"];
        $tmp_name   =   $_FILES["img"]["tmp_name"];
        $size       =   $_FILES["img"]["size"];
        $diskS3     =   new Storage();
        $key        =   'probudjet/metrado/images/' . time() . '_' . uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION);
        $result     =   $diskS3->storeAs($tmp_name, $key, $size);
        $data = [
            'nombre'    => $name,
            'formato'   => pathinfo($name, PATHINFO_EXTENSION),
            'tipo'      => $type,
            'peso'      => $size,
            'bucket'    => 'platform-owlfiles',
            'url'       => "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}" . $key,
            'size'      => FG::getZiseConvert($size),
            'user_id'   => $user_id
        ];
        $id = self::insert('archivos', $data)["lastInsertId"];
        $result['key'] = $key;
        return $result;
    }

    public function siNumeric($nro)
    {
        return ($nro) ? $nro : 1;
    }

    private function getMetradoParcial($metrado)
    {
        if ($metrado->is_information == 1) {
            return 0;
        }
        $matriz_parcial = [
            ($this->siNumeric($metrado->metrado_largo)),
            ($this->siNumeric($metrado->metrado_alto)),
            ($this->siNumeric($metrado->metrado_ancho)),
            ($this->siNumeric($metrado->metrado_area)),
            ($this->siNumeric($metrado->metrado_volumen)),
            ($this->siNumeric($metrado->metrado_cantidad)),
            ($this->siNumeric($metrado->metrado_nro_elemto)),
            ($this->siNumeric($metrado->metrado_factor))
        ];
        $parcial = array_product($matriz_parcial);
        return $parcial; // number_format($parcial, 2, '.', '');
    }

    private function updateMetradoPartida($metrado)
    {
        self::update("presupuestos", array(
            'metrado' => $metrado // number_format($metrado, 2, '.', '')
        ), array(
            'id' => $this->_id
        ));
    }

    public function getListPresupuestoMetrado()
    {

        $sql_general = "SELECT        
                        id,
                        descripcion AS 'name',
                        unidad_medidas_id AS 'uni',
                        proyecto_generales_id,
                        partidas_id,
                        subpresupuestos_id,
                        presupuestos_title_id,
                        metrado AS 'metered',
                        presupuestos_proyecto_generales_id,
                        nro_orden AS 'level',
                        type_item
                FROM presupuestos 
                WHERE proyecto_generales_id = :id  AND deleted_at is NULL
                AND subpresupuestos_id IN ({$this->_subpresupuestos_id})
                ORDER BY nro_orden ASC";
        $presupuestos_general = self::fetchAllObj($sql_general, ['id' => $this->_id]);

        $sql = "SELECT
                        id,
                        descripcion AS 'name',
                        metrado_largo,
                        metrado_ancho,
                        metrado_area,
                        metrado_alto,
                        metrado_volumen,
                        metrado_cantidad,
                        metrado_nro_elemto,
                        metrado_factor,
                        presupuestos_id,
                        proyecto_generales_id,
                        img,
                        is_information
                FROM metrado_partida_presupuestos
                WHERE proyecto_generales_id = :id ORDER BY position ASC";
        $metrados = self::fetchAllObj($sql, ['id' => $this->_id]);
        $data = array();
        foreach ($presupuestos_general as $key => $value) {
            if ($value->presupuestos_proyecto_generales_id == null || $value->presupuestos_proyecto_generales_id == 0) {
                $value->detail = $this->setMatrizPresupuestoCalculo($value->id, $presupuestos_general, $metrados);
                array_push($data, $value);
            }
        }

        $region = $_ENV['REGION_AWS'];
        $bucket = $_ENV['BUCKET_NAME'];

        $resp['success'] = true;
        $resp['message'] = 'Lista de metrados';
        $resp['data'] = $data;
        $resp['url_imagen'] = "https://{$bucket}.s3.{$region}.amazonaws.com/";

        return $resp;
    }


    public function setMatrizPresupuestoCalculo($searchedValue, $dataPresupuesto, $metrados)
    {
        $array = [];

        $object =   array_filter(
            $dataPresupuesto,
            function ($e) use ($searchedValue) {
                return $e->presupuestos_proyecto_generales_id == $searchedValue;
            }
        );

        foreach ($object as $key => $value) {
            array_push($array, $value);
        }

        $childrens = $array;
        if (count($childrens)) {
            foreach ($childrens as $key => $value) {
                if ($value->type_item == '3') {
                    $sValue = $value->id;
                    $object = array_filter(
                        $metrados,
                        function ($e) use ($sValue) {
                            return $e->presupuestos_id == $sValue;
                        }
                    );
                    $data = $this->getMetradoCalculado($object);
                    $childrens[$key]->detail = $data['metrados'];
                    $childrens[$key]->metered = $data['metered'];
                    continue;
                }
                $childrens[$key]->detail = $this->setMatrizPresupuestoCalculo($value->id, $dataPresupuesto, $metrados);
            }
        }
        return $childrens;
    }

    private function getMetradoCalculado($metrados)
    {
        $list = array();
        $metered = 0;

        foreach ($metrados as $key => $metrado) {
            $metrados[$key]->partial = $this->getMetradoParcial($metrado);
            $metered += $metrados[$key]->partial;
            $metrados[$key]->uni = '';
            $metrados[$key]->metered = '';
            array_push($list, $metrados[$key]);
        }

        $data = array(
            'metrados' => $list,
            'metered' => $metered // number_format($metered, 2, '.', '')
        );

        return $data;
    }

    public function getDelete()
    {
        $resp = [];
        try {
            $sql = 'SELECT presupuestos_id FROM metrado_partida_presupuestos WHERE id = :id';
            $metrado = self::fetchObj($sql, ['id' => $this->_id]);
            if ($metrado) {
                self::delete("metrado_partida_presupuestos", ['id' => $this->_id]);
                $this->_id = $metrado->presupuestos_id;
                $this->getListMetrado();
                $resp['success'] = true;
                $resp['message'] = 'Metrado eliminado';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'Metrado no existe';
            }
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
        }
        return $resp;
    }

    private function setNullableValues()
    {
        $valuesNullable = [];

        if ($this->_is_information) {
            $valuesNullable["metrado_largo"] = null;
            $valuesNullable["metrado_ancho"] = null;
            $valuesNullable["metrado_area"] = null;
            $valuesNullable["metrado_volumen"] = null;
            $valuesNullable["metrado_cantidad"] = null;
            $valuesNullable["metrado_nro_elemto"] = null;
            $valuesNullable["metrado_factor"] = null;
            $valuesNullable["metrado_alto"] = null;
            return $valuesNullable;
        }

        if (!$this->_metrado_largo) {
            $valuesNullable["metrado_largo"] = null;
        }
        // 'metrado_largo',
        if (!$this->_metrado_ancho) {
            $valuesNullable["metrado_ancho"] = null;
        }
        // 'metrado_ancho',
        if (!$this->_metrado_area) {
            $valuesNullable["metrado_area"] = null;
        }
        // 'metrado_area',
        if (!$this->_metrado_volumen) {
            $valuesNullable["metrado_volumen"] = null;
        }
        // 'metrado_volumen',
        if (!$this->_metrado_cantidad) {
            $valuesNullable["metrado_cantidad"] = null;
        }
        // 'metrado_cantidad',
        if (!$this->_metrado_nro_elemto) {
            $valuesNullable["metrado_nro_elemto"] = null;
        }
        // 'metrado_nro_elemto',
        if (!$this->_metrado_factor) {
            $valuesNullable["metrado_factor"] = null;
        }
        // 'metrado_factor',
        if (!$this->_metrado_alto) {
            $valuesNullable["metrado_alto"] = null;
        }
        // 'metrado_alto',

        return $valuesNullable;
    }
}
