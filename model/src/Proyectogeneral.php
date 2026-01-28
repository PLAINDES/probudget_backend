<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../../model/src/Subcategoria.php');
require_once(__DIR__ . '/../src/Plan.php');

class Proyectogeneral extends Mysql
{

    private $_id;
    public function getid()
    {
        return $this->_id;
    }
    private $_users_id;
    public function getusers_id()
    {
        return $this->_users_id;
    }
    private $_proyecto;
    public function getproyecto()
    {
        return $this->_proyecto;
    }
    private $_cliente;
    public function getcliente()
    {
        return $this->_cliente;
    }
    private $_direccion;
    public function getdireccion()
    {
        return $this->_direccion;
    }
    private $_distrito;
    public function getdistrito()
    {
        return $this->_distrito;
    }
    private $_provincia;
    public function getprovincia()
    {
        return $this->_provincia;
    }
    private $_departamento;
    public function getdepartamento()
    {
        return $this->_departamento;
    }
    private $_pais;
    public function getpais()
    {
        return $this->_pais;
    }
    private $_area_geografica;
    public function getarea_geografica()
    {
        return $this->_area_geografica;
    }
    private $_fecha_base;
    public function getfecha_base()
    {
        return $this->_fecha_base;
    }
    private $_jornada_laboral;
    public function getjornada_laboral()
    {
        return $this->_jornada_laboral;
    }
    private $_moneda;
    public function getmoneda()
    {
        return $this->_moneda;
    }
    private $_proyecto_generalescol;
    public function getproyecto_generalescol()
    {
        return $this->_proyecto_generalescol;
    }
    private $_fecha_inicio;
    public function getfecha_inicio()
    {
        return $this->_fecha_inicio;
    }
    private $_fecha_fin;
    public function getfecha_fin()
    {
        return $this->_fecha_fin;
    }
    private $_costo_directo;
    public function getcosto_directo()
    {
        return $this->_costo_directo;
    }
    private $_categoriaId;
    public function getcategoriaId()
    {
        return $this->_categoriaId;
    }
    private $_subcategorias;
    public function getsubcategorias()
    {
        return $this->_subcategorias;
    }
    private $_newsubcategorias;
    public function getnewsubcategorias()
    {
        return $this->_newsubcategorias;
    }
    private $_values;
    public function getValue()
    {
        return $this->_values;
    }

    public function __construct($request)
    {

        if ($request) {
            $column = [
                'id',
                'users_id',
                'proyecto',
                'cliente',
                'direccion',
                'distrito',
                'provincia',
                'departamento',
                'pais',
                'area_geografica',
                'fecha_base',
                'jornada_laboral',
                'moneda',
                'proyecto_generalescol',
                'fecha_inicio',
                'fecha_fin',
                'costo_directo',
                'categoriaId',
                'subcategorias',
                'newsubcategorias'
            ];

            foreach ($column as  $value) {
                if (isset($request->{$value}) && !empty($request->{$value})) {
                    if ($value != "subcategorias" && $value != 'newsubcategorias') {
                        $this->_values[$value] = $request->{$value};
                    }
                    $this->{"_$value"} = $request->{$value};
                }
            }
        }
    }

    public function getSave()
    {
            error_log("=== DEBUG CATEGORIA ===");
    error_log("_values completo: " . print_r($this->_values, true));
    error_log("categoriaId directa: " . ($this->_categoriaId ?? 'NO DEFINIDA'));
    error_log("=====================");
        try {
            if ($this->_id) {
                $sql = 'SELECT COUNT(id) FROM proyecto_generales WHERE id = :id';
                $proyectoGeneral = self::fetchObj($sql, ['id' => $this->_id]);
                if ($proyectoGeneral) {
                    $update = self::update("proyecto_generales", $this->_values, ['id' => $this->_id]);
                    $resp['success'] = true;
                    $resp['message'] = 'Presupuesto registrado';
                    $resp['data'] = ['id' => $this->_id];
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'No se puede actualizar el registro';
                }
            } else {
                $plan = new Plan();
                $result = $plan->getValidate(['modulo' => 1, 'user_id' => $this->_values['users_id']]);
                if (!$result['success']) {
                    return $result;
                }
                $insert = self::insert("proyecto_generales", $this->_values);
                if ($insert && $insert["lastInsertId"]) {
                    $id = $insert["lastInsertId"];
                    $resp['success'] = true;
                    $resp['message'] = 'Proyecto general registrado';
                    $resp['data'] = compact('id');
                } else {
                    $resp['success'] = false;
                    $resp['message'] = 'Ocurrió un erro al registrar presupuesto';
                }
            }
            if ($this->_subcategorias) {
                $subcategoria   = new Subcategoria([
                    'subcategorias' => $this->_subcategorias,
                    'proyecto_generales_id' => ($this->_id) ? $this->_id : $insert['lastInsertId'],
                ]);
                $subcategoria->getCreateArray();
            }

            $resp['data'] = ($this->_id) ? $this->_id : $insert['lastInsertId'];

            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = $th->getMessage();
            return $resp;
        }
    }

