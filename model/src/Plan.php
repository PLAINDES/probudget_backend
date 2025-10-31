<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');

class Plan extends Mysql
{

    public function getValidate($args)
    {


        $rsp = ['success' => false, 'message' => 'No sé proceso ninguna validación'];
        try {

            $user_id = $args['user_id'];
            $modulo = $args['modulo'];
            $store = isset($args['store']) ? $args['store'] : true;

            $sql = "SELECT 
                        US.id AS user_id,
                        PL.id AS plan_id,
                        PL.nombre AS plan,
                        PL.vigencia AS vigencia,
                        PR.id AS permiso_id,
                        PR.nombre AS permiso,
                        PP.cantidad AS cantidad
                    FROM planes_permisos AS PP
                    INNER JOIN planes AS PL ON PL.id = PP.plan_id
                    INNER JOIN permisos AS PR ON PR.id = PP.permiso_id
                    INNER JOIN users AS US ON PL.id = US.plan_id
                    WHERE US.id = :user_id";
            $permisos = self::fetchAllObj($sql, compact('user_id'));

            if (count($permisos) == 0) {
                $rsp['success'] = true;
                return $rsp;
            }

            function getPermiso($permisos, $id)
            {
                $permiso = null;
                foreach ($permisos as $key => $item) {
                    if (intval($item->permiso_id) == intval($id)) {
                        $permiso = $item;
                        break;
                    }
                }
                return $permiso;
            }

            switch (intval($modulo)) {
                case 1: // PROYECTOS
                    $permiso = getPermiso($permisos, 1);
                    if ($permiso) {
                        $sql = "SELECT PG.* FROM proyecto_generales AS PG WHERE PG.deleted_at IS NULL AND PG.users_id = :user_id";
                        $proyectos = self::fetchAllObj($sql, compact('user_id'));
                        $sql = 'SELECT proyectogeneralId FROM usuarios_invitados WHERE userId = :user_id';
                        $compartidos = self::fetchAllObj($sql, compact('user_id'));
                        $total = intval(count($proyectos)) + intval(count($compartidos)) + 1;
                        $cantidad = $permiso->cantidad;
                        if ($cantidad >= 0 && $total > $cantidad) {
                            throw new \Exception("Ya supero la cantidad de " . $cantidad . " proyectos permitidas");
                        }
                    }
                    break;
                case 2: // PARTIDAS
                    $permiso = getPermiso($permisos, 2);
                    if ($permiso) {
                        if (!isset($args['proyecto_id'])) {
                            throw new \Exception("Debe especificar el proyecto para validar las partidas");
                        }

                        $proyecto_id = $args['proyecto_id'];

                        $sql = "SELECT
                                    PR.*
                                FROM presupuestos AS PR
                                INNER JOIN proyecto_generales AS PG ON PR.proyecto_generales_id = PG.id
                                WHERE PG.deleted_at IS NULL
                                AND PR.deleted_at IS NULL
                                AND PG.users_id = :user_id
                                AND PG.id = :proyecto_id";

                        $partidas = self::fetchAllObj($sql, compact('user_id', 'proyecto_id'));
                        $total = intval(count($partidas));
                        $cantidad = $permiso->cantidad; // por ejemplo, 100

                        if ($cantidad >= 0 && $total >= $cantidad) {
                            throw new \Exception("Ya superó la cantidad de " . $cantidad . " partidas permitidas para este proyecto");
                        }
                    }
                    break;

                case 3: // ALMACENAMIENTO
                    $permiso = getPermiso($permisos, 3);
                    $peso = intval($args['peso']);
                    if ($permiso) {
                        $sql = "SELECT AR.* FROM archivos AS AR WHERE AR.deleted_at IS NULL AND AR.user_id = :user_id";
                        $archivos = self::fetchAllObj($sql, compact('user_id'));
                        $total = 0;
                        foreach ($archivos as $key => $item) {
                            $total = $total + $item->peso;
                        }
                        $postpeso = $total + $peso;
                        $cantidad = $permiso->cantidad;
                        if ($cantidad >= 0 && $postpeso > $cantidad) {
                            $convert = FG::getZiseConvert($cantidad);
                            throw new \Exception("Ya superó la cantidad de " . $convert . " de almacenamiento disponible");
                        }
                        $rsp['message'] = 'Almacenamiento disponible';
                    }
                    break;
            }
            $rsp['success'] = true;
        } catch (Exception $e) {
            $rsp['message'] = $e->getMessage();
        }
        return $rsp;
    }
}
