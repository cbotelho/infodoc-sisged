<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$obj = array();

if(isset($_GET['id']))
{
	$obj = db_find('app_ext_xml_export_templates',$_GET['id']);
}
else
{
	$obj = db_show_columns('app_ext_xml_export_templates');
	$obj['is_active'] = 1;
	$obj['is_public'] = 1;
	
}