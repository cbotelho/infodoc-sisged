<?php
/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

class tcmb
{
	public $title;

	function __construct()
	{
		$this->title = 'TCMB - Türkiye Cumhuriyet Merkez Bankası';
	}

	static function rate($from, $to)
	{
		$kur = simplexml_load_file("https://www.tcmb.gov.tr/kurlar/today.xml");
		
		$from_value = false;		
		$to_value = false;	
		
		if($from=='TRY') $from_value = 1;
		if($to=='TRY') $to_value = 1;
		
		foreach ($kur -> Currency as $cur) 
		{
			if ($cur["Kod"] == $from) {
				$from_value = $cur -> ForexBuying;
			}

			if ($cur["Kod"] == $to) {
				$to_value  = $cur -> ForexSelling;
			}
		}
		
		if ($from_value and $to_value)
		{
			return ($from_value/$to_value);
		}
		else
		{
			return false;
		}
	}
}