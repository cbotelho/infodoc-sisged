<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(!mail_accounts::user_has_access())
{
    redirect_to('dashboard/access_forbidden');
}

switch($app_module_action)
{
    case 'upload':
        
        $verifyToken = $_GET['attachments_form_token'];
                
        audiorecorder::upload_mail($verifyToken);
        
        exit();
        break;
}

