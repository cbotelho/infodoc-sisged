<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php 
    $phone = preg_replace("/\D/","",$_GET['phone']);	 
?>

<?php echo ajax_modal_template_header(TEXT_EXT_CALL_TO_NUMBER . ' ' . $phone) ?>

<div class="modal-body">    
<?php 

$module_info_query = db_query("select * from app_ext_modules where id='" . _GET('module_id') . "' and type='telephony' and is_active=1");
if($module_info = db_fetch_array($module_info_query))
{
    modules::include_module($module_info,'telephony');
    
    $module = new $module_info['module'];
    $module->call_to_number($module_info['id'],$phone);    
}	

?>
</div>
 
<?php echo ajax_modal_template_footer('hide-save-button') ?>

 