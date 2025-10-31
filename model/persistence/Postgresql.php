<?php

/**
 * 
 */
class Postgresql
{

  private static $db_server;
  private static $db_user;
  private static $db_password;
  private static $db_name;

  function __construct()
  {
    self::$db_server = $_ENV['DB_PGSQL_SERVER'];
    self::$db_user = $_ENV['DB_PGSQL_USER'];
    self::$db_password = $_ENV['DB_PGSQL_PASSWORD'];
    self::$db_name = $_ENV['DB_PGSQL_NAME'];
  }

  /**
   * 
   * @param string $server
   * @param string $dbname
   * @param string $user
   * @param string $password
   * @return \PDO
   */
  private static function PDOConnection($server = null, $dbname = null, $user = null, $password = null)
  {
    $SERVER = ($server) ? $server : self::$db_server;
    $DBNAME = ($dbname) ? $dbname : self::$db_name;
    $USER = ($user) ? $user : self::$db_user;
    $PASSWORD = ($password) ? $password : self::$db_password;

    try {
      $pdo = new PDO("pgsql:host={$SERVER};dbname={$DBNAME}", $USER, $PASSWORD);
    } catch (PDOException $e) {
      die($e->getMessage());
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
  }

  public static function getPDO()
  {
    return self::PDOConnection();
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

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $rsp = false;
    $stmt = self::_fetch($Query, $fields, $pdo);

    if ($stmt) {
      $rsp = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    self::gc($pdo, $stmt);

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

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();
    $rsp = false;
    $stmt = self::_fetch($sql, $fields, $pdo);

    if ($stmt) {
      $rsp = $stmt->fetchObject();
    }
    self::gc($pdo, $stmt);

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
    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $rsp = false;
    $stmt = self::_fetch($Query, $fields, $pdo);
    if ($stmt) {
      $rsp = [];
      while ($arr = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rsp[] = $arr;
      }
    }

    self::gc($pdo, $stmt);

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

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $rsp = false;
    $stmt = self::_fetch($sql, $fields, $pdo);
    if ($stmt) {
      $rsp = [];
      while ($obj = $stmt->fetchObject()) {
        $rsp[] = $obj;
      }
    }

    self::gc($pdo, $stmt);

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

    //$pdo = ( self::isPDO($pdo) ) ? $pdo : self::PDOConnection();
    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();
    $whereArray = $setArray = array();
    $whereString = $setString = '';

    $tabla = (string) $tabla;
    $where = (array) $where;

    $rsp = false;

    $setArray = self::parseDataFilter($data, $pdo);
    $whereArray = self::parseDataFilter($where, $pdo);

    $setString = implode(', ', $setArray);
    $whereString = implode(' AND ', $whereArray);

    $sql = "UPDATE $tabla SET $setString WHERE $whereString";

    $query = $pdo->prepare($sql);

    try {

      foreach ($data as $name => &$value) {
        $value = ($value === null) ? "" : $value;
        $query->bindParam(":" . $name, $value);
      }
      foreach ($where as $name => &$value) {
        $value = ($value === null) ? "" : $value;
        $query->bindParam(":" . $name, $value);
      }

      $rsp['success'] = $query->execute();
    } catch (PDOException $e) {
      self::gc($pdo, $query);
      $rsp['success'] = false;
      $rsp['message'] = $e->getMessage();
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

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $values = array();
    $query = null;
    $tabla = (string) $tabla;
    $data = (array) $data;
    $rsp = array();

    $values = self::parseDataFilterInsert($data, $pdo);
    $columns = self::parseColumnsFilter($data);
    $valuesString = implode(', ', $values);
    $sql = "INSERT INTO $tabla ($columns) VALUES ( $valuesString )";

    $query = $pdo->prepare($sql);

    try {

      foreach ($data as $name => &$value) {
        $value = ($value === null) ? "" : $value;
        $query->bindParam(":" . $name, $value);
      }

      $query->execute();
      $rsp['success'] = $query;
      $rsp['affter_id'] = $pdo->lastInsertId();
    } catch (PDOException $e) {
      self::gc($pdo, $query);
      $rsp['success'] = false;
      $rsp['message'] = $e->getMessage();
    }

    return $rsp;
  }

  public static function UpSert($query, $fields, &$pdo)
  {
    $stmt = false;
    $rsp  = (object) array();
    try {
      $stmt = $pdo->prepare($query);
      $stmt->execute($fields);
      if ($stmt) {
        $rsp = $stmt->fetchObject();
        $rsp->success = true;
      }
    } catch (PDOException $e) {
      $rsp->success = false;
      $rsp->message = $e->getMessage();
    }
    return $rsp;
  }

  /**
   * Anti Injection Method DELETE
   * @param $tabla string : nombre de la tabla
   * @param $where array: Columnas y valores para el where
   * @param pdo object PDO connection
   * */
  public static function delete($tabla, array $where, &$pdo = null)
  {

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();
    $tabla = (string) $tabla;
    $where = (array) $where;
    $query = null;
    $return = array('success' => false, 'lastInsertId' => 0);
    $values = self::parseDataFilter($where, $pdo);

    $whereString = implode(' AND ', $values);

    $sql = "DELETE FROM $tabla WHERE $whereString ";
    $query = $pdo->prepare($sql);

    try {

      foreach ($where as $name => &$value) {
        $value = ($value == null) ? "" : $value;
        $query->bindParam(":" . $name, $value);
      }

      $return['success'] = $query->execute();
    } catch (PDOException $e) {
      self::gc($pdo, $query);
      $return['success'] = false;
      $return['message'] = $e->getMessage();
    }
    self::gc($pdo, $query);
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
      self::gc($pdo, $stmt);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }

    self::gc($pdo, $stmt);

    return $count;
  }

  public static function countcolumn($query, array $fields, &$pdo = null)
  {

    $stmt = self::_fetch($query, $fields, $pdo);

    try {

      $count = $stmt->columnCount();
    } catch (PDOException $e) {
      self::gc($pdo, $stmt);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }

    $return[] = $count;
    $return[] = $stmt;

    //Cerramos la conexión      
    self::gc($pdo, $stmt);

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
      self::gc($pdo, $query);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }
    self::gc($pdo, $query);

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
    $stmt = false;
    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    if (self::basicEval($query, $fields, $pdo)) {

      try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($fields);
      } catch (PDOException $e) {
        self::gc($pdo, $query);
        die($e->getMessage());
      }
    }

    self::gc($pdo, $query); // don't put $stmt 

    return $stmt;
  }

