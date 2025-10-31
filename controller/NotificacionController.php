<?php

require_once(__DIR__ . '/../model/src/Notificacion.php');

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