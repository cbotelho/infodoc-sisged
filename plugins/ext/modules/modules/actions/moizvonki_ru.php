<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

$modules = new modules('telephony');

$module_id = _GET('module_id');

$module = new moizvonki;
$cfg = modules::get_configuration($module->configuration(),$module_id);

switch($app_module_action)
{
    case 'webhook.subscribe':
        
        $data = [
            'user_name' => $app_user['email'],
            'api_key' => $cfg['api_key'],
            'action' => 'webhook.subscribe',
            'hooks' => ['call.finish'=>url_for_file('api/tel/moizvonki.php')]            
        ];
        
        $body = json_encode($data);
        
        $ch = curl_init($cfg['api_url']);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Content-Lengt:' . strlen($body)));            
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );            
        $result = curl_exec($ch);
        curl_close($ch);
        
        if($result=='OK')
        {
            $alerts->add('Оформлена подписка на событие "завершение звонка"','success');
        }
        
        //echo $result;
        //exit();
        
        redirect_to('ext/modules/modules','type=telephony');
        
        break;
    case 'webhook.unsubscribe':
        
        $data = [
            'user_name' => $app_user['email'],
            'api_key' => $cfg['api_key'],
            'action' => 'webhook.unsubscribe',
            'hooks' => ['call.finish']            
        ];
        
        $body = json_encode($data);
        
        $ch = curl_init($cfg['api_url']);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Content-Lengt:' . strlen($body)));            
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );            
        $result = curl_exec($ch);
        curl_close($ch);
        
        //echo $result;
        //exit();
        
        redirect_to('ext/modules/modules','type=telephony');
        
        break;
}

        

