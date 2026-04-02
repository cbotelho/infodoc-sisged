<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


class mail_templates
{
    static function render_dropdown_helper()
    {
        global $app_user;
        
        $templates_query = db_query("select mf.*, ma.name as account_name from app_ext_mail_templates mf left join app_ext_mail_accounts ma on mf.accounts_id=ma.id where  mf.accounts_id in (select accounts_id from app_ext_mail_accounts_users where users_id='" . $app_user['id'] . "') order by mf.id");
        if(db_num_rows($templates_query)==0) return '';
        
        $html = '
            
            <div class="dropdown">
                      <button class="btn btn-default btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                        ' .TEXT_EXT_TEMPLATES . '
                        <span class="caret"></span>
                      </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenu1" style="max-height: 250px; overflow-y: auto">
            ';
        
        while($templates = db_fetch_array($templates_query))
        {
            $html .= '
                <li>
                    <a href="#" class="mail-template-button" data-id="' . $templates['id'] . '">' . $templates['subject']. '</a>
                </li>    
                ';
        }
        
        $html .= '</ul></div>';
        
        return $html;
    }
}
