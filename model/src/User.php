<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of User
 *
 * @author AJAC
 */
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql
require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../utilitarian/EmailSES.php');
require_once(__DIR__ . '/../utilitarian/HelperJWT.php');

class User extends Mysql
{

  public function signUp($email, $accept_policies, $domain, $oauth_provider, $roleId, $password = null)
  {
    $resp = array();
    try {
      if ($this->findByEmail($email)->success) {
        $resp['success'] = false;
        $resp['id'] = $this->findByEmail($email)->id;
        $resp['message'] = 'El usuario ya se encuentra registrado';
        return $resp;
      }

      $password = FG::_crypt($password);
      $insert = self::insert("users", compact('email', 'accept_policies', 'oauth_provider', 'password', 'roleId'));
      if ($insert && $insert["lastInsertId"]) {
        $id = $insert["lastInsertId"];
        $confirm_email = '0';
        $resp['success'] = true;
        $resp['message'] = 'Usuario registrado';
        // $resp['data'] = compact('id', 'email', 'confirm_email', 'password_rand');
        $sql = 'SELECT id,email,first_name,last_name,picture, confirm_email, password AS password_rand  FROM users WHERE deleted_at IS NULL AND id = :id Limit 1';
        $resp['data'] = self::fetchArr($sql, compact('id'));
        $token    = HelperJWT::encode(['exp' =>  time() + 3700, 'id' =>  $id]);
        $url      = "{$domain}/user/confirm-email/{$token}";
        $asunto   = 'Confirmación de correo electrónico';
        $template = 'emails/confirmar-email.twig';
        // $this->sendEmail($resp['data'], $domain, $url, $asunto, $template);
      } else {
        $resp['success'] = false;
        $resp['message'] = 'Ocurrió un erro al registrar usuario';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function signUpdate($id, $email, $accept_policies, $domain, $oauth_provider, $password_rand = null)
  {
    $resp = array();
    try {
      $password = FG::_crypt($password_rand);

      $data = array(
        'email' => $email,
        'accept_policies' => $accept_policies,
        'oauth_provider' => $oauth_provider,
        'password' => $password
      );
      $where = array(
        'id' => $id
      );

      $data = self::update("users", $data, $where);

      $resp['success'] = true;
      $resp['message'] = 'updated of user successfully';
      $resp['data'] = $data;
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function emailRememberPassword($email)
  {
    $resp = array();
    try {
      if ($this->findByEmail($email)->success) {
        $code_password = FG::genRandomCode();
        $res = self::update("users", compact('code_password'), compact('email'));
        if ($res) {
          $data = ['email' => $email, 'code' => $code_password];
          $this->sendEmail($data, '', '', 'Restablecer contraseña', 'emails/remember-password.twig');
          $resp['success'] = true;
          $resp['message'] = 'Envio exitoso';
          $resp['data'] = $data;
        } else {
          $resp['success'] = false;
          $resp['message'] = 'Ocurrió un error, vuelva intentarlo';
        }
      } else {
        $resp['success'] = false;
        $resp['message'] = 'El usuario no existe';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function restorePassword($request)
  {
    $resp = array();
    try {
      $result = $this->findByCodePassword($request->code_password);
      if ($result->success) {
        $id = $result->data->id;
        $password =  FG::_crypt($request->newpassword);
        $code_password = null;
        $res = self::update("users", compact('password', 'code_password'), compact('id'));
        $resp['success'] = true;
        $resp['message'] = 'Contraseña restablecida';
        $resp['data'] = $result->data;
      } else {
        $resp['success'] = false;
        $resp['message'] = 'Codigo inválido';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function roles()
  {
    $resp = array();
    try {
      $sql = 'SELECT * FROM roles';
      $result = self::fetchAllObj($sql);
      $resp['success'] = true;
      $resp['data'] = $result;
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function signUpGmail($request)
  {
    $resp = array();
    try {
      $filter = array(
        'oauth_uid' => $request->id,
        'oauth_provider' => $request->oauth_provider
      );
      $data = $this->find($filter);
      if ($data->success) {
        return $data;
      }
      $data = array(
        'oauth_uid' => $request->oauth_uid,
        'email' => $request->email,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'gender' => $request->gender,
        'locale' => $request->locale,
        'picture' => $request->picture,
        'oauth_provider' => $request->oauth_provider,
        'confirm_email' => '1',
        'accept_policies' => $request->accept_policies,
        'created_at' => FG::getFechaHora(),
        'updated_at' => FG::getFechaHora()
      );
      $insert = self::insert("users", $data);
      if ($insert && $insert["lastInsertId"]) {
        $data['id'] = $insert["lastInsertId"];
        $resp['success'] = true;
        $resp['message'] = 'Usuario registrado';
        $resp['data'] = $data;
      } else {
        $resp['success'] = false;
        $resp['message'] = 'Ocurrió un erro al registrar usuario';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function signUpFacebook($request)
  {
    $resp = array();
    try {
      $filter = array(
        'oauth_uid' => $request->id,
        'oauth_provider' => $request->oauth_provider
      );
      $data = $this->find($filter);
      if ($data->success) {
        return $data;
      }
      $data = array(
        'oauth_uid' => $request->oauth_uid,
        'email' => $request->email,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'gender' => $request->gender,
        'picture' => $request->picture,
        'oauth_provider' => $request->oauth_provider,
        'confirm_email' => '1',
        'accept_policies' => $request->accept_policies,
        'created_at' => FG::getFechaHora(),
        'updated_at' => FG::getFechaHora()
      );
      $insert = self::insert("users", $data);
      if ($insert && $insert["lastInsertId"]) {
        $data['id'] = $insert["lastInsertId"];
        $resp['success'] = true;
        $resp['message'] = 'Usuario registrado';
        $resp['data'] = $data;
      } else {
        $resp['success'] = false;
        $resp['message'] = 'Ocurrió un erro al registrar usuario';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function confirmEmail($request)
  {
    $response   =   array();
    $decode     =   HelperJWT::decode($request->token);
    if (!$decode->success) {
      $response['success']        =   false;
      $response['message']        =   'Token inválido';
      return $response;
    }
    $id             = $decode->data->id;
    $confirm_email  = '1';
    self::update('users', compact('confirm_email'), compact('id'));
    $response['success']        =   true;
    $response['message']        =   'Correo validado';
    return $response;
  }

  private function findByEmail($email)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT COUNT(1) AS checked, id FROM users WHERE email = :email';
      $rs = self::fetchObj($sql, compact('email'));
      if ($rs->checked) {
        $resp->success = true;
        $resp->id = $rs->id;
        $resp->message = 'User found';
      } else {
        $resp->success = false;
        $resp->message = 'User does not exist';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function allUsers()
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id,email,first_name,last_name,picture,roleId FROM users WHERE deleted_at IS NULL';
      $rs = self::fetchAllObj($sql);
      if ($rs) {
        $resp->success = true;
        $resp->message = 'Lista de usuarios';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'No hay usuarios por mostrar';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function findByCodePassword($code_password)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id,email,first_name,last_name,picture FROM users WHERE code_password = :code_password';
      $rs = self::fetchObj($sql, compact('code_password'));
      if ($rs && $rs->id) {
        $resp->success = true;
        $resp->message = 'Codigo correcto';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'Codigo incorrecto';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  private function find($args)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id,email,first_name,last_name,picture FROM users WHERE oauth_uid = :oauth_uid AND oauth_provider = :oauth_provider';
      $rs = self::fetchObj($sql, $args);
      if ($rs && $rs->id) {
        $resp->success = true;
        $resp->message = 'Usuario registrado';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'User does not exist';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  private function sendEmail($user, $domain, $url, $mensaje_asunto, $template)
  {
    $destinatario_nombre    = isset($user["name"]) ? $user["name"] : '';
    $destinatario_nombre   .= isset($user["last_name"]) ? ' ' . $user["last_name"] : '';
    $parametros_ses = array(
      "destinatario_nombre"   => $destinatario_nombre,
      "destinatario_email"    => $user['email'],
      "mensaje_asunto"        => $mensaje_asunto,
      "emisor_email"          => '',
      "domain_company"        => $domain,
      'emisor_nombre'         => '',
      'password'              => isset($user['password_rand']) ? $user['password_rand'] : '',
      'code'              => isset($user['code']) ? $user['code'] : '',
    );
    $SES            =   new EmailSES();
    $email_info     =   $SES->enviarEmail('', '', '', '', $url, $parametros_ses, $template);
    $email_info["message"] = 'Correo enviado correctamente.';
    return $email_info;
  }

  public function listUser($request)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id,email,first_name,last_name,picture FROM users WHERE deleted_at IS NULL AND id != :userId';
      $rs = self::fetchAllObj($sql, ['userId' => $request->userId]);
      if ($rs) {
        $resp->success = true;
        $resp->message = 'Lista de usuarios';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'No hay usuarios por mostrar';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function sharedBudget($request)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id FROM usuarios_invitados WHERE userId = :userId AND proyectogeneralId = :budgetId';
      $rs = self::fetchObj($sql, ['userId' => $request->userId, 'budgetId' => $request->budgetId]);
      if (!$rs) {
        self::insert("usuarios_invitados", [
          'userId' => $request->userId,
          'proyectogeneralId' => $request->budgetId
        ]);
        $resp->message = 'Presupuesto compartido correctamente';
      } else {
        $resp->message = 'El Presupuesto ya está compartido';
      }
      $resp->success = true;
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function findUserEmail($request)
  {
    $resp = new stdClass();
    try {
      $email = $request->email;
      $sql = 'SELECT id,email,first_name,last_name,picture FROM users WHERE deleted_at IS NULL AND email = :email Limit 1';
      $rs = self::fetchObj($sql, compact('email'));
      if ($rs && $rs->id) {
        $resp->success = true;
        $resp->message = 'Usuario registrado';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'User does not exist';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function changePassword($userId, $newPassword)
  {
    $resp = [];
    try {
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $data = ['password' => $hashedPassword];
      $where = ['id' => $userId];
      $result = self::update('users', $data, $where);
      if ($result) {
        $resp['success'] = true;
        $resp['message'] = 'Contraseña actualizada correctamente';
      } else {
        $resp['success'] = false;
        $resp['message'] = 'No se pudo actualizar la contraseña';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function getUser($userId)
  {
    $resp = new stdClass();
    try {
      $sql = 'SELECT id, email, first_name, last_name, picture, password FROM users WHERE deleted_at IS NULL AND id = :userId LIMIT 1';
      $rs = self::fetchObj($sql, ['userId' => $userId]);
      if ($rs && $rs->id) {
        $resp->success = true;
        $resp->message = 'Usuario encontrado';
        $resp->data = $rs;
      } else {
        $resp->success = false;
        $resp->message = 'Usuario no existe';
      }
    } catch (\Throwable $th) {
      $resp->success = false;
      $resp->message = $th->getMessage();
    }
    return $resp;
  }

  public function updateUser($userId, $email, $roleId, $currentPassword, $newPassword)
  {
    $resp = [];
    try {
      // 1. Obtener usuario actual con su contraseña
      $sql = 'SELECT password FROM users WHERE id = :userId LIMIT 1';
      $user = self::fetchObj($sql, ['userId' => $userId]);

      if (!$user || empty($user->password)) {
        $resp['success'] = false;
        $resp['message'] = 'Usuario no encontrado';
        return $resp;
      }

      // 2. Validar contraseña actual
      if (!password_verify($currentPassword, $user->password)) {
        $resp['success'] = false;
        $resp['message'] = 'La contraseña actual es incorrecta';
        return $resp;
      }
      $password = FG::_crypt($newPassword);

      // 3. Actualizar datos
      $updateData = [
        'email' => $email,
        'roleId' => $roleId,
        'password' => $password
      ];
      $where = ['id' => $userId];
      $result = self::update('users', $updateData, $where);

      if ($result) {
        $resp['success'] = true;
        $resp['message'] = 'Usuario actualizado correctamente';
      } else {
        $resp['success'] = false;
        $resp['message'] = 'No se pudo actualizar el usuario';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function deleteUser($userId)
  {
    $resp = [];
    try {
      $where = ['id' => $userId];
      $result = self::delete('users', $where);
      if ($result) {
        $resp['success'] = true;
        $resp['message'] = 'Usuario eliminado correctamente';
      } else {
        $resp['success'] = false;
        $resp['message'] = 'No se pudo eliminar el usuario';
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $resp;
  }

  public function sharedBudgetMasivo($request)
{
    $resp = new stdClass();

    // DEBUG: log entrada completa
    error_log("==== DEBUG sharedBudgetMasivo ====");
    error_log("Request recibido: " . json_encode($request));

    try {
        $userId = $request->userId ?? null;
        $proyectos = $request->proyectos ?? null;

        error_log("UserId: " . json_encode($userId));
        error_log("Proyectos: " . json_encode($proyectos));

        if (!$userId) {
            $resp->success = false;
            $resp->message = 'El ID de usuario es requerido';
            error_log("ERROR: userId vacío");
            return $resp;
        }

        $isMultiple = is_array($proyectos);
        error_log("¿Asignación masiva?: " . ($isMultiple ? "SÍ" : "NO"));

        if ($isMultiple) {
            if (empty($proyectos)) {
                $resp->success = false;
                $resp->message = 'Debe seleccionar al menos un proyecto';
                error_log("ERROR: listado de proyectos vacío");
                return $resp;
            }

            $asignados = 0;
            $actualizados = 0;
            $errores = 0;

            foreach ($proyectos as $proyectoId) {
                error_log("Procesando proyecto ID: " . $proyectoId);

                try {
                    // Buscar asignación previa
                    $sql = 'SELECT id FROM usuarios_invitados 
                            WHERE userId = :userId AND proyectogeneralId = :budgetId';

                    $rs = self::fetchObj($sql, [
                        'userId' => $userId,
                        'budgetId' => $proyectoId
                    ]);

                    error_log("Resultado fetchObj: " . json_encode($rs));

                    if (!$rs) {
                        // Insertar
                        self::insert("usuarios_invitados", [
                            'userId' => $userId,
                            'proyectogeneralId' => $proyectoId,
                        ]);

                        error_log("Insertado proyectoId: {$proyectoId}");

                        $asignados++;

                    } else {
                        // Notas: la variable $tipo NO EXISTE, así que no actualiza
                        error_log("Asignación ya existente (ID {$rs->id}), no se actualiza tipo por estar comentado");
                    }
                } catch (\Throwable $th) {
                    $errores++;
                    error_log("ERROR procesando proyecto {$proyectoId}: " . $th->getMessage());
                }
            }

            $mensaje = "Se asignaron {$asignados} proyecto(s) correctamente";
            if ($actualizados > 0) $mensaje .= ", se actualizaron {$actualizados} asignación(es)";
            if ($errores > 0) $mensaje .= ". {$errores} proyecto(s) presentaron errores";

            $resp->message = $mensaje;
            $resp->success = true;

            error_log("RESULTADO FINAL MASIVO: " . json_encode($resp));

        } else {
            // Asignación simple
            $budgetId = $request->budgetId ?? $proyectos;

            error_log("Asignación simple. BudgetId = " . json_encode($budgetId));

            if (!$budgetId) {
                $resp->success = false;
                $resp->message = 'El ID del presupuesto es requerido';
                error_log("ERROR: budgetId vacío");
                return $resp;
            }

            $sql = 'SELECT id FROM usuarios_invitados WHERE userId = :userId AND proyectogeneralId = :budgetId';
            $rs = self::fetchObj($sql, [
                'userId' => $userId,
                'budgetId' => $budgetId
            ]);

            error_log("Resultado fetchObj simple: " . json_encode($rs));

            if (!$rs) {
                self::insert("usuarios_invitados", [
                    'userId' => $userId,
                    'proyectogeneralId' => $budgetId,
                ]);

                $resp->message = 'Presupuesto compartido correctamente';
                error_log("Insertado budgetId: {$budgetId}");
            } else {
                error_log("Asignación ya existe, no se actualiza tipo porque está comentado");
                $resp->message = 'El presupuesto ya está compartido';
            }

            $resp->success = true;
        }

    } catch (\Throwable $th) {
        $resp->success = false;
        $resp->message = $th->getMessage();
        error_log("ERROR GENERAL: " . $th->getMessage());
    }

    error_log("==== FIN DEBUG sharedBudgetMasivo ====");

    return $resp;
}


/**
 * Obtener proyectos asignados a un usuario específico
 */
public function getUserProjects($userId)
{
  $resp = new stdClass();
  try {
    $sql = "
      SELECT 
        ui.id,
        ui.userId,
        ui.proyectogeneralId,
        p.proyecto as proyecto_nombre,
        p.direccion as proyecto_descripcion
      FROM usuarios_invitados ui
      LEFT JOIN proyecto_generales p ON ui.proyectogeneralId = p.id
      WHERE ui.userId = :userId
      ORDER BY p.proyecto ASC
    ";
    
    $proyectos = self::fetchAllObj($sql, ['userId' => $userId]);
    
    $resp->success = true;
    $resp->data = $proyectos ? $proyectos : [];
    $resp->message = 'Proyectos cargados correctamente';
    
  } catch (\Throwable $th) {
    $resp->success = false;
    $resp->message = $th->getMessage();
    $resp->data = [];
  }
  
  return $resp;
}
/**
 * Eliminar asignación de proyecto a usuario (Model)
 */
public function removeUserProject($request)
{
    error_log("=== DEBUG Model removeUserProject ===");
    error_log("REQUEST OBJETO RECIBIDO: " . print_r($request, true));

    $resp = new stdClass();

    try {
        $assignmentId = $request->id ?? null;

        error_log("ID recibido en Modelo: " . $assignmentId);

        if (!$assignmentId) {
            $resp->success = false;
            $resp->message = 'El ID de asignación es requerido';
            error_log("ERROR: ID vacío en Modelo");
            return $resp;
        }

        // Verificar que existe la asignación
        $sql = 'SELECT id FROM usuarios_invitados WHERE id = :id';
        error_log("SQL Check: $sql, PARAMS: " . print_r(['id' => $assignmentId], true));

        $rs = self::fetchObj($sql, ['id' => $assignmentId]);

        error_log("Resultado SELECT usuarios_invitados: " . print_r($rs, true));

        if (!$rs) {
            $resp->success = false;
            $resp->message = 'Asignación no encontrada';
            error_log("ERROR: No existe usuarios_invitados.id = $assignmentId");
            return $resp;
        }

        // Eliminar la asignación
        error_log("Intentando eliminar usuarios_invitados ID: " . $assignmentId);

        $result = self::delete("usuarios_invitados", ['id' => $assignmentId]);

        error_log("Resultado DELETE: " . print_r($result, true));

        if ($result) {
            $resp->success = true;
            $resp->message = 'Asignación eliminada correctamente';
            error_log("ÉXITO: Eliminación ok");
        } else {
            $resp->success = false;
            $resp->message = 'No se pudo eliminar la asignación';
            error_log("ERROR: delete() retornó FALSE");
        }
        
    } catch (\Throwable $th) {
        $resp->success = false;
        $resp->message = $th->getMessage();
        error_log("EXCEPTION: " . $th->getMessage());
    }

    return $resp;
}


/**
 * Obtener todos los proyectos disponibles para asignar
 */
public function getAvailableProjects()
{
  $resp = new stdClass();
  try {
    $sql = "
      SELECT 
        id,
        proyecto,
        cliente,
        direccion
      FROM proyectos_generales
      WHERE deleted_at IS NULL
      ORDER BY proyecto ASC
    ";
    
    $proyectos = self::fetchAllObj($sql);
    
    $resp->success = true;
    $resp->data = $proyectos ? $proyectos : [];
    $resp->message = 'Proyectos disponibles';
    
  } catch (\Throwable $th) {
    $resp->success = false;
    $resp->message = $th->getMessage();
    $resp->data = [];
  }
  
  return $resp;
}

/**
 * Obtener estadísticas de asignaciones de un usuario
 */
public function getUserStats($userId)
{
  $resp = new stdClass();
  try {
    $sql = "
      SELECT 
        COUNT(*) as total_proyectos,
        SUM(CASE WHEN tipo = 'encargado' THEN 1 ELSE 0 END) as total_encargado,
        SUM(CASE WHEN tipo = 'copia' THEN 1 ELSE 0 END) as total_copia
      FROM usuarios_invitados
      WHERE userId = :userId
    ";
    
    $stats = self::fetchObj($sql, ['userId' => $userId]);
    
    if (!$stats) {
      $stats = (object)[
        'total_proyectos' => 0,
        'total_encargado' => 0,
        'total_copia' => 0
      ];
    }
    
    $resp->success = true;
    $resp->data = $stats;
    $resp->message = 'Estadísticas obtenidas';
    
  } catch (\Throwable $th) {
    $resp->success = false;
    $resp->message = $th->getMessage();
    $resp->data = null;
  }
  
  return $resp;
}

/**
 * Obtener todas las asignaciones con información detallada
 */
public function getAllAssignments()
{
  $resp = new stdClass();
  try {
    $sql = "
      SELECT 
        ui.id,
        ui.userId,
        ui.proyectogeneralId,

        u.email as usuario_email,
        u.first_name as usuario_nombre,
        u.last_name as usuario_apellido,
        p.proyecto as proyecto_nombre,
        p.direccion as proyecto_direccion,
        ui.created_at,
        ui.updated_at
      FROM usuarios_invitados ui
      LEFT JOIN users u ON ui.userId = u.id
      LEFT JOIN proyecto_generales p ON ui.proyectogeneralId = p.id
      WHERE u.deleted_at IS NULL
      ORDER BY ui.created_at DESC
    ";
    
    $asignaciones = self::fetchAllObj($sql);
    
    $resp->success = true;
    $resp->data = $asignaciones ? $asignaciones : [];
    $resp->message = 'Asignaciones obtenidas';
    
  } catch (\Throwable $th) {
    $resp->success = false;
    $resp->message = $th->getMessage();
    $resp->data = [];
  }
  
  return $resp;
}
}
