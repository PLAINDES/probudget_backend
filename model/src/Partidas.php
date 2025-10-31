<?php

require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql

class Partidas extends Mysql
{

  private $_id;
  public function getid()
  {
    return $this->_id;
  }
  private $_partidas;
  public function getpartidas()
  {
    return $this->_partidas;
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
  private $_unidad_medidas_id;
  public function getunidad_medidas_id()
  {
    return $this->_unidad_medidas_id;
  }
  private $_masterid;

  public function __construct($array = [])
  {
    $this->_id = FG::validateMatrizKey('id', $array, 0);
    $this->_partidas = FG::validateMatrizKey('partidas', $array);
    $this->_precio = FG::validateMatrizKey('precio', $array);
    $this->_tipo = FG::validateMatrizKey('tipo', $array);
    $this->_unidad_medidas_id = FG::validateMatrizKey('unidad_medidas_id', $array);
    $this->_masterid = FG::validateMatrizKey('masterid', $array);
  }

  public function getListPartida($request)
  {
    $map = [];

    $sql = 'SELECT id, partida, rendimiento, rendimiento_unid, unidad_medidas_id FROM partidas';
    $patidasMaster = self::fetchAllObj($sql);

    foreach ($patidasMaster as $key => $value) {
      $map[$value->id] = $key;
      $patidasMaster[$key]->apus = array();
    }

    $sql = 'SELECT ap.id, ap.cuadrilla, ap.cantidad, ap.insumo_id, 
              ap.partida_id, ap.unidad_medidas_id, im.codigo, im.tipo, im.insumos, im.precio
              FROM apus_partidas ap
              INNER JOIN insumos im ON ap.insumo_id = im.id';
    $apusMaster = self::fetchAllObj($sql);

    foreach ($apusMaster as $value) {
      $key = $map[$value->partida_id];
      array_push($patidasMaster[$key]->apus, $value);
    }

    $map = [];
    $sql = 'SELECT pp.id, pp.partida, pp.rendimiento, pp.rendimiento_unid, pp.unidad_medidas_id, pp.master_partida_id
              FROM partidas_proyecto pp
              WHERE pp.proyectos_generales_id = :id';
    $patidasProyecto = self::fetchAllObj($sql, ['id' => $request->proyectos_generales_id]);

    $data = array(
      'master' => $patidasMaster,
      'proyecto' => $patidasProyecto
    );

    $resp['success'] = true;
    $resp['message'] = 'Lista de partidas';
    $resp['data'] = $data;
    return $resp;
  }

  public function getSave($request)
  {
    $data = [];
    if ($request['id'] == '0' && $request['masterid'] == '0') {
      $rend = number_format(0, 2, '.', '');
      $var = [
        'partida' => $request['partida'],
        'rendimiento_unid' => $request['rendimiento_unid'],
        'unidad_medidas_id' => $request['unidad_medidas_id'],
        'proyectos_generales_id' => $request['proyecto_generales_id'],
      ];
      if ($request['rendimiento']) {
        $rend = $var['rendimiento'] = number_format($request['rendimiento'], 2, '.', '');
      }
      $lastInsert = self::insert("partidas_proyecto", $var);
      $data = array(
        'id' => $lastInsert["lastInsertId"],
        'rendimiento' => $rend,
        'rendimiento_unid' => $request['rendimiento_unid'],
        'unidad_medidas_id' => $request['unidad_medidas_id'],
      );
    } else {
      if ($request['id'] == '0' && $request['masterid'] != '0') {
        $sql = 'SELECT id, partida, rendimiento, rendimiento_unid, unidad_medidas_id 
                  FROM partidas_proyecto WHERE master_partida_id = :id AND proyectos_generales_id = :proyectos_generales_id';
        $partida = self::fetchObj($sql, ['id' => $request['masterid'], 'proyectos_generales_id' => $request['proyecto_generales_id']]);
        $rend =  number_format(0, 2, '.', '');
        if (empty($partida)) {
          $sql = 'SELECT id, partida, rendimiento, rendimiento_unid, unidad_medidas_id FROM partidas WHERE id = :id';
          $partida = self::fetchObj($sql, ['id' => $request['masterid']]);
          if ($partida) {
            $rend = number_format($partida->rendimiento, 2, '.', '');
            $lastInsert = self::insert("partidas_proyecto", array(
              'partida' => $partida->partida,
              'rendimiento' => $rend,
              'rendimiento_unid' => $partida->rendimiento_unid,
              'unidad_medidas_id' => $partida->unidad_medidas_id,
              'proyectos_generales_id' => $request['proyecto_generales_id'],
              'master_partida_id' => $partida->id
            ));
            $data['id'] = $lastInsert["lastInsertId"];
          }
        } else {
          $data['id'] = $partida->id;
        }
        $data['rendimiento'] = number_format($partida->rendimiento, 2, '.', '');
        $data['rendimiento_unid'] = $partida->rendimiento_unid;
        $data['unidad_medidas_id'] = $partida->unidad_medidas_id;
      } else {
        $sql = 'SELECT id, rendimiento, rendimiento_unid, unidad_medidas_id FROM partidas_proyecto WHERE id = :id';
        $partida = self::fetchObj($sql, ['id' => $request['id']]);
        if ($partida) {
          $data = array(
            'id' => $partida->id,
            'rendimiento' => number_format($partida->rendimiento, 2, '.', ''),
            'rendimiento_unid' => $partida->rendimiento_unid,
            'unidad_medidas_id' => $partida->unidad_medidas_id,
          );
        }
      }
    }
    return $data;
  }
}
