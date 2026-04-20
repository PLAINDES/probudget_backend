<?php

//require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
//require_once(__DIR__ . '/../utilitarian/FG.php');

namespace App\Model;

use App\Model\Utilitarian\FG;
use App\Model\Persistence\Mysql;

class Diametros extends Mysql
{
    public function getList()
    {
        $sql = 'SELECT * FROM diametros';
        $result = self::fetchAllObj($sql);
        $resp['success'] = true;
        $resp['data'] = $result;
        return $resp;
    }
}
