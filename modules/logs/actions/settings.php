<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'reset':
        db_query("TRUNCATE app_logs");
        
        redirect_to('logs/settings');
        break;
}