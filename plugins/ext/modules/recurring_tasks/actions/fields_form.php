<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

require(component_path('ext/recurring_tasks/check_access'));

$obj = array();

if(isset($_GET['id']))
{
	$obj = db_find('app_ext_recurring_tasks_fields',$_GET['id']);
}
else
{
	$obj = db_show_columns('app_ext_recurring_tasks_fields');
}