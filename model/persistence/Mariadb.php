<?php

//namespace model\persistence;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Mariadb
 *
 * @author wheredia
 */
class Mariadb
{

  private static $db_server;
  private static $db_user;
  private static $db_password;
  private static $db_name;
  private static $cn;

  function __construct()
  {
    self::$db_server = $_ENV['DB_MDB_SERVER'];
    self::$db_user = $_ENV['DB_MDB_USER'];
    self::$db_password = $_ENV['DB_MDB_PASSWORD'];
    self::$db_name = $_ENV['DB_MDB_NAME'];
  }
  public static function setServer($server)
  {
    self::$db_server = $server;
  }
  public static function setUser($user)
  {
    self::$db_user = $user;
  }
  public static function setPassword($password)
  {
    self::$db_password = $password;
  }
  public static function setName($name)
  {
    self::$db_name = $name;
  }
  /**
   * 
   * @param string $server
   * @param string $dbname
   * @param string $user
   * @param string $password
   * @return \PDO
   */
  public static function PDOConnection($server = null, $dbname = null, $user = null, $password = null)
  {
    $SERVER = ($server) ? $server : self::$db_server;
    $DBNAME = ($dbname) ? $dbname : self::$db_name;
    $USER = ($user) ? $user : self::$db_user;
    $PASSWORD = ($password) ? $password : self::$db_password;


    try {
      $pdo = new PDO("mysql:host={$SERVER};dbname={$DBNAME}", $USER, $PASSWORD);
    } catch (PDOException $e) {
      die($e->getMessage());
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
  }

  public static function getPDOConnection()
  {
    self::$cn = self::isPDO(self::$cn) ? self::$cn : self::PDOConnection();
    return self::$cn;
  }

  /**
   * Anti Injection Method
   *
   * @return array query result
   * @param Query string database query
   * @param fields array params
   * @param pdo object PDO connection
   * */
  public static function fetchArr($Query, $fields = null, &$pdo = null)
  {
    $rsp = false;
    $stmt = self::_fetch($Query, $fields, $pdo);

    if ($stmt) {
      $rsp = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    //self::gc($pdo, $stmt);

    return $rsp;
  }

  /**
   * Anti Injection Method
   *
   * @return object query result
   * @param Query string database query
   * @param fields array params
   * @param pdo object PDO connection
   * */
  public static function fetchObj($sql, $fields = null, &$pdo = null)
  {
    $rsp = false;
    $stmt = self::_fetch($sql, $fields, $pdo);

    if ($stmt) {
      $rsp = $stmt->fetchObject();
    }
    if ($pdo) {
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $stmt);
      }
    }

    return $rsp;
  }

  /**
   * Anti Injection Method
   *
   * @return Array Arrays query result
   * @param Query string database query
   * @param fields array params
   * @param pdo object PDO connection
   * */
  public static function fetchAllArr($Query, $fields = null, &$pdo = null)
  {
    $rsp = false;
    $stmt = self::_fetch($Query, $fields, $pdo);
    if ($stmt) {
      $rsp = [];
      while ($arr = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rsp[] = $arr;
      }
    }

    //self::gc($pdo, $stmt);

    return $rsp;
  }

