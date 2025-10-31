<?php

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

use Aws\S3\MultipartUploader;
/**
* @author A.joel
*/
class Storage {

    private $bucket = "platform-owlfiles";

    private $s3Client;

    function __construct($bucket = "platform-owlfiles") {

        $access = "";
        $secret = "";

        switch ($bucket) {
            case 'platform-owlfiles':
                $access = $_ENV['AWS_ACCES_KEY_S3'];
                $secret = $_ENV['AWS_SECRET_KEY_S3'];
                break;

            default:
                $access = $_ENV['AWS_ACCES_KEY_S3'];
                $secret = $_ENV['AWS_SECRET_KEY_S3'];
                break;
        }

        $credentials = new Credentials($access, $secret);
        $this->s3Client = new S3Client([
            'credentials' => $credentials,
            'version' => '2006-03-01',
            'region' => 'us-west-2'
        ]);
        $this->s3Client->registerStreamWrapper();
        $this->bucket = $bucket;
    }

    public function storeAs( $source, $key, $size ) {
        $response = [];
        if( $size ) {
            try {
                $uploader = new MultipartUploader($this->s3Client, $source, [
                    'bucket' => $this->bucket,
                    'key'    => $key,
                ]);                    
                $result = $uploader->upload();            
                $response["success"] = true;
                $response["data"] = $result['ObjectURL'];
    
            } catch (Aws\Exception\MultipartUploadException $e) {
                $response["success"] = false;
                $response["message"] = $e->getMessage();
            }
        } else {
            try {
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key'    => $key,
                    'SourceFile' => $source
                ]);
                $response["success"] = true;
                $response["data"] = $result['ObjectURL'];
            } catch (Aws\S3\Exception\S3Exception $e) {
                $response["success"] = false;
                $response["message"] = $e->getMessage();
            }
        }
        return $response;
    }

    public function _unlink($key) {
        return unlink("s3://{$this->bucket}/$key");
    }

    public function _file_exists($key) {
        return file_exists("s3://{$this->bucket}/$key");
    }

    public function _filesize($key) {
        return filesize("s3://{$this->bucket}/$key");
    }

    public function _readfile($key) {
        return readfile("s3://{$this->bucket}/$key");
    }

    public function getSaveFile($source, $dest) {
        return $manager = $this->s3Client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $source,
            'SaveAs' => $dest
        ]);
    }

    public function getSaveFolder($source, $dest) {
        $manager = new \Aws\S3\Transfer($this->s3Client, $source, "s3://{$this->bucket}/".$dest);
        return $manager->transfer();
    }

    public function setBucket($bucket) {
        $this->bucket = $bucket;
    }

    function getZiseConvert($bytes) {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", ".", strval(round($result, 2))) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    public function getExtencionArchivo($file_name) {

        $tmp = explode(".", $file_name);
        $extencion = end($tmp);

        $data = array();
        $data["extencion"] = $extencion;

        switch ($extencion) {
            case 'doc':
            case 'docx':
                $data["tipo"] = "Documento word";
                break;
            case 'xls':
            case 'xlsx':
            case 'xlsm':
                $data["tipo"] = "Documento excel";
                break;
            case 'ppt':
            case 'pptx':
            case 'ppsx':
                $data["tipo"] = "Documento powerpoint ";
                break;

            case 'mpp':
                $data["tipo"] = "Documento de proyectos";
                break;

            case 'pdf':
                $data["tipo"] = "Archivo PDF";
                break;

            case 'zip':
            case 'rar':
                $data["tipo"] = "Archivo comprimido";
                break;

            case 'jpg':
            case 'JPG':
                $data["tipo"] = "Imagen JPG";
                break;

            case 'png':
            case 'PNG':
                $data["tipo"] = "Imagen PNG";
                break;

            case 'gif':
            case 'GIF':
                $data["tipo"] = "Imagen GIF";
                break;

            case 'mp4':
            case 'm3u8':
                $data["tipo"] = "Video";
                break;

            default:
                $data["tipo"] = "Embebido";
                $data["extencion"] = "emb";
                break;
        }

        return $data;
    }

}