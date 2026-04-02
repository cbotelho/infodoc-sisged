<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$field_id = _GET('field');
$file_id = _GET('file');
$token = _GETS('token');
$file_query = db_query("select * from app_onlyoffice_files where field_id={$field_id} and id={$file_id} and download_token='{$token}'");
if(!$file = db_fetch_array($file_query))
{
    die(TEXT_FILE_NOT_FOUD);
}

if(!is_file($filepath = DIR_WS_ONLYOFFICE . $file['folder'] . '/' . $file['filename']))
{
    die(TEXT_FILE_NOT_FOUD);
}

header("Content-type: " . mime_content_type($filepath));
header('Content-Disposition: filename="' . $file['filename'] . '"');

flush();

readfile($filepath);

exit();
