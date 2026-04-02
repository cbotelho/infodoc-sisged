<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(rss_feed::has_user_feeds())
{
    $app_plugin_menu['account_menu'][] = array('title'=>TEXT_EXT_RSS_FEED,'url'=>url_for('users/rss_feeds'),'class'=>'fa-rss');
}
