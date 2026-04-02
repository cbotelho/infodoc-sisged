<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
/**
 * add kanban reports to main menu
 */
$reports_query = db_query("select c.* from app_ext_kanban c, app_entities e where c.is_active=1 and e.id=c.entities_id and (find_in_set(" . $app_user['group_id'] . ",c.users_groups) or find_in_set(" . $app_user['id'] . ",c.assigned_to)) order by c.name");
while($reports = db_fetch_array($reports_query))
{
    $check_query = db_query("select id from app_entities_menu where find_in_set('kanban" . $reports['id'] . "',reports_list)");
    if(!$check = db_fetch_array($check_query))
    {
        if($reports['in_menu'])
        {
            $app_plugin_menu['menu'][] = array('title' => $reports['name'], 'url' => url_for('ext/kanban/view', 'id=' . $reports['id']), 'class' => 'fa-th');
        }
        else
        {
            $app_plugin_menu['reports'][] = array('title' => $reports['name'], 'url' => url_for('ext/kanban/view', 'id=' . $reports['id']));
        }
    }
}

/**
 * add kanban reports to items menu
 */
if(isset($_GET['path']))
{
    $entities_list = items::get_sub_entities_list_by_path($_GET['path']);

    if(count($entities_list))
    {

        $reports_query = db_query("select c.* from app_ext_kanban c, app_entities e where c.is_active=1 and e.id=c.entities_id and e.id in (" . implode(',', $entities_list) . ")  and (find_in_set(" . $app_user['group_id'] . ",c.users_groups) or find_in_set(" . $app_user['id'] . ",c.assigned_to)) order by c.name");

        while($reports = db_fetch_array($reports_query))
        {
            $path = app_get_path_to_report($reports['entities_id']);

            $app_plugin_menu['items_menu_reports'][] = array('title' => $reports['name'], 'url' => url_for('ext/kanban/view', 'id=' . $reports['id'] . '&path=' . $path));
        }
    }
}