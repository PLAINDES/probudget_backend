<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(0);
require_once(__DIR__ . '/../vendor/autoload.php');
//require_once(__DIR__ . '/controller.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

date_default_timezone_set('America/Lima');

/**
 * Description of route
 *
 * @author wheredia
 */

$klein = new \Klein\Klein();

$klein->respond('GET', '/[:controller]?/[:action]?', function ($request, $response, $service) {
    return \App\Core\Controller::getController($request->controller, $request->action, $request, $response);
});

$klein->respond('POST', '/[:controller]?/[:action]?', function ($request, $response, $service) {
    return \App\Core\Controller::getController($request->controller, $request->action, $request, $response);
});

$klein->onHttpError(function ($code, $router) {
    switch ($code) {
        case 400:
            $router->response()->json(array('code' => $code, 'status' => 'Error 400'));
            break;
        case 403:
            $router->response()->json(array('code' => $code, 'status' => 'Error 403'));
            break;
        case 404:
            $router->response()->json(array('code' => $code, 'status' => 'Error 404'));
            break;
        case 405:
            $router->response()->json(array('code' => $code, 'status' => 'Método no permitido'));
            break;
        default:
            $router->response()->json(array('code' => $code, 'status' => 'Oh no, ocurrió un mal error que causó ' . $code));
            break;
    }
});

$klein->dispatch();
