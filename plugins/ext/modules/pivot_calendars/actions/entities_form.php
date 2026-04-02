<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$obj = array();

if(isset($_GET['id']))
{
	$obj = db_find('app_ext_pivot_calendars_entities',$_GET['id']);
}
else
{
	$obj = db_show_columns('app_ext_pivot_calendars_entities');
        
        $obj['reminder_status'] = 0;
        $obj['reminder_type'] = 'popup';
        $obj['reminder_minutes'] = 15;
}