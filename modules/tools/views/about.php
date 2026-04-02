<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

    $img = '<img style="width: 100%; max-width: 250px;" src="images/rukovoditel_box' . (APP_LANGUAGE_SHORT_CODE == 'ru' ? '.ru' : '') . '.png">';
?>
<h3 class="page-title"><?php echo TEXT_ABOUT_APP ?></h3>

<div class="row">    
    <div class="col-md-12">
        <div class="col-md-3"><center><?= $img ?></center></div>
        <div class="col-md-9">
            <?= TEXT_ABOUT_APP_DETAILS . '<hr>' ?>
            <?= TEXT_CURRENT_APP_VERSION . ': <b>' . PROJECT_VERSION . (strlen(PROJECT_VERSION_DEV) ? ' (' . PROJECT_VERSION_DEV . ')':'') . '</b><br>' . TEXT_UPDATE_INSTRUCTION ?>
            
        </div>
    </div>
</div>
