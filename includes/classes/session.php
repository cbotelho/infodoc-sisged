<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class session
{
  public static function get($key, $default='')
  {
    if(isset($_SESSION[$key]))
    {
      return $_SESSION[$key]; 
    }
    else
    {
      return $default;
    }
  }
}