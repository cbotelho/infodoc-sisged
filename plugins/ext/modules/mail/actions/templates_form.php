<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(!mail_accounts::user_has_access())
{
	redirect_to('dashboard/access_forbidden');
}


$obj = array();

if(isset($_GET['id']))
{
    $obj = db_find('app_ext_mail_templates',_GET('id'));
        
}
else
{
    $obj = db_show_columns('app_ext_mail_templates');		
}