<?php

namespace App\Model\Utilitarian;

use Aws\Credentials\Credentials;
use Aws\Ses\Exception\SesException;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use App\Model\Utilitarian\TwigHtml;

class EmailSES
{
    /**
     * Funcion Envio de Correo atravez de AWS SES
     *
     * @param string $NomCuenta
     * @param string $email
     * @param string $asunto
     * @param string $body
     * @param string $filenamePDF
     * @param string $content
     * @param string $From
     * @param string $emailFrom
     * @return boolean
     */
    public function emailSES3($parametros_ses)
    {
        $resultado = [];
        try {
            $destinatario_nombre = $parametros_ses["destinatario_nombre"];
            $destinatario_email = $parametros_ses["destinatario_email"];
            $emisor_nombre = $parametros_ses["emisor_nombre"];
            $emisor_email = $parametros_ses["emisor_email"];
            $mensaje_asunto = $parametros_ses["mensaje_asunto"];
            $mensaje_cuerpo = $parametros_ses["mensaje_cuerpo"];

            $ses = \Aws\Ses\SesClient::factory(array(
                        'credentials' => new Credentials($_ENV['AWS_ACCES_KEY_SES'], $_ENV['AWS_SECRET_KEY_SES']),
                        'version' => 'latest',
                        'region' => 'us-west-2'
            ));

            $request = [];
            $request['Source'] = $emisor_email;
            $request['Destination']['ToAddresses'] = [urlencode($destinatario_nombre) . ' <' . $destinatario_email . '>'];
            $request['Message']['Subject']['Data'] = $mensaje_asunto;
            $request['Message']['Body']['Text']['Data'] = $mensaje_asunto;
            $request['Message']['Body']['Html']['Data'] = $mensaje_cuerpo;
            $result = $ses->sendEmail($request);
            $msg_id = $result->get('MessageId');
            $resultado = array(
                "success" => true,
                "message" => "El correo se envio correctamente"
            );
        } catch (SesException $e) {
            $resultado = array(
                "success" => false,
                "message" => $e->getMessage()
            );
        } catch (AwsException $e) {
            $resultado = array(
                "success" => false,
                "message" => $e->getMessage()
            );
        }
        return $resultado;
    }

    /**
     *
     * @param string $titulo
     * @param string $mensaje
     * @param string $subtitulo
     * @param string $submensaje
     * @param object $empresa
     * @param string $url
     * @return string
     */
    public function enviarEmail($titulo, $mensaje, $subtitulo, $submensaje, $url, $parametros_ses, $template)
    {

        $param = array(
            "color" => "red",
            "LogoCorreo" => $parametros_ses['domain_company'],
            "Logo2Correo_Html" => "imagen",
            "titulo" => $titulo,
            "mensaje" => $mensaje,
            "subtitulo" => $subtitulo,
            "submensaje" => $submensaje,
            "url" => $url,
            "userFullname" => $parametros_ses["destinatario_nombre"],
            "code" => $parametros_ses["code"]
        );

        if (isset($parametros_ses["password"])) {
            $param['password'] = $parametros_ses["password"];
        }

        $fTwig = new TwigHtml();
        $mensaje_cuerpo = $fTwig->view($template, $param);
        $parametros_ses["mensaje_cuerpo"] = $mensaje_cuerpo;

        if (!$parametros_ses["emisor_email"]) {
            $parametros_ses["emisor_email"] = "soporte@proeducative.org";
        }

        if (!$parametros_ses["emisor_nombre"]) {
            $parametros_ses["emisor_nombre"] = "Plaindes";
        }

        $email_info = $this->emailSES3($parametros_ses);

        return $email_info;
    }
}
