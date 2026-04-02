<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'get_refresh_token':
        $app_code = $_POST['app_code'];
        $app_key = $_POST['app_key'];
        $app_secret = $_POST['app_secret'];
        
        $endpoint = 'https://api.dropbox.com/oauth2/token?code=' . $app_code . '&grant_type=authorization_code';
        
        $ch = curl_init($endpoint);
        $headers = array("Content-Type: application/json");
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, "$app_key:$app_secret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
        $r = curl_exec($ch);
        curl_close($ch);
                        
        echo $r;
        
        exit();
        
        break;
}
