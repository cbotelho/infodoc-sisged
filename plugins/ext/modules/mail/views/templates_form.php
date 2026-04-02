<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php echo ajax_modal_template_header(TEXT_EXT_MAIL_TEMPLATES) ?>

<?php echo form_tag('templates_form', url_for('ext/mail/templates','action=save' . (isset($_GET['id']) ? '&id=' . $_GET['id']:'') ),array('class'=>'form-horizontal')) ?>
<div class="modal-body">
  <div class="form-body">
      
        <div class="form-group">
            <label class="col-md-3 control-label" for="accounts_id"><?php echo TEXT_EXT_MAIL_ACCOUNT ?></label>
            <div class="col-md-9">	
                <?php echo select_tag('accounts_id',mail_accounts::get_choices_by_user(),$obj['accounts_id'],array('class'=>'form-control input-xlarge required')) ?>        
            </div>			
        </div>
      
        <div class="form-group">
            <label class="col-md-3 control-label" for="subject"><?php echo TEXT_EXT_EMAIL_SUBJECT ?></label>
            <div class="col-md-9">	
                <?php echo input_tag('subject', $obj['subject'], ['class' => 'form-control required']) ?>
            </div>			
        </div>
	  
        <div class="form-group">	
            <label class="col-md-3 control-label"><?php echo TEXT_EXT_MAIL_BODY ?></label>	 	
            <div class="col-md-9">	
                <?php echo textarea_tag('body', $obj['body'], array('class' => 'editor')) ?>                
            </div>			
        </div>

                        
   </div>
</div> 
 
<?php echo ajax_modal_template_footer() ?>

<script>
  $(function() { 
    
    $('#templates_form').validate({
			submitHandler: function(form){
				app_prepare_modal_action_loading(form)
				form.submit();
			}
    });
  }); 
</script>