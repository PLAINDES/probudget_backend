<?php

require_once(__DIR__ . '/../utilitarian/FG.php');
require_once(__DIR__ . '/../src/Plan.php');
require_once(__DIR__ . '/../utilitarian/Storage.php');
require_once(__DIR__ . '/../persistence/Mysql.php'); // Cambiado de Mariadb a Mysql

class Archivo extends Mysql
{

    public function getArchivoUploadStorage($args)
    {
        $rsp = ['success' => false, 'data' => null, 'message' => 'Ocurrió un error en el sistema, intentelo más tarde'];
        try {

            $file = $_FILES['file'];
            $user_id = $args['user_id'];

            if (!$file || !($file['error'] == UPLOAD_ERR_OK)) {
                throw new \Exception("No se pudo subir el archivo a la nube");
            }

            $plan = new Plan();
            $result = $plan->getValidate(['modulo' => 3, 'user_id' => $user_id, 'peso' => $file['size']]);
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            $folder = "/uploads/";
            $basepath  = __DIR__ . "/../../public";
            $fullpath  = $basepath . $folder;
            if (!is_dir($fullpath)) {
                mkdir($fullpath, 0777, true);
            }

            $name = $file['name'];
            $type = $file['type'];
            $size = $file['size'];
            $tmp_name = $file['tmp_name'];

            $diskS3     =   new Storage();
            $key        =   'probudjet/archivos/' . time() . '_' . uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION);
            $result     =   $diskS3->storeAs($tmp_name, $key, $size);

            //$path = $folder . time() . strtoupper(FG::alfanumerico()) . '-' . FG::slugify($name);
            $data = [
                'nombre'    => $name,
                'formato'   => pathinfo($name, PATHINFO_EXTENSION),
                'tipo'      => $type,
                'peso'      => $size,
                'bucket'    => 'platform-owlfiles',
                'url'       => $_ENV['URL_IMAGES'] . $key,
                'size'      => FG::getZiseConvert($size),
                'user_id'   => $user_id
            ];
            $finalpath = $basepath . $path;
            // move_uploaded_file($file['tmp_name'], "$finalpath");

            $id = self::insert('archivos', $data)["lastInsertId"];

            $file = self::fetchObj("SELECT*FROM archivos WHERE id = :id", compact('id'));

            $rsp['data'] = compact('file');
            $rsp['success'] = true;
            $rsp['message'] = 'Se subió correctamente';
        } catch (Exception $e) {
            $rsp['message'] = $e->getMessage();
        }
        return $rsp;
    }
}
