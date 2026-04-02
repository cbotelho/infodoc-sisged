<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(!mail_accounts::user_has_access())
{
    redirect_to('dashboard/access_forbidden');
}

switch($app_module_action)
{
    case 'save':
        $sql_data = array(
            'accounts_id' => $_POST['accounts_id'],
            'subject' => $_POST['subject'],
            'body' => $_POST['body'],            
        );

        if(isset($_GET['id']))
        {
            db_perform('app_ext_mail_templates', $sql_data, 'update', "id='" . db_input($_GET['id']) . "'");
        }
        else
        {
            db_perform('app_ext_mail_templates', $sql_data);
        }

        redirect_to('ext/mail/templates');
        break;
    case 'delete':

        db_delete_row('app_ext_mail_templates', _get::int('id'));

        redirect_to('ext/mail/templates');
        break;
    
    case 'apply':
        $template_query = db_query("select mf.*, ma.name as account_name from app_ext_mail_templates mf left join app_ext_mail_accounts ma on mf.accounts_id=ma.id where  mf.id = '" . _GET('id') . "' and mf.accounts_id in (select accounts_id from app_ext_mail_accounts_users where users_id='" . $app_user['id'] . "') order by mf.id");
        if($template = db_fetch_array($template_query))
        {
            $body = $template['body'];
            $accounts_id = _GET('accounts_id');            
            $signature_choices = mail_accounts::get_signature_choices();
            
            if(isset($signature_choices[$accounts_id]))
            {
                $body .= '<br><br>' . $signature_choices[$accounts_id];
            }
            
            echo json_encode(['subject'=>$template['subject'],'body'=>$body]);
        }
        else
        {
            echo json_encode(['subject'=>'','body'=>'']);
        }

        exit();
        break;        
}