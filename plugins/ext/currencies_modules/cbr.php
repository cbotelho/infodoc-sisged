<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class cbr
{
	public $title;

	function __construct()
	{
		$this->title = TEXT_EXT_CURRENCY_MODULE_CBR_TITLE;
	}

	static function rate($from, $to)
	{		
		$ch = curl_init('http://www.cbr.ru/scripts/XML_daily.asp');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($ch);
		curl_close($ch);
				
		$xml = simplexml_load_string($data);
		$json = json_encode($xml);
		$currencies = json_decode($json,TRUE);
				
		$from_value = false;
		$from_nominal = 1;
		$to_value = false;
		$to_nominal = 1;
				
		if($from=='RUB') $from_value = 1;
		if($to=='RUB') $to_value = 1;
		
		foreach($currencies['Valute'] as $currency)
		{
			if($currency['CharCode']==$to)
			{
				$to_value = str_replace(',','.',$currency['Value']);
				$to_nominal = $currency['Nominal'];
			}
			
			if($currency['CharCode']==$from)
			{
				$from_value = str_replace(',','.',$currency['Value']);
				$from_nominal = $currency['Nominal'];
			}					
		}
		
		if ($from_value and $to_value)
		{
			return (($from_value/$from_nominal)/($to_value/$to_nominal));
		}
		else
		{
			return false;
		}				 
	}
}