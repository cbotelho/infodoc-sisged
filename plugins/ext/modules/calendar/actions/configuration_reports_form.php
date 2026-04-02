<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

//check access
if($app_user['group_id']>0)
{
  exit();
}

$obj = array();

if(isset($_GET['id']))
{
  $obj = db_find('app_ext_calendar',$_GET['id']);  
}
else
{
  $obj = db_show_columns('app_ext_calendar');
  $obj['default_view'] = 'month';
  $obj['time_slot_duration'] = '00:30:00';
  $obj['event_limit'] = 6;
  $obj['filters_panel'] = 'default';
    
  $obj['reminder_status'] = 0;
  $obj['reminder_minutes'] = 15;
  $obj['reminder_type'] = 'popup';
}