<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title"><?php echo sprintf(TEXT_COPY_GROUP_QUESTION, access_groups::get_name_by_id(_GET('id'))) ?></h4>
</div>


<?php echo form_tag('users_groups_form', url_for('users_groups/copy', 'action=copy&id=' . $_GET['id']), array('class' => 'form-horizontal')) ?>
<div class="modal-body">
    <div class="form-body">

        <p><?= sprintf(TEXT_COPY_GROUP_TIP,access_groups::get_name_by_id(_GET('id'))) ?></p>       
        <div class="form-group">
            <label class="col-md-3 control-label" for="name"><?php echo TEXT_NAME ?></label>
            <div class="col-md-9">	
                <?php echo input_tag('name', '', array('class' => 'form-control input-large required')) ?>
            </div>			
        </div>  

        <div class="form-group">
            <label class="col-md-3 control-label" for="sort_order"><?php echo TEXT_SORT_ORDER ?></label>
            <div class="col-md-9">	
                <?php echo input_tag('sort_order', '', array('class' => 'form-control input-small number')) ?>
            </div>			
        </div>
        
        <div class="form-group">
            <label class="col-md-3 control-label" for="name"><?php echo TEXT_ADMINISTRATOR_NOTE ?></label>
            <div class="col-md-9">	
                <?php echo textarea_tag('notes', '', array('class' => 'form-control')) ?>
            </div>			
        </div> 

    </div>
</div>

<?php echo ajax_modal_template_footer() ?>

</form> 

<script>
    $(function ()
    {
        $('#users_groups_form').validate();
    });

</script>   


