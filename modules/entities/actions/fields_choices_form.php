<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$obj = array();

if(isset($_GET['id']))
{
  $obj = db_find('app_fields_choices',$_GET['id']);  
}
else
{
  $obj = db_show_columns('app_fields_choices');
  $obj['is_active'] = 1;
}

$fields_info = db_find('app_fields',$_GET['fields_id']);