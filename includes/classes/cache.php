<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class cache
{
  
  public static function create($filename,$content,$folder='/')
  {
    if ($fp = @fopen('cache' . $folder . $filename, 'w')) {
      fputs($fp, $content);
      fclose($fp);
    }
  }
}