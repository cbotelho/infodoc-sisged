<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(!calendar::user_has_public_full_access())
{
  exit();
}

$obj = array();

if(isset($_GET['id']))
{
  $obj = db_find('app_ext_calendar_events',$_GET['id']);
  
  $obj['start_date'] = str_replace(' 00:00','',date('Y-m-d H:i',$obj['start_date']));  
  $obj['end_date'] =  str_replace(' 00:00','',date('Y-m-d H:i',$obj['end_date']));
  
  if($obj['repeat_end']>0)
  {
    $obj['repeat_end'] = date('Y-m-d',$obj['repeat_end']);
  }
  else
  {
    $obj['repeat_end'] = '';
  }
  
}
else
{
  $obj = db_show_columns('app_ext_calendar_events');
  
  $start_date_timestamp = strtotime($_GET['start']);
  $end_date_timestamp = strtotime($_GET['end']);
              
  if($_GET['view_name']=='dayGridMonth')
  {
    $obj['start_date'] = date('Y-m-d',$start_date_timestamp);
    $obj['end_date'] = date('Y-m-d',strtotime('-1 day',$end_date_timestamp));
  }  
  else
  { 
    $obj['start_date'] = date('Y-m-d H:i',$start_date_timestamp);
    $obj['end_date'] = date('Y-m-d H:i',$end_date_timestamp);       
  }  
    
  $obj['bg_color'] = '#3a87ad';
  $obj['repeat_interval'] = 1;
   
}