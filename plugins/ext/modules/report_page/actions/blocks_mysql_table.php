<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'save':
        $sql_data = [
        'report_id' => $report_page['id'],
        'block_type' => 'body_cell',
        'parent_id' => $block_info['id'],        
        'settings' => (isset($_POST['settings']) ? json_encode($_POST['settings']) : ''),
        'sort_order' => $_POST['sort_order'],
        ];
        
        if(isset($_GET['id']))
        {
            db_perform('app_ext_report_page_blocks',$sql_data,'update',"id='" . db_input($_GET['id']) . "'");
        }
        else
        {
            db_perform('app_ext_report_page_blocks',$sql_data);
        }
        
        redirect_to('ext/report_page/blocks_mysql_table','report_id=' . $report_page['id'] . '&block_id=' . $block_info['id']);
        break;
    case 'delete':
        if(isset($_GET['id']))
        {
            report_page\blocks::delete(_GET('id'));
            
            redirect_to('ext/report_page/blocks_mysql_table','report_id=' . $report_page['id'] . '&block_id=' . $block_info['id']);
        }
        break;    
}