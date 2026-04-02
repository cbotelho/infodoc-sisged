<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'listing':
        if(is_mobile())
        {
            require(component_path('ext/call_history/listing_mobile'));
        }
        else
        {
            require(component_path('ext/call_history/listing'));
        }
        exit();
        break;
        
    case 'set_star':
        $id = _POST('id');
        $is_star = _POST('is_star');
        db_query("update app_ext_call_history set is_star={$is_star} where id={$id}");
        exit();
        break;
    
    case 'delete':
        $id = _POST('id');                
        db_delete_row('app_ext_call_history', $id);
        exit();
        break;
}

