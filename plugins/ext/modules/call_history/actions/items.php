<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$entity_id = _GET('entity_id');

$call_history_query = db_query("select * from app_ext_call_history where id=" . _GET('id'));
if(!$call_history = db_fetch_array($call_history_query))
{
    redirect_to('ext/call_history/view');
}

if(!users::has_users_access_to_entity($entity_id))
{
    redirect_to('dashboard/access_forbidden');
}
