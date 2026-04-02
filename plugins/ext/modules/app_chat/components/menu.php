<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if($app_chat->has_access)
{
	$app_plugin_menu['account_menu'][] = array('title'=>TEXT_EXT_CHAT_MESSAGES,'url'=>url_for('ext/app_chat/chat_window'),'class'=>'fa-comments');
}