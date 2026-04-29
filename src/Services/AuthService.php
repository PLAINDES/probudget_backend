<?php

namespace App\Services;

use App\Model\Persistence\Mysql;

class AuthService
{
    public static function isAdmin($userId)
    {
        $sql = "SELECT r.name
                FROM users u
                INNER JOIN roles r ON r.id = u.roleId
                WHERE u.id = :userId
                LIMIT 1";

        $rs = Mysql::fetchObj($sql, ['userId' => $userId]);

        if (!$rs) {
            return false;
        }

        return strtolower($rs->name) === 'admin';
    }
}