  /**
   * Anti Injection Method
   *
   * @return Array Objects query result
   * @param Query string database query
   * @param fields array params
   * @param pdo object PDO connection
   * */
  public static function fetchAllObj($sql, $fields = null, &$pdo = null)
  {
    $rsp = false;
    $stmt = self::_fetch($sql, $fields, $pdo);
    if ($stmt) {
      $rsp = [];
      while ($obj = $stmt->fetchObject()) {
        $rsp[] = $obj;
      }
    }
    if ($pdo) {
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $stmt);
      }
    }
    return $rsp;
  }

  /**
   * Anti Injection Method UPDATE
   * @param $tabla string: Nombre de tabla
   * @param $data array: Columnas y valores a actualizar
   * @param $where array: Columnas y valores de filtro
   * @param pdo object PDO connection
   * */
  public static function update($tabla, array $data, array $where, &$pdo = null)
  {
    $pdo = (self::isPDO($pdo)) ? $pdo : (self::isPDO(self::$cn) ? self::$cn : self::getPDOConnection());
    $whereArray = $setArray = array();
    $whereString = $setString = '';

    $tabla = (string) $tabla;
    $where = (array) $where;

    $rsp = false;

    if (!empty($tabla) && !empty($data) && !empty($where)) {

      $setArray = self::parseDataFilter($data, $pdo);
      $whereArray = self::parseDataFilter($where, $pdo);

      $setString = implode(', ', $setArray);
      $whereString = implode(' AND ', $whereArray);

      $sql = "UPDATE $tabla SET $setString WHERE $whereString";
      $query = $pdo->prepare($sql);

      try {

        foreach ($data as $name => &$value) {
          $value = ($value == null) ? "" : $value;
          $query->bindParam(":" . $name, $value);
        }
        foreach ($where as $name => &$value) {
          $value = ($value == null) ? "" : $value;
          $query->bindParam(":" . $name, $value);
        }

        $rsp = $query->execute();
      } catch (PDOException $e) {
        $debug = debug_backtrace();
        array_shift($debug);
        $str_fields = "\r\n";
        $fields = array_merge($data, $where);
        if (is_array($fields)) {
          foreach ($fields as $key => $value) {
            $str_fields .= "field:{$key} value: {$value}\r\n";
          }
        }

        die($e->getMessage());
      }
    }
    if ($pdo) {
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $query);
      }
    }

    return $rsp;
  }

  /**
   * Anti Injection Method INSERT
   * @param $data array: Columnas y valores a guardar en la tabla
   * @param pdo object PDO connection
   * */
  public static function insert($tabla, array $data, &$pdo = null)
  {
    $pdo = (self::isPDO($pdo)) ? $pdo : (self::isPDO(self::$cn) ? self::$cn : self::getPDOConnection());

    $values = array();
    $query = null;
    $tabla = (string) $tabla;
    $data = (array) $data;
    $return = array('success' => false, 'lastInsertId' => 0);


    if (!empty($tabla) && !empty($data)) {

      $values = self::parseDataFilter($data, $pdo);

      $valuesString = implode(', ', $values);

      $sql = "INSERT INTO $tabla SET $valuesString ";
      $query = $pdo->prepare($sql);

      try {

        foreach ($data as $name => &$value) {
          $value = ($value == null) ? "" : $value;
          $query->bindParam(":" . $name, $value);
        }

        $query->execute();
        $return['success'] = $query;
        $return['lastInsertId'] = $pdo->lastInsertId();
      } catch (PDOException $e) {

        $debug = debug_backtrace();
        array_shift($debug);
        $str_fields = "\r\n";
        if (is_array($data)) {
          foreach ($data as $key => $value) {
            $str_fields .= "field:{$key} value: {$value}\r\n";
          }
        }

        die($e->getMessage());
        print "Error!: " . $e->getMessage() . "</br>";
      }
    }
    if ($pdo) {
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $query);
      }
    }

    return $return;
  }

  /**
   * Anti Injection Method DELETE
   * @param $tabla string : nombre de la tabla
   * @param $where array: Columnas y valores para el where
   * @param pdo object PDO connection
   * */
  public static function delete($tabla, array $where, &$pdo = null)
  {

    $pdo = (self::isPDO($pdo)) ? $pdo : (self::isPDO(self::$cn) ? self::$cn : self::getPDOConnection());
    $tabla = (string) $tabla;
    $where = (array) $where;
    $query = null;
    $return = array('success' => false, 'lastInsertId' => 0);

    if (!empty($tabla) && !empty($where)) {

      $values = self::parseDataFilter($where, $pdo);

      $whereString = implode(' AND ', $values);

      $sql = "DELETE FROM `$tabla` WHERE $whereString ";
      $query = $pdo->prepare($sql);

      try {

        foreach ($where as $name => &$value) {
          $value = ($value == null) ? "" : $value;
          $query->bindParam(":" . $name, $value);
        }

        $return = $query->execute();
      } catch (PDOException $e) {
        $debug = debug_backtrace();
        array_shift($debug);
        $str_fields = "\r\n";
        if (is_array($where)) {
          foreach ($where as $key => $value) {
            $str_fields .= "field:{$key} value: {$value}\r\n";
          }
        }
        //self::gc($pdo, $query);
        die($e->getMessage());
        print "Error!: " . $e->getMessage() . "</br>";
      }
    }
    if ($pdo) {
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $query);
      }
    }
    return $return;
  }

  public static function delet($tabla, array $data, $pdo = null)
  {

    return self::delete($tabla, $data, $pdo);
  }

  public static function countrows($query, array $fields, &$pdo = null)
  {

    $rsp = false;
    $stmt = self::_fetch($query, $fields, $pdo);
    try {

      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $count = count($rows);
    } catch (PDOException $e) {
      //self::gc($pdo, $stmt);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }

    //self::gc($pdo, $stmt);

    return $count;
  }

  public static function countcolumn($query, array $fields, &$pdo = null)
  {

    $stmt = self::_fetch($query, $fields, $pdo);

    try {

      $count = $stmt->columnCount();
    } catch (PDOException $e) {
      //self::gc($pdo, $stmt);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }

    $return[] = $count;
    $return[] = $stmt;

    //Cerramos la conexión      
    //self::gc($pdo, $stmt);

    return $return;
  }

  public static function drop($tabla, &$pdo = null)
  {

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $sql = "DROP TABLE IF EXISTS $tabla  ";
    $query = $pdo->prepare($sql);

    try {

      $query->execute();
      $pdo = null;
    } catch (PDOException $e) {
      //self::gc($pdo, $query);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }
    //self::gc($pdo, $query);

    $return[] = $count;
    $return[] = $stmt;

    return $return;
  }

  private static function isPDO($arg = null)
  {
    return ($arg instanceof PDO);
  }

  private static function basicEval($query, $fields, $pdo)
  {

    return (is_array($fields) || is_null($fields)) && self::isPDO($pdo);
  }

  private static function _fetch($query, $fields = null, &$pdo = null)
  {
    $pdo = (self::isPDO($pdo)) ? $pdo : (self::isPDO(self::$cn) ? self::$cn : self::getPDOConnection());

    if (self::basicEval($query, $fields, $pdo)) {

      try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($fields);
      } catch (PDOException $e) {
        $str_fields = "";
        if (is_array($fields)) {
          foreach ($fields as $key => $value) {
            $str_fields .= "field:{$key} value: {$value}\r\n";
          }
        }
        $debug = debug_backtrace();
        array_shift($debug);

        die($e->getMessage());
      }
    }
    if (!$pdo->inTransaction()) {
      //self::gc($pdo, $query); // don't put $stmt 
    }

    return $stmt;
  }

  private static function parseDataFilter($data, &$pdo)
  {
    $values = [];

    try {
      foreach ($data as $key => $value) {
        //$names[] = (string) $key;

        $valor = $pdo->quote($value);
        $values[] = is_int($valor) ? $valor : "$key = :$key";
      }
    } catch (Exception $e) {
      //self::gc($pdo, $stmt);
    }

    return $values;
  }

  public static function ex($sql, &$pdo = null)
  {

    if (strlen($sql)) {
      $pdo = (self::isPDO($pdo)) ? $pdo : (self::isPDO(self::$cn) ? self::$cn : self::getPDOConnection());

      $query = $pdo->prepare($sql);

      try {
        $stmt = $query->execute();
      } catch (PDOException $e) {
        //self::gc($pdo, $query);
        die($e->getMessage());
      }
      if (!$pdo->inTransaction()) {
        //self::gc($pdo, $query);
      }
    }
    $return[] = $stmt;
    return $return;
  }

  /**
   * Garbage Collector
   * Connections & Statements Cleaner
   * @param $connection PDO Connection
   * @param $statement PDO statement
   * */
  private static function gc(&$connection, &$statement)
  {
    $connection = null;
    $statement = null;
  }

  public static function getPdoLocal()
  {
    return self::PDOConnection();
  }

  public static function getPDO()
  {
    return self::PDOConnection();
  }

  public static function InsertWithTransaction($tabla, array $data, &$pdo)
  {
    $values = array();
    $query = null;
    $tabla = (string) $tabla;
    $data = (array) $data;
    $return = array('success' => false, 'lastInsertId' => 0);

    $values = self::parseDataFilter($data, $pdo);

    $valuesString = implode(', ', $values);

    $sql = "INSERT INTO $tabla SET $valuesString ";
    $query = $pdo->prepare($sql);
    foreach ($data as $name => &$value) {
      $value = ($value == null) ? "" : $value;
      $query->bindParam(":" . $name, $value);
    }

    $query->execute();
    $return['lastInsertId'] = $pdo->lastInsertId();
    $query = null;
    return $return;
  }
}
