<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$s = plugins::include_menu('extension');
      
if(count($s)>0)
{
  redirect_to('ext/ext/');
}