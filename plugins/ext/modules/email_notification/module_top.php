<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

  //check access
  if($app_user['group_id']>0)
  {
    redirect_to('dashboard/access_forbidden');
  }
  
  $app_title = app_set_title(TEXT_EXT_EMAIL_SENDING_RULES);
  
  //check if entity exist
  if(isset($_GET['entities_id']) and !isset_entity($_GET['entities_id']))
  {
  	redirect_to('entities/entities');
  }