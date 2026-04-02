<?php 
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

echo pivot_map_reports::render_legend($reports); 

switch(pivot_map_reports::get_map_type($reports['id']))
{
    case 'yandex':
        require(component_path('ext/pivot_map_reports/view_yandex'));
        break;
    case 'google':
        require(component_path('ext/pivot_map_reports/view_google'));
        break;
    case 'mapbbcode':
        require(component_path('ext/pivot_map_reports/view'));
        break;
}