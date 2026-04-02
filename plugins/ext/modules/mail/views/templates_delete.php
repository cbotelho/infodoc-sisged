<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php echo ajax_modal_template_header(TEXT_HEADING_DELETE) ?>

<?php echo form_tag('login', url_for('ext/mail/templates','action=delete&id=' . _GET('id') )) ?>

<?php 
    $template_info = db_find('app_ext_mail_templates', _GET('id'))
?>
    
<div class="modal-body">    
<?php echo sprintf(TEXT_DEFAULT_DELETE_CONFIRMATION,$template_info['subject']) ?>
</div> 
 
<?php echo ajax_modal_template_footer(TEXT_BUTTON_DELETE) ?>

</form>  