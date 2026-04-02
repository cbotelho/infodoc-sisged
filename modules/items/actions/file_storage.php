<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$filetype = _GETS('ft');

if(class_exists($filetype))
{        
    $filetype = new $filetype();
    
    switch ($app_module_action)
    {    
        case 'fs_upload':
            $filetype->upload($current_entity_id, _GET('field_id'));            
            break;
        case 'fs_preview':
            echo file_storage_field::preview($current_entity_id,_GET('field_id'),$_GET['form_token']??'',$current_item_id);            
            break;
        case 'fs_download':
            $filetype->download($current_entity_id, $current_item_id,_GET('field_id'),_GET('file'));
            break;
        case 'fs_delete':
            $filetype->delete($current_entity_id,_GET('field_id'),_POST('file'));            
            break;
    }
}

exit();
