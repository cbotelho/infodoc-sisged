<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


if(!strlen(CFG_CALL_HISTORY_ACCESS) or (strlen(CFG_CALL_HISTORY_ACCESS) and !in_array($app_user['group_id'],explode(',',CFG_CALL_HISTORY_ACCESS))))
{   
    redirect_to('dashboard/access_forbidden');
}

