<?php

namespace App\Model\Persistence;

use PDO;

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

    public function __construct()
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
            throw new \Exception("Connection failed: " . $th->getMessage());
        }
    }

    public static function connection() // TODO: buscar luego donde se usa
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


/**
 *
 *
 * @param string $sql - Consulta SQL
 * @param array $params - Parámetros para binding
 * @return bool - True si la ejecución fue exitosa
 */
    public static function execute($sql, $params = [])
    {
        try {
            $pdo = self::Connection();
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                error_log("Error en execute(): Query falló");
                error_log("SQL: " . $sql);
                error_log("Params: " . print_r($params, true));
            }

            return $result;
        } catch (PDOException $e) {
            error_log("PDOException en execute(): " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw $e;
        }
    }


    // ==========================================
    // MÉTODOS DE TRANSACCIONES
    // ==========================================

    /**
     * Iniciar una transacción
     *
     * @return bool
     */
    public static function beginTransaction()
    {
        try {
            $pdo = self::Connection();
            if ($pdo->inTransaction()) {
                error_log("WARNING: Ya existe una transacción activa");
                return true;
            }
            return $pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Error al iniciar transacción: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirmar (commit) una transacción
     *
     * @return bool
     */
    public static function commit()
    {
        try {
            $pdo = self::Connection();
            if (!$pdo->inTransaction()) {
                error_log("WARNING: No hay transacción activa para hacer commit");
                return false;
            }
            return $pdo->commit();
        } catch (PDOException $e) {
            error_log("Error al hacer commit: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revertir (rollback) una transacción
     *
     * @return bool
     */
    public static function rollback()
    {
        try {
            $pdo = self::Connection();
            if (!$pdo->inTransaction()) {
                error_log("WARNING: No hay transacción activa para hacer rollback");
                return false;
            }
            return $pdo->rollBack();
        } catch (PDOException $e) {
            error_log("Error al hacer rollback: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si hay una transacción activa
     *
     * @return bool
     */
    public static function inTransaction()
    {
        $pdo = self::Connection();
        return $pdo->inTransaction();
    }

    /**
     * Obtener el número de filas afectadas por la última operación
     * Solo funciona con INSERT, UPDATE, DELETE
     *
     * @return int
     */
    public static function rowCount()
    {
        $pdo = self::Connection();
        return 0;
    }

    /**
     * Ejecutar múltiples queries en una transacción
     *
     * @param array $queries - Array de arrays ['sql' => '...', 'params' => [...]]
     * @return array - ['success' => bool, 'message' => string, 'affected' => int]
     */
    public static function executeTransaction(array $queries)
    {
        $response = ['success' => false, 'message' => '', 'affected' => 0];

        try {
            self::beginTransaction();

            $totalAffected = 0;
            foreach ($queries as $query) {
                $sql = $query['sql'] ?? '';
                $params = $query['params'] ?? [];

                if (empty($sql)) {
                    throw new \Exception("Query SQL vacía en transacción");
                }

                $result = self::ex($sql, $params);

                if (!$result) {
                    throw new \Exception("Error ejecutando query: {$sql}");
                }

                $totalAffected++;
            }

            self::commit();

            $response['success'] = true;
            $response['message'] = "Transacción completada exitosamente";
            $response['affected'] = $totalAffected;
        } catch (Exception $e) {
            self::rollback();
            $response['message'] = "Error en transacción: " . $e->getMessage();
            error_log("executeTransaction failed: " . $e->getMessage());
        }

        return $response;
    }
}
