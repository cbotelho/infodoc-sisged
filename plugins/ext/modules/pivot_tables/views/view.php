<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$check = db_count('app_ext_pivot_tables_fields',$pivot_tables['id'],'reports_id');

if($check==0)
{
	
	echo '
			<h3 class="page-title">' . $pivot_tables['name'] . '</h3>
			<div class="alert alert-warning">' . TEXT_EXT_PIVOTREPORTS_FIELDS_ERROR . '</div>
	';	
}
else 
{	

    echo '<h3 class="page-title">' . $pivot_tables['name'] . '</h3>';

        
    if($pivot_tables['filters_panel']=='default')
    {
      $type = 'pivot_tables_filters' . $pivot_tables['id'];
      $fiters_reports_id = reports::auto_create_report_by_type($pivot_tables['entities_id'],$type,true);
      $filters_preivew = new filters_preivew($fiters_reports_id);  
      $filters_preivew->redirect_to = 'pivot_table' . $pivot_tables['id'];
      $filters_preivew->has_listing_configuration = false;

      echo $filters_preivew->render();  
    }
    elseif($pivot_tables['filters_panel']=='quick_filters')
    {                
        $type = 'pivot_tables' . $pivot_tables['id'];
        $fiters_reports_id = reports::auto_create_report_by_type($pivot_tables['entities_id'],$type,true);
        $filters_panels = new filters_panels($pivot_tables['entities_id'],$fiters_reports_id,'',0);
        $filters_panels->set_type($type);
        $filters_panels->set_items_listing_funciton_name('refetch_pivot_table');
        echo '
            <div class="' . $type . '">' . $filters_panels->render_horizontal() . '</div>
            <script>
                function refetch_pivot_table()
                {
                    pivot_table' . $pivot_table->id . '.updateData({    
                        //filename: "https://cdn.webdatarocks.com/data/data.csv"
                        filename: "' . url_for('ext/pivot_tables/view','action=get_csv&id=' . $pivot_table->id) . '"+"&time="+Math.floor(Date.now() / 1000)
                    });
                      
                    
                   
                }
            </script>
            ';
    }

    require(component_path('ext/pivot_tables/pivot_table'));	
}

?>

