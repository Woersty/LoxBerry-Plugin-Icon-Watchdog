<?php

// Two functions to get a list of all Xpath as Array
function xmlToXpath($xml)
{
    $sxi = new SimpleXmlIterator($xml);
    return sxiToXpath($sxi);
}
function sxiToXpath($sxi, $key = null, &$tmp = null)
{
    $keys_arr = array();
    for ($sxi->rewind(); $sxi->valid(); $sxi->next())
    {
        $sk = $sxi->key();
        if (array_key_exists($sk, $keys_arr))
        {
            $keys_arr[$sk]+=1;
            $keys_arr[$sk] = $keys_arr[$sk];
        }
        else
        {
            $keys_arr[$sk] = 1;
        }
    }
    for ($sxi->rewind(); $sxi->valid(); $sxi->next())
    {
        $sk = $sxi->key();
        if (!isset($$sk))
        {
            $$sk = 1;
        }
        if ($keys_arr[$sk] >= 1)
        {
            $spk = $sk . '[' . $$sk . ']';
            $keys_arr[$sk] = $keys_arr[$sk] - 1;
            $$sk++;
        }
        else
        {
            $spk = $sk;
        }
        $kp = $key ? $key . '/' . $spk : '/' . $sxi->getName() . '/' . $spk;
        if ($sxi->hasChildren())
        {
            sxiToXpath($sxi->getChildren(), $kp, $tmp);
        }
        else
        {
            $tmp[$kp] = strval($sxi->current());
        }
        $at = $sxi->current()->attributes();
        if ($at)
        {
            $tmp_kp = $kp;
            foreach ($at as $k => $v)
            {
                $kp .= '/@' . $k;
                $tmp[$kp] = $v;
                $kp = $tmp_kp;
            }
        }
    }
    return $tmp;
}

// Convert Icons
function convert_icons($xml,$icon_types)
{
	// Current availavle icon Types are IconPlace, IconCat, IconState
	$rs = xmlToXpath($xml->asXML());
	foreach ($icon_types as $icon_type)
	{
		// Get a Xpath list with all icons
		$icon_Xpath_array = array_keys($rs,$icon_type);
		// Start Index numbering at 1
		$Ix = 1;
		// Convert each icon
		foreach ($icon_Xpath_array as $icon_Xpath)
		{
			// New numbering
			$Ix++;
			// Get parent node
			$Icon_Node=str_replace("/@Type", "", $icon_Xpath);
			// Remove bitmap icon definition
			unset($xml->xpath($Icon_Node.'/@Icon')[0][0]);
			// Remove User info
			unset($xml->xpath($Icon_Node.'/@User')[0][0]);
			// Set new index
			$xml->xpath($Icon_Node."[@Type='$icon_type']")[0]->attributes()['Ix'] = $Ix;
		}
	}
	return $xml;
}

function import_loxone_project($file)
{
	// Main Import function
	$xml = simplexml_load_file ( "$file" , "SimpleXMLElement" ,LIBXML_NOCDATA);
	$json = array();
	$xml = convert_icons($xml,array("IconPlace","IconCat","IconState"));

	foreach ($xml->C->C as $value) 
	{
		if ((string) $value['Title'] == 'Symbole') 
		{
			foreach ($value as $symbol_category) 
			{
				$output ="<hr><b>".$symbol_category['Type']." (".$symbol_category['Title'].")</b><hr>";			
				foreach ($symbol_category->C as $icon) 
				{
					if ((string) $symbol_category->C['Type'] == 'IconPlace') 
					{
						array_push($json, array("U" => $icon["U"], "Title" => $icon["Title"], "Type" => $icon["Type"] ) );
					}
					elseif ((string) $symbol_category->C['Type'] == 'IconCat') 
					{	
						array_push($json, array("U" => $icon["U"], "Title" => $icon["Title"], "Type" => $icon["Type"] ) );
					}
					elseif ((string) $symbol_category->C['Type'] == 'IconState') 
					{	
						array_push($json, array("U" => $icon["U"], "Title" => $icon["Title"], "Type" => $icon["Type"] ) );
					}
				}
			}	
		}
	}
	$data['xml'] = $xml->asXML();
	$data['xml'] = str_replace(array("<IoData/>","<Display/>"),array("<IoData></IoData>","<Display></Display>"),$data);
	$data['json'] = json_encode($json);
	return $data;
}
