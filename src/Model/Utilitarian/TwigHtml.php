<?php

/**
 * Description of TwigHtml
 *
 * @author AJAC
 */

namespace App\Model\Utilitarian;

class TwigHtml
{
    public function view($uri, $param = array())
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../resources/views');
        $twig = new \Twig\Environment($loader);
        return $twig->render($uri, $param);
    }
}
