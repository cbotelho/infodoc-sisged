<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$app_users_cfg->set('app_chat_active_dialog','');

$obj = array();

if(isset($_GET['id']))
{
	$obj = db_find('app_ext_chat_conversations',_get::int('id'));
}
else
{
	$obj = db_show_columns('app_ext_chat_conversations');
}