<?php

//require_once(__DIR__ . '/../model/src/Notificacion.php');

namespace App\Controllers;

use App\Model\Notificacion;

class NotificacionController
{
    public function searchNotification($request)
    {
        $notificacion   = new Notificacion();
        return $notificacion->searchNotification($request);
    }

    public function skipNotification($request)
    {
        $notificacion   = new Notificacion();
        return $notificacion->skipNotification($request);
    }
}
