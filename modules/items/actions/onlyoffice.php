<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch ($app_module_action)
{    
    case 'upload':
        (new onlyoffice($current_entity_id))->upload();
        exit();
        break;
    case 'preview':
        echo (new onlyoffice($current_entity_id))->preview(_GET('field_id'),$_GET['form_token']??'',$current_item_id);
        exit();
        break;
    case 'download':
        onlyoffice::download($current_entity_id, $current_item_id,_GET('file'));
        break;
    case 'download_all':
        onlyoffice::download_all($current_entity_id, $current_item_id,_GET('field_id'));
        break;
    case 'delete':
        onlyoffice::delete(_POST('file'));
        exit();
        break;
}
