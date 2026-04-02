<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$file = attachments::parse_filename(base64_decode($_GET['file']));
    		  	
if(!is_file($file['file_path']))
{
    die(TEXT_FILE_NOT_FOUD);
}

switch($app_module_action)
{
    case 'file_get_contents':
        header('Content-type: ' . $file['mime_type']);
        header('Content-Disposition: inline; filename="' . $file['name']. '"');
        echo file_get_contents($file['file_path']);
        exit();
        break;
}

if(is_file($file['file_path']) and $file['is_image'])
{          
    if(isset($_GET['rotate']))
    {
        attachments::rotate_image($file['file_path'], $_GET['rotate']);

        if(attachments::has_image_preview($file))
        {
            attachments::delete_image_preview($file);
            attachments::prepare_image_preview($file);
        }
    }

    if(!$size = getimagesize($file['file_path']))
    {
        exit();
    }

    $width = $size[0];
    $height = $size[1];

    $html = '';

    if(isset($_GET['windowWidth']))
    {
        $maxWidth = _GET('windowWidth') - (is_mobile() ? 70 : 170);

        if($width>$maxWidth)
        {
            //get percen differecne
            $diff = ($width - $maxWidth)/$width*100;

            $width  = $width - (($width/100)*$diff);
            $height  = $height - (($height/100)*$diff);
        }         
    }


    $html .=  '<img  width="' . $width . '" height="' . $height . '"  src="' . url_for('ext/app_chat/attachment_preview_img','action=file_get_contents&file=' . urlencode($_GET['file'])) . '">';

    echo $html;
  }

