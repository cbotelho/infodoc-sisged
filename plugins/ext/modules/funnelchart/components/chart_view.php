<!-- handle chart -->
<?php 
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if($app_module_path=='ext/funnelchart/view')
{
	$chart_settins = ($funnelchart_type[$reports['id']]=='funnel' ? "type: 'funnel'" :  "type: 'bar'");
	$click_event = 'funnelchart_items_listin(this.options.name,this.options.filter_by)';	
}
else
{
	$chart_settins = ($funnelchart_type[$reports['id']]=='funnel' ? "type: 'funnel'" : "type: 'bar'");
	$click_event = '';
}


if(count($data_css))
{
    echo '
    <style>
        ' . implode("\n",$data_css). '
    </style>    
        ';
}
?>

<script>
$(function(){
	
	$('#funnelchart_container_<?php echo $reports['id'] ?>').highcharts({
		chart: {
			<?php echo $chart_settins ?>,	
                        styledMode: true
		},
		title: {
			text: '<?php echo TEXT_EXT_TOTAL . ': ' . $count_items ?>',
			x: -50
		},
		yAxis:{
			title: {
        text: '',      
    	},
		},		
		plotOptions: {
			series: {
				dataLabels: {
					enabled: true,
					format: '{point.name}: {point.y:,.0f}',
					color:  'black',
					softConnector: true
				},
				cursor: 'pointer',
                                point: {
                                    events: {
                                        click: function() {
                                                <?php echo $click_event ?>                  
                                        }
                                    }
                                },
				neckWidth: '30%',
				neckHeight: '10%',
                                width: '70%',
                                center: ['40%', '50%'],

						//-- Other available options
				// height: pixels or percent
				// width: pixels or percent
			}
		},
		legend: {
			enabled: false
		},
		tooltip: {
                    formatter: function() {
                        return '<b>' + this.point.name + ': '  + this.y + '</b><br><?php echo TEXT_EXT_CONVERSION ?>: '+this.point.conversion <?php echo $sum_js_tip ?>;
                    }
                },
		series: [{
			name: '',
			data: [
					<?php echo implode(',',$data_js) ?>
			]
		}		
		]
	});

})

</script>