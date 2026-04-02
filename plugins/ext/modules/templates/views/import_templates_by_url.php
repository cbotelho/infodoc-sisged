<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php echo ajax_modal_template_header($template_info['name']) ?>

<?php echo form_tag('import', url_for('ext/templates/import_templates_by_url', 'action=import&id=' . $template_info['id'])) ?>

<div class="modal-body ajax-modal-width-1100">    
    <h2 class="form-section" style="margin-top:0"><?= TEXT_PREVIEW ?></h2>

    <div id="preview_content" style="width: 1068px; max-height: 500px; overflow: auto;"></div>

</div> 

<?php echo ajax_modal_template_footer(TEXT_BUTTON_IMPORT) ?>

</form>  

<script>
    $(function ()
    {
        $('#preview_content').html('<div class="ajax-loading"></div>');
        $('#preview_content').load('<?= url_for('ext/templates/import_templates_by_url', 'action=preview&id=' . $template_info['id']) ?>', function ()
        {
            $(window).resize()
        })

        $('#import').validate({
            submitHandler: function (form)
            {
                app_prepare_modal_action_loading(form)
                form.submit();
            }
        });
    })

</script>