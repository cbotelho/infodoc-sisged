<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if($app_module_path == 'items/info')
{
	$access_rules = new access_rules($current_entity_id, $current_item_id);

	if(users::has_access('repeat',$access_rules->get_access_schema()))
	{
		$app_plugin_menu['more_actions'][] = array('title'=>'<i class="fa fa-calendar-check-o"></i> ' . TEXT_EXT_REPEAT,'url'=>url_for('ext/recurring_tasks/repeat','path=' . $_GET['path']));
	}	
}

$tasks_query = db_query("select * from app_ext_recurring_tasks where created_by ='" . $app_user['id'] . "' limit 1 ");
if($tasks = db_fetch_array($tasks_query))
{
	$app_plugin_menu['account_menu'][] = array('title'=>TEXT_EXT_MY_RECURRING_TASKS, 'url'=>url_for('ext/recurring_tasks/my_recurring_tasks'), 'class'=>'fa-calendar-check-o');
}
	