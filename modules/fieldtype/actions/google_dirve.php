<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

switch($app_module_action)
{
    case 'get_access_token':
        $app_code = $_POST['app_code'];
        $client_id = $_POST['client_id'];
        $app_secret = $_POST['app_secret'];
        
        $redirectUrl = url_for('dashboard/dashboard');
        
        $GoogleDriveApi = new GoogleDriveApi();
                    
        $access_token = false;
        $response = [];
                    
        try
        {                                                
            $data = $GoogleDriveApi->GetAccessToken($client_id, $redirectUrl, $app_secret, $app_code); 
            $access_token = $data['access_token']; 
            
        }
        catch(Exception $e)
        {            
            $response = [
                'error' => $e->getCode(),
                'message' => $e->getMessage(),
            ];
           
        }  
        
        if($access_token)
        {
            $response = [                
                'access_token' => $access_token,
            ];
        }
        
        echo json_encode($response);
        
        exit();
        
        break;
}