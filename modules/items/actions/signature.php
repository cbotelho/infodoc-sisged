<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

if(!isset($app_fields_cache[$current_entity_id][_get::int('fields_id')]))
{
	redirect_to('dashboard/page_not_found');
}

$app_layout = 'signature_layout.php';