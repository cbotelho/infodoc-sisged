<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
 
define('PLUGIN_EXT_VERSION','3.6.2');
define('PLUGIN_EXT_REQUIRED_RUKOVODITEL_VERSION','3.6.2');

//check required Rukovoditel version
if(PROJECT_VERSION!=PLUGIN_EXT_REQUIRED_RUKOVODITEL_VERSION and !in_array($app_module_path,array('tools/check_version')) )
{
    die(sprintf(TEXT_EXT_REQUIRED_RUKOVODITEL_VERSION,PLUGIN_EXT_REQUIRED_RUKOVODITEL_VERSION,PLUGIN_EXT_VERSION));              
} 
 
require('plugins/ext/classes/license.php'); 
license::check();


if (!app_session_is_registered('app_calendar_reminder')) 
{
    $app_calendar_reminder = new calendar_reminder();
    app_session_register('app_calendar_reminder');    
} 

if (!app_session_is_registered('plugin_ext_current_version')) 
{
  $plugin_ext_current_version = '';
  app_session_register('plugin_ext_current_version');    
} 

if(CFG_DISABLE_CHECK_FOR_UPDATES==1)
{
	$plugin_ext_current_version = '';
}

$app_chat = new app_chat();

