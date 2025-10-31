<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class Unidadmedida extends Mysql
{

  private $_id;
  public function getid()
  {
    return $this->_id;
  }
  private $_descripcion;
  public function getdescripcion()
  {
    return $this->_descripcion;
  }
  private $_alias;
  public function getalias()
  {
    return $this->_alias;
  }


  public function __construct($array = [])
  {
    $this->_id = FG::validateMatrizKey('id', $array, 0);
    $this->_descripcion = FG::validateMatrizKey('descripcion', $array); //(array_key_exists('descripcion',$array))?$array['descripcion']:"";
    $this->_alias = FG::validateMatrizKey('alias', $array); //(array_key_exists('alias',$array))?$array['alias']:"";       
  }

  public function getListUnidadMedida()
  {
    $sql = 'SELECT id, descripcion, apu_cantidad FROM unidad_medidas';
    $resp['success'] = true;
    $resp['message'] = '';
    $resp['data'] = self::fetchAllObj($sql);
    return $resp;
  }

  public function saveData($request)
  {
    $response = [];
    $params = [
      'descripcion' => $request->descripcion,
      'alias' => $request->descripcion,
    ];
    if ($request->id) {
      self::update('unidad_medidas', $params, ['id' => $request->id]);
      $response['success'] = true;
      $response['message'] = 'Datos guardados';
      $response['data'] = $params;
    } else {
      $found = $this->findUnit($request->descripcion);
      if ($found) {
        $response['success'] = false;
        $response['message'] = 'La unidad de medida ya existe';
        return $response;
      }
      $insert = self::insert("unidad_medidas", $params);
      if ($insert && $insert["lastInsertId"]) {
        $params['id'] = $insert["lastInsertId"];
        $response['success'] = true;
        $response['message'] = 'Unidad de medida registrada';
        $response['data'] = $params;
      } else {
        $response['success'] = false;
        $response['message'] = 'Ocurrió un erro al registrar unidad de medida';
      }
    }

    return $response;
  }

  public function findUnit($unit)
  {
    $sql = 'SELECT * FROM unidad_medidas WHERE LOWER(descripcion) = :unit';
    $found = self::fetchObj($sql, ['unit' => strtolower($unit)]);
    return $found;
  }
}
