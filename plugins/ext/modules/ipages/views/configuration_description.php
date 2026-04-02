<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<h3 class="page-title"><?php echo TEXT_EXT_IPAGES ?></h3>

<ul class="page-breadcrumb breadcrumb">
  <li><?php echo link_to(TEXT_EXT_IPAGES,url_for('ext/ipages/configuration'))?><i class="fa fa-angle-right"></i></li>  
  <li><?php echo $ipage_info['name'] ?></li>
</ul>

<?php 
	$app_items_form_name = 'ipage_form';
?>

<?php echo form_tag('configuration_form', url_for('ext/ipages/configuration','action=save_description&id=' . $_GET['id']),array('class'=>'form-horizontal')) ?>
  <div class="form-body">
    
    <div class="form-group">    	
      <div class="col-md-12">	
    	  <?php echo textarea_tag('description',$ipage_info['description'],array('class'=>'form-control input-large')) ?>
    	  <?php echo tooltip_text(TEXT_IPAGE_DESCRIPTION_TIP) ?>        
      </div>			
    </div>
         
		<div class="form-group">    	
      <div class="col-md-12">	
    	  <?php echo fields_types::render('fieldtype_attachments',array('id'=>'attachments'),array('field_attachments'=>$ipage_info['attachments'])) ?>
        <?php echo input_hidden_tag('attachments','',array('class'=>'form-control')) ?>        
      </div>			
    </div>
    <br>
    
    
    <div class="form-group">    	
      <div class="col-md-12">	
    	  <?php echo submit_tag(TEXT_BUTTON_SAVE) . ' ' . button_tag(TEXT_BUTTON_CANCEL,url_for('ext/ipages/configuration'),false,array('class'=>'btn btn-default'))  ?>        
      </div>			
    </div>
    
   
    
  </div>
    
</form>


<script>

$(function(){
        
  use_editor_full('description',true)
     
})
  
</script>