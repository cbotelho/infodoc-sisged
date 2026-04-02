<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$reports_query = db_query("select * from app_ext_ganttchart where id='" . str_replace('gantt','',$app_redirect_to) . "'");
$reports = db_fetch_array($reports_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_GANTTCHART_REPORT,url_for('ext/ganttchart/configuration')) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $reports['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $app_entities_cache[$reports['entities_id']]['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_QUICK_FILTERS_PANELS . '</li>';

?>

<ul class="page-breadcrumb breadcrumb">
  <?php echo implode('',$breadcrumb) ?>  
</ul>