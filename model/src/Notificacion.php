<?php

require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql

class Notificacion extends Mysql
{

    public function searchNotification($request)
    {
        $resp = [];
        $sql = "SELECT nt.id, np.id AS npid, nt.message, nt.title, nt.type_notify FROM notificacion_proyecto np
                INNER JOIN notificaciones nt ON np.notificacion_id = nt.id
                WHERE np.estado = '1' AND omitir = 'No' AND np.proyectos_generales_id = :id";
        $data = self::fetchObj($sql, ['id' => $request->id]);
        if ($data) {
            $resp['success'] = true;
            $resp['message'] = 'Notificaciones';
            $resp['data'] = $data;
        } else {
            $resp['success'] = false;
            $resp['message'] = 'No hay notificaciones';
        }
        return $resp;
    }

    public function skipNotification($request)
    {
        self::update("notificacion_proyecto", array(
            'omitir' => 'Si'
        ), ['id' => $request->id]);
        return array(
            'message' => 'Registro actualizado',
            'success' => true
        );
    }
}
