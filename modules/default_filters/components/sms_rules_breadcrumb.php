<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$rules_query = db_query("select * from app_ext_sms_rules where id='" . str_replace('sms_rules','',$app_redirect_to) . "'");
$rules = db_fetch_array($rules_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_SMS_SENDIGN_RULES,url_for('ext/modules/sms_rules', 'entities_id=' . $rules['entities_id'])) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $entity_info['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . strip_tags($rules['description']) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_FILTERS . '</li>';

$page_description = TEXT_SET_MSG_FILTERS;

