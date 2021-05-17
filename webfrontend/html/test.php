<?php

$xml = simplexml_load_file ( "/opt/loxberry/webfrontend/html/plugins/icon-watchdog/xml.loxone" , "SimpleXMLElement" ,LIBXML_NOCDATA);
foreach ($xml->C->C as $value) 
{
    if ((string) $value['Title'] == 'Symbole') 
	{
		foreach ($value as $symbol_category) 
		{
			echo "<hr><b>".$symbol_category['Type']." (".$symbol_category['Title'].")</b><hr>";			
			foreach ($symbol_category->C as $icon) 
			{
				if ((string) $symbol_category->C['Type'] == 'IconPlace') 
				{	
					if ( (string) $symbol_category->C['Type'] != (string) $icon["Type"])
					{
						echo "<b>".$icon["Type"]."</b>: ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
					else
					{
						echo $icon["Type"].": ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
				}
				elseif ((string) $symbol_category->C['Type'] == 'IconCat') 
				{	
					if ( (string) $symbol_category->C['Type'] != (string) $icon["Type"])
					{
						echo "<b>".$icon["Type"]."</b>: ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
					else
					{
						echo $icon["Type"].": ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
				}
				elseif ((string) $symbol_category->C['Type'] == 'IconState') 
				{	
					if ( (string) $symbol_category->C['Type'] != (string) $icon["Type"])
					{
						echo "<b>".$icon["Type"]."</b>: ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
					else
					{
						echo $icon["Type"].": ". $icon["Title"] ." ". $icon["U"]."<br>";
					}
				}
			}
		}	
	}
}

$data = $xml->asXML();
$data = str_replace("<IoData/>", "<IoData></IoData>", $data);
file_put_contents("/opt/loxberry/webfrontend/html/plugins/icon-watchdog/test.loxone", $data);

