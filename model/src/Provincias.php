<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of User
 *
 * @author AJAC
 */
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class Provincias extends Mysql
{
  private $_id;
  public function getid()
  {
    return $this->_id;
  }
  private $_descripcion;
  public function getDescripcion()
  {
    return $this->_descripcion;
  }
  //   public function getid(){ return $this->_id; }


  public function __construct($array = [])
  {
    $this->_id = (array_key_exists('id', $array)) ? $array['id'] : "0";
    $this->_descripcion = (array_key_exists('descripcion', $array)) ? $array['descripcion'] : "";
  }
  public function getDepartamentos()
  {
    $sql = 'SELECT id, descripcion FROM ub_departamentos';
    $resp['success'] = true;
    $resp['message'] = '';
    $resp['data'] = self::fetchAllObj($sql);
    return $resp;
  }
  public function getProvincias()
  {
    $sql = 'SELECT id, descripcion FROM ub_provincias WHERE ub_departamentos_id = :ub_departamentos_id';
    $resp['success'] = true;
    $resp['message'] = '';
    $resp['data'] = self::fetchAllObj($sql, ['ub_departamentos_id' => $this->_id]);
    return $resp;
  }
  public function getDistritos()
  {
    $sql = 'SELECT id, descripcion FROM ub_distritos WHERE ub_provincias_id = :ub_provincias_id';
    $resp['success'] = true;
    $resp['message'] = '';
    $resp['data'] = self::fetchAllObj($sql, ['ub_provincias_id' => $this->_id]);
    return $resp;
  }
}
