<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$obj = array();

if(isset($_GET['id']))
{
  $obj = db_find('app_ext_sms_rules',$_GET['id']);  
}
else
{
  $obj = db_show_columns('app_ext_sms_rules');
  $obj['number_of_days'] = 0;
  $obj['is_active'] = 0;
}