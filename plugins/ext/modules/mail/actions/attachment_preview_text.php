<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$email_info_query = db_query("select * from app_ext_mail where groups_id='" . _get::int('id') . "' and accounts_id in (select accounts_id from app_ext_mail_accounts_users where users_id='" . $app_user['id'] . "') order by date_added limit 1");
if(!$email_info = db_fetch_array($email_info_query))
{
	redirect_to('dashboard/access_forbidden');
}


$file = mail_info::parse_attachment_filename(base64_decode($_GET['file']));

$download_url = url_for('ext/mail/info', 'id=' . $email_info['groups_id'] . '&action=download_attachment&file=' . urlencode(base64_encode($file['file'])));
    		  	
require(component_path('items/attachment_preview_text'));

