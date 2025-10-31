<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../persistence/Mariadb.php');
require_once(__DIR__ . '/../utilitarian/FG.php');

class Insumos extends Mysql
{

  private $_id;
  public function getid()
  {
    return $this->_id;
  }
  private $_insumos;
  public function getinsumos()
  {
    return $this->_insumos;
  }
  private $_precio;
  public function getprecio()
  {
    return $this->_precio;
  }
  private $_tipo;
  public function gettipo()
  {
    return $this->_tipo;
  }
  private $_indice_unificado;
  public function getindice_unificado()
  {
    return $this->_indice_unificado;
  }
  private $_unidad_medidas_id;
  public function getunidad_medidas_id()
  {
    return $this->_unidad_medidas_id;
  }

  public function __construct($array = [])
  {
    $this->_id = FG::validateMatrizKey('id', $array, 0);
    $this->_insumos = FG::validateMatrizKey('insumos', $array); //(array_key_exists('insumos',$array))?$array['insumos']:"";
    $this->_precio = FG::validateMatrizKey('precio', $array); //(array_key_exists('precio',$array))?$array['precio']:"";
    $this->_tipo = FG::validateMatrizKey('tipo', $array); //  (array_key_exists('tipo',$array))?$array['tipo']:"";
    $this->_indice_unificado = FG::validateMatrizKey('indice_unificado', $array); // (array_key_exists('indice_unificado',$array))?$array['indice_unificado']:"";
    $this->_unidad_medidas_id = FG::validateMatrizKey('unidad_medidas_id', $array); // (array_key_exists('unidad_medidas_id',$array))?$array['unidad_medidas_id']:"" ;        
  }

  public function getListInsumo($proyectos_generales_id)
  {
    $sql = 'SELECT insumos.id, insumos,tipo, unidad_medidas.alias AS unidad_medida, unidad_medidas.id AS unidad_medidas_id
              FROM insumos
              INNER JOIN unidad_medidas ON unidad_medidas.id = insumos.unidad_medidas_id ';
    $minsumos = self::fetchAllObj($sql);

    $sql = 'SELECT ipr.id, ipr.insumos,ipr.tipo,ipr.master_insumo_id, um.alias AS unidad_medida, um.id AS unidad_medidas_id
              FROM insumos_proyecto ipr
              INNER JOIN unidad_medidas um ON um.id = ipr.unidad_medidas_id WHERE ipr.proyectos_generales_id = :id AND ipr.master_insumo_id IS NULL';
    $pinsumos = self::fetchAllObj($sql, ['id' => $proyectos_generales_id]);

    $resp['success'] = true;
    $resp['message'] = '';
    $resp['data'] = array(
      'master' => $minsumos,
      'proyecto' => $pinsumos
    );
    return $resp;
  }

  public function getIndicesUnificados()
  {
    $sql = 'SELECT * FROM indice_unificado';
    $resp['success'] = true;
    $resp['data'] = self::fetchAllObj($sql);
    return $resp;
  }

  public function renameInsumo($request)
  {
    $response = [];
    $params = [
      'insumos' => $request->insumos,
    ];
    $sql = 'SELECT insumo_id FROM apus_partida_presupuestos WHERE id = :id AND deleted_at IS NULL';
    $insu = self::fetchObj($sql, ['id' => $request->id]);
    if ($insu) {
      self::update('insumos_proyecto', $params, ['id' => $insu->insumo_id]);
      $response['success'] = true;
      $response['message'] = 'Datos guardados';
      $response['data'] = $params;
    } else {
      $response['success'] = false;
      $response['message'] = 'El insumo no existe';
      $response['data'] = null;
    }

    return $response;
  }
}
