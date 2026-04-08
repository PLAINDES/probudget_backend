<?php
//namespace model\utilitarian;
use Carbon\Carbon;

/**
 * Description of FuncionGlobal
 *
 * @author PLATAFORMA
 */
class FG {

    public static function VD($objeto) {
        echo '<pre>';
        var_dump($objeto);
        echo '<pre>';
        exit();
    }

    public static function _VD($objeto) {
        echo '<pre>';
        var_dump($objeto);
        echo '<pre>';
    }

    public static function W($string) {
        echo $string;
    }

    public static function WE($string) {
        exit($string);
    }

    public static function getFecha() {
        date_default_timezone_set('America/Lima');
        $fecha = date("d/m/Y");
        return $fecha;
    }

    public static function getCarbonDate($format="Y-m-d H:i:s", $days = false, $date = false) {
        $date = $date ? new Carbon($date)  : Carbon::now();
        $date->setTimezone('America/Lima');
        if (is_numeric($days)) {
            $date->addDays($days);
        }
        $fecha = $date->format($format);

        return $fecha;
    }

    public static function getCarbonUnixNow() {
        $date = Carbon::now();
        //$date->setTimezone('America/Lima');
        return $date->timestamp;
    }

    public static function getFechaHoraFormat() {
        $date = Carbon::now();
        $date->setTimezone('America/Lima');
        $fecha = $date->format('d-m-Y h:i A');
        return $fecha;
    }

    public static function getFechaMysql() {
        date_default_timezone_set('America/Lima');
        $fecha = date("Y-m-d");
        return $fecha;
    }

    public static function getHora() {
        date_default_timezone_set('America/Lima');
        $hora = $fecha = date("H:i:s");
        return $hora;
    }

    public static function getFechaHora($format = "Y-m-d H:i:s") {
        date_default_timezone_set('America/Lima');
        $fecha = date($format);
        return $fecha;
    }

