<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


$file = attachments::parse_filename(base64_decode($_GET['file']));
    		  	
if(!is_file($file['file_path']))
{
    die(TEXT_FILE_NOT_FOUD);
}

$download_url = url_for('ext/app_chat/chat','action=attachment_download&file=' . urlencode(base64_encode($file['file'])));

require(component_path('items/attachment_preview_text'));
