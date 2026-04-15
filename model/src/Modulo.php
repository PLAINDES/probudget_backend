<?php

require_once(__DIR__ . '/../persistence/Mysql.php');

class Modulo extends Mysql
{
    private $_id;
    public function getid()
    {
        return $this->_id;
    }

    private $_presupuesto_id;
    public function getpresupuesto_id()
    {
        return $this->_presupuesto_id;
    }

    private $_codigo;
    public function getcodigo()
    {
        return $this->_codigo;
    }

    private $_nombre;
    public function getnombre()
    {
        return $this->_nombre;
    }

    private $_descripcion;
    public function getdescripcion()
    {
        return $this->_descripcion;
    }

    private $_subtotal;
    public function getsubtotal()
    {
        return $this->_subtotal;
    }

    private $_estado;
    public function getestado()
    {
        return $this->_estado;
    }

    private $_values = [];
    public function getValue()
    {
        return $this->_values;
    }


    public function __construct($request)
    {
        if ($request) {
            $column = [
                'id',
                'presupuesto_id',
                'codigo',
                'nombre',
                'descripcion',
                'subtotal',
                'estado'
            ];

            foreach ($column as $value) {
                if (isset($request->{$value}) && $request->{$value} !== '') {
                    $this->_values[$value] = $request->{$value};
                    $this->{"_$value"} = $request->{$value};
                }
            }
        }
    }

    /**
     * Crear o actualizar un módulo
     */
    public function getSave()
    {
        try {
            if ($this->_id) {
                // Verificar si existe
                $sql = "SELECT id FROM modulos WHERE id = :id";
                $existe = self::fetchObj($sql, ['id' => $this->_id]);

                if ($existe) {
                    // Actualizar
                    $update = self::update("modulos", $this->_values, ['id' => $this->_id]);
                    return [
                        'success' => true,
                        'message' => 'Módulo actualizado correctamente',
                        'data' => ['id' => $this->_id]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'No se puede actualizar: módulo no existe'
                    ];
                }
            } else {
                // Insertar
                $insert = self::insert("modulos", $this->_values);

                if ($insert && isset($insert["lastInsertId"])) {
                    return [
                        'success' => true,
                        'message' => 'Módulo registrado correctamente',
                        'data' => ['id' => $insert["lastInsertId"]]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Error al registrar el módulo'
                    ];
                }
            }
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    /**
     * Obtener módulo por ID
     */
    public static function getById($id)
    {
        $sql = "SELECT * FROM modulos WHERE id = :id";
        return self::fetchObj($sql, ['id' => $id]);
    }

    /**
     * Obtener módulos de un presupuesto
     */
    public static function getByPresupuesto($presupuesto_id)
    {
        $sql = "SELECT * FROM modulos WHERE presupuesto_id = :presupuesto_id ORDER BY id ASC";
        return self::fetchAll($sql, ['presupuesto_id' => $presupuesto_id]);
    }

    /**
     * Listar todos los módulos
     */
    public static function getAll()
    {
        $sql = "SELECT * FROM modulos ORDER BY fecha_creacion DESC";
        return self::fetchAll($sql);
    }

    /**
     * Eliminar (soft delete opcional)
     */
    public static function deleteModulo($id)
    {
        $sql = "DELETE FROM modulos WHERE id = :id";
        $delete = self::delete($sql, ['id' => $id]);

        return [
            'success' => true,
            'message' => 'Módulo eliminado'
        ];
    }
}
