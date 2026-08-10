<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

function users_groups_is_super_admin()
{
  global $app_user;
  return ((int)$app_user['group_id'] === 0);
}

function users_groups_is_sector_admin_delegate()
{
  global $app_user;
  return ((int)$app_user['group_id'] === 5);
}

function users_groups_can_manage_groups()
{
  return users_groups_is_super_admin() || users_groups_is_sector_admin_delegate();
}

function users_groups_can_manage_access_matrix()
{
  // Grupo 5 (delegado setorial) também pode administrar ACL completa de grupos.
  return users_groups_is_super_admin() || users_groups_is_sector_admin_delegate();
}

  //check access
  if(!users_groups_can_manage_groups())
  {
    redirect_to('dashboard/access_forbidden');
  }
  
  $app_title = app_set_title(TEXT_HEADING_USERS_ACCESS_GROUPS);
  