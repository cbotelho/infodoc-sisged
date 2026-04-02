<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
$obj = isset($_GET['id']) ? db_find('app_ext_process_form_tabs', _GET('id')) : db_show_columns('app_ext_process_form_tabs');
?>

<?php echo ajax_modal_template_header((isset($_GET['id']) ? TEXT_HEADING_EDIT_FORM_TAB : TEXT_HEADING_NEW_FORM_TAB)) ?>

<?php echo form_tag('forms_form', url_for('ext/processes/process_form', 'action=save_tab&process_id=' . _GET('process_id') . (isset($_GET['id']) ? '&id=' . $_GET['id'] : '')), array('class' => 'form-horizontal')) ?>
<div class="modal-body">
    <div class="form-body">



        <div class="form-group">
            <label class="col-md-3 control-label" for="name"><?php echo TEXT_NAME ?></label>
            <div class="col-md-9">	
                <?php echo input_tag('name', $obj['name'], array('class' => 'form-control input-large required')) ?>      
            </div>			
        </div>

        <div class="form-group">
            <label class="col-md-3 control-label" for="cfg_menu_title"><?php echo TEXT_ICON; ?></label>
            <div class="col-md-9">	
                <?php echo input_icon_tag('icon', $obj['icon'], array('class' => 'form-control input-large')); ?>                    
            </div>			
        </div>

        <div class="form-group">
            <label class="col-md-3 control-label"><?php echo TEXT_ICON_COLOR ?></label>
            <div class="col-md-9">
                <?php echo input_color('icon_color', $obj['icon_color']) ?>   
            </div> 
        </div>       

        <div class="form-group">
            <label class="col-md-3 control-label" for="name"><?php echo TEXT_DESCRIPTION ?></label>
            <div class="col-md-9">	
                <?php echo textarea_tag('description', $obj['description'], array('class' => 'editor')) ?>      
            </div>			
        </div>

    </div>
</div> 

<?php echo ajax_modal_template_footer() ?>

</form> 

<script>
    $(function ()
    {
        $('#forms_form').validate({
            submitHandler: function (form)
            {
                app_prepare_modal_action_loading(form)
                return true;
            }
        });
    });

</script>   


