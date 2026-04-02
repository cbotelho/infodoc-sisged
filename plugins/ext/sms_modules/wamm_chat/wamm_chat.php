<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class wamm_chat
{

    public $title;
    public $site;
    public $api;
    public $version;
    public $country;

    function __construct()
    {
        $this->title = TEXT_MODULE_WAMM_CHAT_TITLE;
        $this->site = 'https://wamm.chat';
        $this->api = 'https://wamm.chat/why/api';
        $this->version = '1.0';
        $this->country = 'RU';
    }

    public function configuration()
    {
        $cfg = array();

        $cfg[] = array(
            'key' => 'token',
            'type' => 'input',
            'default' => '',
            'title' => TEXT_MODULE_WAMM_CHAT_TOKEN,
            'description' => TEXT_MODULE_WAMM_CHAT_TOKEN_DESCRIPTION,
            'params' => array('class' => 'form-control input-large required'),
        );


        return $cfg;
    }

    function send($module_id, $destination = array(), $text = '')
    {
        global $alerts;

        $cfg = modules::get_configuration($this->configuration(), $module_id);        

        foreach($destination as $phone)
        {
            $phone  = preg_replace('/\D/', '', $phone);
            
            $text = str_replace(["<br>","\n"],'%0A',$text);
            $text = strip_tags($text);
            
            $url = 'https://wamm.chat/api2/msg_to/' . $cfg['token'] . '/?phone=' . $phone . '&text=' . urlencode($text);
            
            //echo $url;
            //exit();
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            curl_close($ch);

            if($result)
            {
                $result = json_decode($result, true);

                if($result['err']!=0)
                {
                    $alerts->add($this->title . ' ' . TEXT_ERROR . ' ' . $result['err'], 'error');
                }
            }
        }
    }

}
