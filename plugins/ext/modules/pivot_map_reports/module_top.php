<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

//check access
if($app_user['group_id']>0 and !in_array($app_module_path,['ext/pivot_map_reports/view','ext/pivot_map_reports/view_openstreetmap','ext/pivot_map_reports/view_google','ext/pivot_map_reports/view_yandex']))
{
  redirect_to('dashboard/access_forbidden');
}