  private static function parseDataFilter($data, &$pdo)
  {
    $values = [];
    try {
      foreach ($data as $key => $value) {
        $valor = $pdo->quote($value);
        $values[] = is_int($valor) ? $valor : "$key=:$key";
      }
    } catch (Exception $e) {
      self::gc($pdo, $stmt);
    }
    return $values;
  }

  private static function parseDataFilterInsert($data, &$pdo)
  {
    $values = [];
    try {
      foreach ($data as $key => $value) {
        $valor = $pdo->quote($value);
        $values[] = is_int($valor) ? $valor : ":$key";
      }
    } catch (Exception $e) {
      self::gc($pdo, $stmt);
    }
    return $values;
  }

  private static function parseColumnsFilter($data)
  {
    $columns = "";
    foreach ($data as $key => $value) {
      $columns .= "$key,";
    }
    return substr($columns, 0, -1);
  }

  public static function ex($sql, &$pdo = null)
  {

    $pdo = (self::isPDO($pdo)) ? $pdo : self::PDOConnection();

    $query = $pdo->prepare($sql);

    try {

      $query->execute();
    } catch (PDOException $e) {
      self::gc($pdo, $query);
      die($e->getMessage());
      print "Error!: " . $e->getMessage() . "</br>";
    }

    self::gc($pdo, $query);

    $return[] = $count;
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
}
