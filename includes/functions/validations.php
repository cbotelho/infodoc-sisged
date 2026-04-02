<?php

  /* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
  
  function app_validate_email($email) 
  {
    $email = trim($email);

    if ( strlen($email) > 255 ) 
    {
      $valid_address = false;
    } 
    elseif ( function_exists('filter_var') && defined('FILTER_VALIDATE_EMAIL') ) 
    {
      $valid_address = (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    } 
    else 
    {
      if ( substr_count( $email, '@' ) > 1 ) 
      {
        $valid_address = false;
      }

      if ( preg_match("/[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/i", $email) ) 
      {
        $valid_address = true;
      } 
      else 
      {
        $valid_address = false;
      }
    }

    return $valid_address;
  }
