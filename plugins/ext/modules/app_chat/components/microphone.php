<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


$audioRecordingScript = url_for('ext/app_chat/audiorecording','assigned_to=' . $assigned_to . '&is_conversation=' . $is_conversation . '&attachments_form_token=' . $attachments_form_token);
$audioUploadScript  = url_for('ext/app_chat/audiorecording','assigned_to=' . $assigned_to . '&is_conversation=' . $is_conversation . '&action=upload&attachments_form_token=' . $attachments_form_token);

echo '<a href="' . $audioRecordingScript . '" audioUploadScript="' . $audioUploadScript . '" class="btn btn-default btn-microphone btn-microphone-chat"><i class="fa fa-microphone"></i></a>';
?>
<script>
$(".btn-microphone-chat").fancybox({
        type: "ajax",
        helpers: {
                overlay : {
                    closeClick: false
                }
            },
        beforeClose:function(){
            audiorecorder_form.resetRecordingTimer()
        }    
})
 
</script>