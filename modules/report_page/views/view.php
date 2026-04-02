<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */
?>

<?php
    if(strlen($app_path))
    {
        require(component_path('items/navigation')); 
    }
    
    $settings = new settings($report_page['settings']);
?>


<div class="row">
    
        <div class="col-md-11">
            <?php if($settings->get('hide_page_title')!=1): ?>
                <h3 class="page-title"><?php echo $report_page['name'] ?></h3>
            <?php endif  ?>
        </div>    
    
    <?php if($settings->get('hide_print_button')!=1): ?>
        <div class="col-md-1 align-right noprint">
            <a href="javascript: window.print()" class="btn btn-default"><i class="fa fa-print"></i></a>
        </div>
    <?php endif  ?>
</div>   
<style>
@media print
{    
    body{
        background-color: #fff !important;
    }   
}
</style>

<?php 
    $filters = new report_page\report_filters($report_page);
    echo $filters->render();
?>

<div class="row">
    <div class="col-md-12">
        <div id="report_page_content" data-id="<?= $report_page['id'] ?>" data-path="<?= $app_path ?>"></div>
    </div>
</div>

<script>
function load_report_page()
{
    $('#report_page_content').css("opacity", 0.5).append('<div class="data_listing_processing"></div>')
    
    let data = $('#report_page_content').data()    
    
    $('#report_page_content').load(url_for('report_page/view','action=load&id='+data.id+'&path='+data.path),$('.from-report-page-filters').serializeArray(),function(){
        $(this).css("opacity", 1)
        
        app_handle_scrollers()
        
        App.initFancybox()
    })
}

$(function(){
    load_report_page()
})
    
</script>

