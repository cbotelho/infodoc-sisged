<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$reports_query = db_query("select * from app_ext_resource_timeline where id='" . str_replace('resource_timeline','',$app_redirect_to) . "'");
$reports = db_fetch_array($reports_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_RESOURCE_TIMELINE,url_for('ext/resource_timeline/reports')) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $reports['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $app_entities_cache[$reports['entities_id']]['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_QUICK_FILTERS_PANELS . '</li>';

?>

<ul class="page-breadcrumb breadcrumb">
  <?php echo implode('',$breadcrumb) ?>  
</ul>