<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


$ipage_query = db_query("select * from app_ext_ipages where id='" .  _GET('id') . "' and (find_in_set(" . $app_user['group_id'] . ",users_groups) or find_in_set(" . $app_user['id'] . ",assigned_to))");
if(!$ipage = db_fetch_array($ipage_query))
{
    die(TEXT_FILE_NOT_FOUD);
}

$file = attachments::parse_filename(base64_decode($_GET['file']));
    		  	
if(!is_file($file['file_path']))
{
    die(TEXT_FILE_NOT_FOUD);
}

$download_url = url_for('ext/ipages/view', 'id=' . _GET('id') . '&action=download_attachment&file=' . urlencode(base64_encode($file['file'])));

require(component_path('items/attachment_preview_text'));