    public static function getFormatFecha($fecha) {
        if (preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $fecha, $partes)) {
            $mes = 'de ' . FG::getFechaMes($partes[2]) . ' del ';
            return $partes[3] . " " . $mes . $partes[1];
        } else {
            // Si hubo problemas en la validación, devolvemos false
            return false;
        }
    }

    public static function getFormat($fecha) {
        $date = Carbon::createFromFormat('Y-m-d', '2018-08-17');
        $date = $date->format("d/m/Y");
        return $date;
    }

    public static function getFormatDate($fecha) {
        $date = Carbon::createFromFormat('d/m/Y', $fecha);
        $date = $date->format('Y-m-d');
        return $date;
    }

    public static function getFechaMes($num) {
        $meses = array('Error', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
        $num_limpio = $num >= 1 && $num <= 12 ? intval($num) : 0;
        return $meses[$num_limpio];
    }

    public static function dominio() {
        return $_SERVER['HTTP_HOST'];
    }

    public function base_url() {
        return sprintf(
            "%s://%s", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http', $_SERVER['HTTP_HOST']
        );

    }

    public function getYear() {
        $dt = Carbon::now();
        return $dt->year;
    }

    public function getMonth() {
        $dt = Carbon::now();
        return $dt->month;
    }

    public static function getStringTime($param) {
        $dt = Carbon::parse($param);
        return $dt->timestamp;
    }

    public function FechaHoraText($fecha, $titulo = "") {
        $segmentosFechaHora = explode(" ", $fecha);
        $segmenFecha = explode("-", $segmentosFechaHora[0]);
        $year = $segmenFecha[0];
        $mes = $segmenFecha[1];
        $mes = self::Mes($mes);
        $day = $segmenFecha[2];

        $dia = date("w", strtotime($fecha));
        switch ($dia) {
            case 0:
                $DiaText = "Domingo";
                break;
            case 1:
                $DiaText = "Lunes";
                break;
            case 2:
                $DiaText = "Martes";
                break;
            case 3:
                $DiaText = "Miercoles";
                break;
            case 4:
                $DiaText = "Jueves";
                break;
            case 5:
                $DiaText = "Viernes";
                break;
            case 6:
                $DiaText = "Sábado";
        }
        $date = new DateTime($fecha);
        $hora = $date->format('H:i a');
        $diaHoy = date('y-m-d');
        $segmentosDiaHoy = explode("-", $diaHoy);
        $segmMesHoy = $segmentosDiaHoy[1];
        $segmDiaHoy = $segmentosDiaHoy[2];
        $sieteDiasAtras = $segmentosDiaHoy[2] - 7;
        $tresDiasAtras = $segmentosDiaHoy[2] - 3;
        $fechaB = new DateTime($diaHoy);
        $fechaB->sub(new DateInterval('P7D'));
        $fechMenosSieteDias = $fechaB->format('Y-m-d');
        if ($titulo == '') {
            if ($fecha > $fechMenosSieteDias) {
                if ($segmDiaHoy == $day) {
                    $valor = "Hoy a la(s) " . $hora;
                } elseif ($segmDiaHoy - 1 == $day) {
                    $valor = "Ayer  a la(s) " . $hora;
                } elseif ($day >= $sieteDiasAtras && $day <= $tresDiasAtras) {
                    $valor = $DiaText . " a la(s) " . $hora;
                } else {
                    $valor = $day . " de " . $mes . " del " . $year . " a la(s) " . $hora;
                }
            } else {
                $valor = $day . " de " . $mes . " del " . $year . " a la(s) " . $hora;
            }
        } else {
            $valor = $DiaText . " " . $day . " de " . $mes . " del " . $year . " a la(s) " . $hora;
        }
        return $valor;
    }

    public function Mes($num) {
        $meses = array('Error', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
        $num_limpio = $num >= 1 && $num <= 12 ? intval($num) : 0;
        return $meses[$num_limpio];
    }

    public static function isNoNumericString($str) {
        return preg_match("/^[a-zA-Z áéíóúÁÉÍÓÚñÑ]+$/", $str);
    }

    public static  function debug($data = "", $debug = false) {
        $data = is_object($data) || is_array($data) ? json_encode($data) : $data;
        self::recordErrorLog($data, (($debug) ? $debug : debug_backtrace()) );
    }

    public static  function recordErrorLog($msg = "", $debug = false) {
        try {
            $mydebug = debug_backtrace();
            array_shift($mydebug);
            $debug = ($debug) ? $debug : $mydebug;

            $folder = __DIR__ . "/../../logs/";
            $fullpath = "{$folder}proeducative.log";
            if (!file_exists($fullpath)) {
                mkdir($folder, 0777); // create folder
                $log = fopen($fullpath, "c");
                fclose($log);
            }
            $debug = $debug[0];
            $date = self::getFechaHora();
            $fullmessage = "--- BEGIN " . $date . " ---\r\n";
            $fullmessage .= "FILE: " . $debug["file"] . "\r\n";
            $fullmessage .= "LINE: " . $debug["line"] . "\r\n";
            $fullmessage .= "CLASS: " . $debug["class"] . "\r\n";
            $fullmessage .= "FUNCTION: " . $debug["function"] . "\r\n";
            $fullmessage .= "MESSAGE: ".$msg . "\r\n";
            // $fullmessage .= "BACKTRACE: ".json_encode(debug_backtrace()) . "\r\n";
            $fullmessage .= "--- END " . $date . " ---\r\n\r\n";
            $text = file_get_contents($fullpath);
            $text = $fullmessage . $text;
            file_put_contents($fullpath, $text);
        } catch (Exception $e) {
            
        }
    }

    public static function validateDateFromFormat($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public static function hoursToMinutes($hours) {
        $minutes = 0;
        if (strpos($hours, ':') !== false) {
            list($hours, $minutes) = explode(':', $hours);
        }
        return $hours * 60 + $minutes;
    }

    public static function isEmail($str = "") {
        return filter_var($str, FILTER_VALIDATE_EMAIL);
    }

    public static function rand_string($characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", $lenght = 10) {
        $string = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < $lenght; $i++) {
            $string .= $characters[mt_rand(0, $max)];
        }
        return $string;
    }

    public static function cleanString($texto) {

        $temp = strtolower($texto);
        $b1 = array();
        $nueva_cadena = '';

        $ent = array('&aacute;', '&eacute;', '&iacute;', '&oacute;', '&oacute;', '&ntilde;');
        $entRep = array('á', 'é', 'í', 'ó', 'ú', 'ñ');

        $b = array('á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü', 'à', 'è', 'ì', 'ò', 'ù', 'ñ',
            ',', '.', ';', ':', '¡', '!', '¿', '?', '"', '_',
            '�?', 'É', '�?', 'Ó', 'Ú', 'Ä', 'Ë', '�?', 'Ö', 'Ü', 'À', 'È', 'Ì', 'Ò', 'Ù', 'Ñ');
        $c = array('a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n',
            '', '', '', '', '', '', '', '', '', '-',
            'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n');

        $temp = str_replace($ent, $entRep, $temp);
        $temp = str_replace($b, $c, $temp);
        $temp = str_replace($b1, $c, $temp);

        $new_cadena = explode(' ', $temp);

        foreach ($new_cadena as $cad) {
            $word = preg_replace("[^A-Za-z0-9]", "", $cad);
            if (strlen($word) > 0) {
                $nueva_cadena .= $word . '.';
            }
        }

        $nueva_cadena = substr($nueva_cadena, 0, strlen($nueva_cadena) - 1);

        return $nueva_cadena;
    }

    public static function getRealIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
            return $_SERVER['HTTP_CLIENT_IP'];

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            return $_SERVER['HTTP_X_FORWARDED_FOR'];

        return $_SERVER['REMOTE_ADDR'];
    }

    public static function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public static function quickRandom($length = 16)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }

    public static function fixSpecialCharecters($str,$except="",$replace=""){
        $replaced = str_replace(
                            ["á", "é", "í", "ó", "ú", "Á", "É", "Í", "Ó", "Ú", "ñ"],
                            ["a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "n"],
                            $str);
        return preg_replace('/[^A-Za-z0-9\\'.$except.']/', $replace, $replaced);

    }

    public static function isDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public static function slugify($str, $delimiter = '-') {
        $slug = strtolower(trim(preg_replace('/[\s-]+/', $delimiter, preg_replace('/[^A-Za-z0-9-]+/', $delimiter, preg_replace('/[&]/', 'and', preg_replace('/[\']/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $str))))), $delimiter));
        return $slug;
    }

    public static function encodeURI($url) {
        if(!$url)return $url; 

        $res = preg_match('/.*:\/\/(.*?)\//',$url,$matches);
        if($res){

            // except host name
            $url_tmp = str_replace($matches[0],"",$url);

            // except query parameter
            $url_tmp_arr = explode("?",$url_tmp);

            // encode each tier
            $url_tear = explode("/", $url_tmp_arr[0]);
            foreach ($url_tear as $key => $tear){
                $url_tear[$key] = rawurlencode($tear);
            }

            $ret_url = $matches[0].implode('/',$url_tear);

            // encode query parameter
            if(count($url_tmp_arr) >= 2){
                $ret_url .= "?".$this->encodeURISub($url_tmp_arr[1]);
            }
            return $ret_url;
        }else{
            return $this->encodeURISub($url);
        }
    }

    public function ExcelABC($i) {
        $str = "";
        $abc = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $labc= count($abc)-1;
        if ($i>$labc) {
            $ll  = intval($i / count($abc));
            $l   = $ll - 1;
            $i  -= (count($abc)*$ll);
            return $abc[$l]. FG::ExcelABC($i);
        } else {
            $str .= $abc[$i];
        }

        return $str;
    }

    public static function randPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array();
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }

    public static function genRandomCode($length = 4)
    {
        $random = "";
        srand((double) microtime() * 1000000);
        $data = "123456123456789071234567890890";
        for ($i = 0; $i < $length; $i++) {
            $random .= substr($data, (rand() % (strlen($data))), 1);
        }
        return $random;
    }

    public static function validateMatrizKey($cadena,$array, $default = "")
    {
         return (array_key_exists($cadena,$array))?$array[$cadena]:$default;
    }

    public static function siNumeric($nro) { return ($nro)?$nro:1; }

    public static function _crypt($string) {
       
        return crypt($string, '$2a$09$tARm1a9A9N7q1W9T9n5LqR$');
    }

    public static function getZiseConvert($bytes)
    {
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

    public static function alfanumerico($strength = 4)
    {
        $input = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $input_length = strlen($input);
        $random_string = '';
        for ($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }

        return $random_string;
    }

    public static function numberFormat($num, $decimal = 2) {
        return number_format($num, $decimal, '.', ',');
    }
    
}