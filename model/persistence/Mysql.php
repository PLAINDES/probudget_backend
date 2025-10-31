<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Mysql
 *
 * @author wheredia
 */
class Mysql
{
    private static $pdo = null;

    function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        $dbname = $_ENV['DB_NAME'] ?? '';
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8";
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            ]);
        } catch (\Throwable $th) {
            throw new Exception("Connection failed: " . $th->getMessage());
        }
    }

    public static function Connection()
    {
        if (self::$pdo === null) {
            // Ensure the constructor is called at least once
            new self();
        }
        return self::$pdo;
    }

    public static function fetchObj($sql, $params = [])
    {
        $pdo = self::Connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public static function fetchArr($sql, $params = [])
    {
        $pdo = self::Connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function fetchAllArr($sql, $params = [])
    {
        $pdo = self::Connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchAllObj($sql, $params = [])
    {
        $pdo = self::Connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function insert($tabla, array $data)
    {
        $pdo = self::Connection();
        $fields = array_keys($data);
        $placeholders = array_map(function ($f) {
            return ":$f";
        }, $fields);
        $sql = "INSERT INTO $tabla (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $success = $stmt->execute();
        return [
            'success' => $success,
            'lastInsertId' => $pdo->lastInsertId()
        ];
    }

    public static function update($tabla, array $data, array $where)
    {
        $pdo = self::Connection();
        $set = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = :set_$k";
        }
        $w = [];
        foreach ($where as $k => $v) {
            $w[] = "$k = :w_$k";
        }
        $sql = "UPDATE $tabla SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $w);
        $stmt = $pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(":set_$k", $v);
        }
        foreach ($where as $k => $v) {
            $stmt->bindValue(":w_$k", $v);
        }
        return $stmt->execute();
    }

    public static function delete($tabla, array $where)
    {
        $pdo = self::Connection();
        $w = [];
        foreach ($where as $k => $v) {
            $w[] = "$k = :$k";
        }
        $sql = "DELETE FROM $tabla WHERE " . implode(' AND ', $w);
        $stmt = $pdo->prepare($sql);
        foreach ($where as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        return $stmt->execute();
    }
    public static function ex($sql, &$pdo = null)
    {
        $return = [];
        if (strlen($sql)) {
            $pdo = $pdo instanceof PDO ? $pdo : self::Connection();
            $query = $pdo->prepare($sql);
            try {
                $stmt = $query->execute();
            } catch (PDOException $e) {
                die($e->getMessage());
            }
            if (!$pdo->inTransaction()) {
                // Optionally clean up resources here
            }
            $return[] = $stmt;
        }
        return $return;
    }
}
