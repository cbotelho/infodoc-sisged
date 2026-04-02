<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$call_history_query = db_query("select * from app_ext_call_history where id=" . _GET('id'));
if(!$call_history = db_fetch_array($call_history_query))
{
    redirect_to('ext/call_history/view');
}
elseif($call_history['is_new']==1)
{
    //set off new
    db_query("update app_ext_call_history set is_new=0 where id={$call_history['id']}");
}

switch ($app_module_action)
{
    case 'save':
        db_perform('app_ext_call_history', ['comments'=>$_POST['comments']], 'update', "id='" . db_input($_GET['id']) . "'");
        exit();
        break;				
}