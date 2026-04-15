<?php

/**
 * Description of UserController
 *
 * @author wheredia
 */

require_once(__DIR__ . '/../model/src/User.php');

class UserController
{
  /*
  public function test() {
    $password = FG::_crypt("1234567");
    return $password;
  }*/


    public function signUp($request)
    {
        $email  = $request->email;
        $accept = $request->accept_policies;
        $domain = $request->domain;
        $oauth_provider = $request->oauth_provider;
        $password = $request->password ? $request->password : null;
        $roleId = $request->roleId;
        $user   = new User();
        return $user->signUp($email, $accept, $domain, $oauth_provider, $roleId, $password);
    }

    public function roles()
    {
        $user   = new User();
        return $user->roles();
    }

    public function signUpdate($request)
    {
        $id  = $request->id;
        $email  = $request->email;
        $accept = $request->accept_policies;
        $domain = $request->domain;
        $oauth_provider = $request->oauth_provider;
        $password = $request->password ? $request->password : null;
        $user   = new User();
        return $user->signUpdate($id, $email, $accept, $domain, $oauth_provider, $password);
    }

    public function updateUser($request)
    {
        $id = $request->id;
        $email = $request->email;
        $roleId = $request->roleId;
        $currentPassword = $request->currentPassword;
        $newPassword = $request->newPassword;
        $user = new User();
        return $user->updateUser($id, $email, $roleId, $currentPassword, $newPassword);
    }

    public function allUsers()
    {
        $user   = new User();
        return $user->allUsers();
    }

    public function emailRememberPassword($request)
    {
        $email  = $request->email;
        $user   = new User();
        return $user->emailRememberPassword($email);
    }

    public function validateCodePassword($request)
    {
        $code_password  = $request->code_password;
        $user   = new User();
        return $user->findByCodePassword($code_password);
    }

    public function restorePassword($request)
    {
        $user   = new User();
        return $user->restorePassword($request);
    }

    public function signUpGmail($request)
    {
        $user   = new User();
        return $user->signUpGmail($request);
    }

    public function signUpFacebook($request)
    {
        $user   = new User();
        return $user->signUpFacebook($request);
    }

    public function confirmEmail($request)
    {
        $user   = new User();
        return $user->confirmEmail($request);
    }

    public function listUser($request)
    {
        $user   = new User();
        return $user->listUser($request);
    }

    public function sharedBudget($request)
    {
        $user   = new User();
        return $user->sharedBudget($request);
    }

    public function findUserEmail($request)
    {
        $user   = new User();
        return $user->findUserEmail($request);
    }

    public function changePassword($request)
    {
        $user = new User();
        return $user->changePassword($request->userId, $request->newPassword);
    }

    public function getUser($request)
    {
        $user = new User();
        return $user->getUser($request->userId);
    }

    public function deleteUser($request)
    {
        $user = new User();
        return $user->deleteUser($request->id);
    }

  /**
   * NUEVOS MÉTODOS PARA ASIGNACIÓN MASIVA
   */

  /**
   * Asignar proyectos a usuario de forma masiva
   * Soporta asignación simple y múltiple
   */
    public function sharedBudgetMasivo($request)
    {
        error_log("🔥 controller massivo backend: " . print_r($request, true));
        $userId = $request->userId ?? null;
        $proyectos = $request->proyectos ?? null;
      //$tipo = $request->tipo ?? 'encargado';

        $user = new User();
        return $user->sharedBudgetMasivo((object)[
        'userId' => $userId,
        'proyectos' => $proyectos,
      //  'tipo' => $tipo
        ]);
    }

  /**
   * Obtener proyectos asignados a un usuario
   */
    public function getUserProjects($request)
    {
        $userId = $_GET['userId'] ?? null;


        if (!$userId) {
            return [
            'success' => false,
            'message' => 'ID de usuario requerido',
            'data' => []
            ];
        }

        $user = new User();
        return $user->getUserProjects($userId);
    }

/**
 * Eliminar asignación de proyecto a usuario (Controller)
 */
    public function removeUserProject($request)
    {
        // Klein: obtener POST
        $postData = $request->paramsPost()->all();

        error_log("=== DEBUG removeUserProject Controller (Klein) ===");
        error_log("POST DATA: " . print_r($postData, true));

        $assignmentId = $postData['id'] ?? null;

        if (!$assignmentId) {
            return [
            'success' => false,
            'message' => 'ID de asignación requerido'
            ];
        }

        $user = new User();
        return $user->removeUserProject((object)['id' => $assignmentId]);
    }


  /**
   * Obtener proyectos disponibles para asignar
   */
    public function getAvailableProjects()
    {
        $user = new User();
        return $user->getAvailableProjects();
    }

  /**
   * Obtener estadísticas de asignaciones de un usuario
   */
    public function getUserStats($request)
    {
        $userId = $request->userId ?? null;

        if (!$userId) {
            return [
            'success' => false,
            'message' => 'ID de usuario requerido',
            'data' => null
            ];
        }

        $user = new User();
        return $user->getUserStats($userId);
    }

  /**
   * Obtener todas las asignaciones con detalles
   */
    public function getAllAssignments()
    {
        $user = new User();
        return $user->getAllAssignments();
    }
}
