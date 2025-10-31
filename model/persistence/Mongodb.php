<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Mongodb
 *
 * @author wheredia
 */
class Mongodb
{

  private static $client;

  function __construct()
  {
    $host = $_ENV['MONGODB_SERVER'];
    $port =  $_ENV['MONGODB_PORT'];
    $user = $_ENV['MONGODB_USER'];
    $pass =  $_ENV['MONGODB_PASSWORD'];
    $database =  $_ENV['MONGODB_DATABASE'];
    self::$client = new MongoDB\Client("mongodb://{$user}:{$pass}@{$host}:{$port}/{$database}");
  }

  public static function Connection()
  {
    try {
      return self::$client->selectDatabase($_ENV['MONGODB_DATABASE']);
    } catch (\Throwable $th) {
      return $th->getMessage();
    }
  }
}
