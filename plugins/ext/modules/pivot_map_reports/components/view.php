<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */



$html = '';

$html .= '
        
    <div id="map_rpeort_' . $reports['id'] . '"></div>
        
    <script>
        function load_pivot_map_report' . $reports['id'] . '()
        {
            $("#map_rpeort_' . $reports['id'] . '").load("' . url_for('ext/pivot_map_reports/view_openstreetmap&id=' . $reports['id']) . '",{id: ' . $reports['id'] . '},function(){
                App.initMapSidebar();
            })
        }
        
        $(function(){
            load_pivot_map_report' . $reports['id'] . '();
        })        
    </script>
        ';

echo $html;
