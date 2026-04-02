<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php echo ajax_modal_template_header(TEXT_HEADING_DELETE) ?>

<?php echo form_tag('delete', url_for('ext/email_notification/rules','action=delete&entities_id=' . _get::int('entities_id') . '&id=' . $_GET['id'] )) ?>
    
<div class="modal-body">    
<?php 
$obj = db_find('app_ext_email_notification_rules',_GET('id'));  

echo sprintf(TEXT_DEFAULT_DELETE_CONFIRMATION,$obj['subject']) ?>
</div> 
 
<?php echo ajax_modal_template_footer(TEXT_BUTTON_DELETE) ?>

</form>    
    
 
