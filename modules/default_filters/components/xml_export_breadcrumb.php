<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$xml_report_info_query = db_query("select * from app_ext_xml_export_templates where id='" . str_replace('xml_export','',$app_redirect_to) . "'");
$xml_report_info = db_fetch_array($xml_report_info_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_XML_EXPORT,url_for('ext/xml_export/templates')) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $xml_report_info['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $entity_info['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_FILTERS . '</li>';

