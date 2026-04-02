<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<h3 class="page-title"><?php echo $reports['name'] ?></h3>

<div class="row">
    <div class="col-md-12">
        <?php echo resource_timeline::render_filters_panel($reports) ?>
        <?php echo resource_timeline::render_entity_filters_panel($reports) ?>
    </div>
</div>   

<?php require(component_path('ext/resource_timeline/report'));?>

<script>
    function get_resource_timeline_height()
    {
        if($(window).height()>400)
        {
            return $(window).height()-170;
        }
        else
        {
            return $(window).height();
        }
    }
    
    $(function(){
        $(window).resize(function() {                        
            resource_timeline<?= $reports['id'] ?>.setOption('height', get_resource_timeline_height());
        });
    })
</script>
