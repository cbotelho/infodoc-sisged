<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$report_info_query = db_query("select * from app_ext_kanban where id='" . str_replace('kanban','',$app_redirect_to) . "'");
$report_info = db_fetch_array($report_info_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_KANBAN,url_for('ext/kanban/reports')) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $report_info['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $entity_info['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_FILTERS . '</li>';

