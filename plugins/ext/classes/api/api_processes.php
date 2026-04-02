<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class api_processes extends api
{
    public $entity_id, $user;
    
    function __construct($user)
    {
        global $app_entities_cache;
        
        $this->entity_id = (int)self::_post('entity_id');
        
        if(!isset_entity($this->entity_id))
        {
            api::response_error('Entity #' . $this->entity_id . ' does not exist');
        }  
        
        $this->user = $user;
    }
    
    function get_process_buttons()
    {
        global $app_user;
        
        $app_user = $this->user;
                        
        $choices = [];               
        $processes = new processes($this->entity_id);   
        
        if(isset($_REQUEST['item_id']))
        {
            $processes->items_id = (int)$_REQUEST['item_id'];
        }
        
        foreach($processes->get_buttons_list() as $button)
        {            
            $choices[] = $button;
        }
        
        self::response_success($choices);
    }
    
    function run_process()
    {
        global $app_user;
        
        $app_user = $this->user;
        
        $process_id = (int)self::_post('process_id');
        $item_id = (int)self::_post('item_id');
        
        $process_info_query = db_query("select * from app_ext_processes where id='" . $process_id . "' and is_active=1");
        if(!$process_info = db_fetch_array($process_info_query))
        {
            api::response_error('Process #' . $process_id . ' does not exist');
        }
        
        $item_info_query = db_query("select id from app_entity_" . $this->entity_id . "   where id='" . $item_id . "'");
        if(!$item_info = db_fetch_array($item_info_query))
        {
            api::response_error('Item #' . $item_id . ' does not exist in Entity ' . $this->entity_id);
        }
        
        $processes = new processes($this->entity_id); 
        $processes->items_id = $item_id;
        
        $check = false;
        foreach($processes->get_buttons_list() as $button)
        {
            if($button['id']==$process_info['id']) $check = true;
        }
        
        if(!$check)
        {
            api::response_error('Process #' . $process_id . ' access denied');
        }
        
        $processes->run($process_info,false,true);
        
        self::response_success("Process #{$process_id} executed");
    }
}
