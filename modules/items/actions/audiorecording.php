<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$field_id = _GET('field_id');

if($_GET['field_id']=='attachments')
{
    $entity_cfg = new entities_cfg($current_entity_id);
    if($entity_cfg->get('comments_allow_audio_recording',0)==0)
    {
        redirect_to_404();
    }
    
    $field_id = 'attachments';
}
elseif(!isset_field($current_entity_id, $field_id))
{
    redirect_to_404();
}

switch($app_module_action)
{
    case 'upload':
        
        $verifyToken = md5($app_user['id'] . _GET('timestamp'));
        
        audiorecorder::upload($field_id, $verifyToken);
        
        exit();
        break;
}

