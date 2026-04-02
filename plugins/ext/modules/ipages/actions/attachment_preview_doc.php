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

$attachments_viewer = new attachments_viewer($file);

$src = $attachments_viewer->get_url($file);

//yandex not support iframe
if(CFG_SERVICE_DOCX_PREVIEW=='docs.yandex.ru')
{    
    header('Location: ' . $src);
    exit();
}


$html = '    
        <iframe id="text_preview" class="attachment-previw-doc' . (is_mobile() ? '-mobile':''). '" src="' . $src  . '"></iframe>    
    ';

$html .= '    
        <script>   
        if(is_mobile)
        {
            $("#text_preview").css("width",($(window).width()-100)+"px").css("height",($(window).height()-150)+"px")
        }
        else
        {
            $("#text_preview").css("height",($(window).height()-200)+"px")
        }
        </script>
    ';
echo $html;

