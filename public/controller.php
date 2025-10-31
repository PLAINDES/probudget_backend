<?php
require_once(__DIR__ . '/../controller/AuthController.php');
/**
 * Description of Controller
 *
 * @author wheredia
 */

class Controller
{
  public static function getController($controller, $action, $request, $response)
  {
    $resp = [];
    try {
      // $auth = apache_request_headers();
      // return $response->json($auth);
      // $auth = isset($auth["authorization"]) ? $auth["authorization"] : $auth["Authorization"];
      // $token = explode(" ", $auth)[1];
      // $authController = new AuthController();
      // $resp = $authController->checkToken($token);
      // if ($resp['success']) {
      if (true) {
        try {
          $controllerFile = __DIR__ . '/../controller/' . ucwords($controller) . 'Controller.php';
          require_once($controllerFile);
          $nameclass = ucwords($controller) . 'Controller';
          $instance = new $nameclass();
          $execute = array($instance, $action);
          $resp = call_user_func($execute, $request);          //code...
        } catch (\Throwable $th) {

          $resp['success'] = false;
          $resp['message'] = $th->getMessage();
        }
      }
    } catch (\Throwable $th) {
      $resp['success'] = false;
      $resp['message'] = $th->getMessage();
    }
    return $response->json($resp);
  }
}
