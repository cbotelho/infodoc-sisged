<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */


class smsc
{
	public $title;
	
	public $site;
        public $api;
        public $version;
        public $country;
	
	function __construct()
	{
		$this->title = TEXT_MODULE_SMSC_TITLE;
		$this->site = 'http://smsc.ru';
		$this->api = 'http://smsc.ru/api/';
		$this->version = '1.0';
                $this->country = 'RU';
	}
	
	public function configuration()
	{
		$cfg = array();
		
					
		$cfg[] = array(
				'key'	=> 'login',
				'type' => 'input',
				'default' => '',
				'title'	=> TEXT_MODULE_SMSC_LOGIN,				
				'params' =>array('class'=>'form-control input-large required'),				
		);
		
		$cfg[] = array(
				'key'	=> 'password',
				'type' => 'input',
				'default' => '',
				'title'	=> TEXT_MODULE_SMSC_PASSWORD,				
				'params' =>array('class'=>'form-control input-large required'),
		);
					
		$cfg[] = array(
				'key'	=> 'use_https',
				'type' => 'dorpdown',
				'default' => 0,
				'choices'=>array(
						'0'=>TEXT_NO,
						'1'=>TEXT_YES,						
				),				
				'title'	=> TEXT_MODULE_SMSC_USE_HTTPS,				
				'params' =>array('class'=>'form-control input-small'),				
		);
							
				
		return $cfg;
	}
		
	function send($module_id, $destination = array(),$text = '')
	{		
		global $alerts;
						
		$cfg = modules::get_configuration($this->configuration(),$module_id);
						
		$url = ($cfg['use_https']==1 ? "https" : "http") . "://smsc.ru/sys/send.php";
							
		foreach($destination as $phone)
		{
			$phone  = preg_replace('/\D/', '', $phone);
			
			$params=[
					'login' => $cfg['login'],
					'psw' => $cfg['password'],
					'phones'=> $phone,
					'mes' => strip_tags($text),
					'charset' => 'utf-8',
					'fmt' => 3,
			];
				
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			$result = curl_exec($ch);
			curl_close($ch);
									
			$result = json_decode($result,true);
			
			//print_r($result);
			
			if(isset($result['error']))
			{
				$alerts->add($this->title . ' ' . TEXT_ERROR . ' ' .  $result['error'] . '; error_code: ' . $result['error_code'],'error');
			}
						
		}					
	}				
	
}