<?php

require_once(__DIR__ . '/../model/src/Provincias.php');

class ProvinciasController
{
  public function getDepartamentos()
  {
    $provincias   = new Provincias();
    return $provincias->getDepartamentos();
  }
  public function getProvincias($request)
  {
    $provincias = new Provincias(['id' => $request->id]);
    return $provincias->getProvincias();
  }
  public function getDistritos($request)
  {
    $provincias = new Provincias(['id' => $request->id]);
    return $provincias->getDistritos();
  }
}
