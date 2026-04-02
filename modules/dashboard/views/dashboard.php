<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>
<?php

$is_plugin_dashboard = false;
if(defined('AVAILABLE_PLUGINS'))
{      
  foreach(explode(',',AVAILABLE_PLUGINS) as $plugin)
  {     
            
    //include plugin dashboard
    if(is_file('plugins/' . $plugin .'/includes/dashboard.php'))
    {
      require('plugins/' . $plugin .'/includes/dashboard.php');
      $is_plugin_dashboard = true;
    }      
  }  
}


if(!$is_plugin_dashboard)
{
    require(component_path('dashboard/dashboard_default'));
}