    public function getProyectoGeneralId()
    {

        try {
            $sql = "SELECT  id,
                            users_id,
                            proyecto,
                            cliente,
                            direccion,
                            distrito,
                            provincia,
                            departamento,
                            pais,
                            area_geografica,
                            fecha_base,
                            jornada_laboral,
                            moneda,
                            'subcategorias' 
            FROM proyecto_generales WHERE id = :id";
            $proyectoGeneral = self::fetchObj($sql, ['id' => $this->_id]);

            $sql_departamento = 'SELECT id, descripcion FROM ub_departamentos WHERE id = :id';
            $proyectoGeneral->departamento = self::fetchObj($sql_departamento, ['id' => $proyectoGeneral->departamento]);

            $sql_provincias = 'SELECT id, descripcion FROM ub_provincias WHERE id = :id';
            $proyectoGeneral->provincia = self::fetchObj($sql_provincias, ['id' => $proyectoGeneral->provincia]);

            $sql_distritos = 'SELECT id, descripcion FROM ub_distritos WHERE id = :id';
            $proyectoGeneral->distrito = self::fetchObj($sql_distritos, ['id' => $proyectoGeneral->distrito]);


            $sql_subcategoria = 'SELECT id, descripcion, subcategorias_master_id
                                 FROM subcategorias_proyecto_general
                                 WHERE proyecto_generales_id = :proyecto_generales_id ORDER BY orden ASC';

            $proyectoGeneral->subcategorias = self::fetchAllObj($sql_subcategoria, ['proyecto_generales_id' => $this->_id]);
            return $proyectoGeneral;
        } catch (\Throwable $th) {
            return ["success" => false, "message" => $th->getMessage()];
        }
    }

    public function getListProyectoGeneral()
{
    // Obtener IDs de proyectos compartidos
    $sql = 'SELECT proyectogeneralId 
            FROM usuarios_invitados 
            WHERE userId = :userId';

    $rs = self::fetchAllObj($sql, ['userId' => $this->_users_id]);

    // Convertir a enteros y limpiar basura
    $compartidos = array_filter(array_map(function ($v) {
        return intval($v->proyectogeneralId);
    }, $rs));

    // Armar filtro
    $filter = 'pg.users_id = :users_id';

    if (!empty($compartidos)) {
        $inList = implode(',', $compartidos);
        $filter = "(pg.users_id = :users_id OR pg.id IN ($inList))";
    }

    // Query final
    $sql = "SELECT        
                pg.id,
                pg.users_id,
                pg.proyecto,
                pg.cliente,
                pg.direccion,
                pg.distrito,
                pg.provincia,
                pg.departamento,
                pg.pais,
                pg.area_geografica,
                pg.fecha_base,
                pg.jornada_laboral,
                pg.moneda,
                pg.proyecto_generalescol,
                pg.fecha_inicio,
                pg.fecha_fin,
                pg.costo_directo,
                pg.categoriaId,
                c.descripcion AS categoriaNombre
        FROM proyecto_generales pg 
        LEFT JOIN categorias c ON c.id = pg.categoriaId
        WHERE $filter 
        AND pg.deleted_at IS NULL
        ORDER BY pg.id ASC";

    return self::fetchAllObj($sql, ['users_id' => $this->_users_id]);
}



    public function getDelete()
    {
        try {
            $sql = 'SELECT id FROM proyecto_generales 
            WHERE id = :id  AND deleted_at is NULL';
            $proyecto_generales = self::fetchObj($sql, ['id' => $this->_id]);
            if ($proyecto_generales) {
                self::update('proyecto_generales', ['deleted_at' => date("Y-m-d H:i:s")], ['id' => $this->_id]);
                $resp['success'] = true;
                $resp['message'] = 'Se elimino el registro';
            } else {
                $resp['success'] = false;
                $resp['message'] = 'No se puede eliminar el registro';
            }
            return $resp;
        } catch (\Throwable $th) {
            $resp['success'] = false;
            $resp['message'] = 'No se puede eliminar el registro';
            return $resp;
        }
    }
}
