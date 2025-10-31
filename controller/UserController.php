<?php

/**
 * Description of CursoController
 *
 * @author wheredia
 */
require_once(__DIR__ . '/../model/src/User.php');

class UserController
{

  public function signUp($request)
  {
    $email  = $request->email;
    $accept = $request->accept_policies;
    $domain = $request->domain;
    $oauth_provider = $request->oauth_provider;
    $password = $request->password ? $request->password : null;
    $roleId = $request->roleId; // Default role ID is 2 if not provided
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
}
