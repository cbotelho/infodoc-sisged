<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if (isset($_GET['path']))
{
    $path_info = items::parse_path($_GET['path']);    
    $current_path = $_GET['path'];
    $current_entity_id = $path_info['entity_id'];
    $current_item_id = true; // set to true to set off default title
    $current_path_array = $path_info['path_array'];
    $parent_entity_item_id = $path_info['parent_entity_item_id'];
    $app_breadcrumb = items::get_breadcrumb($current_path_array);

    $app_breadcrumb[] = array('title' => $reports['name']);

    require(component_path('items/navigation'));
}
else
{
    $parent_entity_item_id = 0;
}

$parent_entity_id = $app_entities_cache[$reports['entities_id']]['parent_id'];

$is_top_kanban = ($parent_entity_id and !isset($_GET['path'])) ? true:false;

//reports name with filters
$common_filters = new common_filters($reports['entities_id'],$fiters_reports_id);
$common_filters->parent_item_id = $parent_entity_item_id;
$common_filters->redirect_to = $is_top_kanban ? 'kanban-top' . _GET('id') : 'kanban' . _GET('id');
echo $common_filters->render($reports['name'] );

if($reports['filters_panel']=='default')
{
    $filters_preivew = new filters_preivew($fiters_reports_id);
    $filters_preivew->redirect_to = 'kanban' . $_GET['id'];
    $filters_preivew->has_listing_configuration = true;
    $filters_preivew->has_listing_configuration_fields = false;


    if (isset($_GET['path']))
    {
        $filters_preivew->path = $_GET['path'];
        $filters_preivew->include_paretn_filters = false;
    }

    echo $filters_preivew->render();
}

echo '
<div id="kanban_board' . $reports['id']. '"></div>    
<script>
    function load_kanban_report' . $reports['id']. '()
    {
        let content_id = "#kanban_board' . $reports['id']. '"
        $(content_id).append("<div class=\"data_listing_processing\"></div>");    
        $(content_id).css("opacity", 0.5);
        $(content_id).load("' . url_for('ext/kanban/view','action=kanban&id=' . $reports['id'] . '&path=' . $app_path) . '",function(){
            $(this).css("opacity", 1);                       
            $(".kanban-div",this).floatingScroll()            
        })
    }
    
    function load_kanban_report' . $reports['id']. '_items(choice_id,page)
    {
        let content_id = "#kanban' . $reports['id'] . '_"+choice_id+"_items"
        $(content_id).css("opacity", 0.5);    
        $(content_id).load("' . url_for('ext/kanban/view','action=kanban_items&id=' . $reports['id'] . '&path=' . $app_path) . '&page="+page+"&choice_id="+choice_id,function(){
            $(this).css("opacity", 1);
            load_kanban_report' . $reports['id'] . '_sortable()
        })
    }
    
    load_kanban_report' . $reports['id']. '()
</script>    
    ';



