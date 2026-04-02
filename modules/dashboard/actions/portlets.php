<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'set_status':
        
        $name = $_POST['name']??'';
        $status = _GET('status');
        portlets::set_status($name, $status);
        
        app_exit();
        break;
}
