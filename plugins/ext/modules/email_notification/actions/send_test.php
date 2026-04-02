<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$rule_info_query = db_query("select * from app_ext_email_notification_rules where id=" . _GET('id'));
if(!$rule_info = db_fetch_array($rule_info_query))
{
    redirect_to('ext/email_notification/rules','entities_id=' . _GET('entities_id'));
}

switch ($app_module_action)
{
    case 'send':
        
        $email_notification_rules = new email_notification_rules($rule_info);
        $email_notification_rules->get_body();
        
        $send_to = [];
        $send_to[] = $_POST['email'];
        $subject =  str_replace(['${current_date}','${current_date_time}'],[format_date(time()), format_date_time(time())],$rule_info['subject']);
        users::send_to($send_to, $subject, $email_notification_rules->get_body());
        
        $alerts->add(TEXT_EMAIL_SENT,'success');
        
        redirect_to('ext/email_notification/rules','entities_id=' . _GET('entities_id'));
        
        break;
}