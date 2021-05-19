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
	$pretty = "<Table><tr class='icon_head'><td style='width:64px; height:64px;'></td><td>UID</td><td>Titel</td><td>Typ</td>";
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
					if (((string) $symbol_category->C['Type'] == 'IconPlace') || ((string) $symbol_category->C['Type'] == 'IconCat') || ((string) $symbol_category->C['Type'] == 'IconState'))
					{
						array_push($json, array("U" => $icon["U"], "Title" => $icon["Title"], "Type" => $icon["Type"] ) );
						if (is_readable(dirname($file)."/images/".$icon["U"].".svg"))
						{
							$pic = base64_encode(file_get_contents (dirname($file)."/images/".$icon["U"].".svg"));
							$pic = 'background-image: url("data:image/svg+xml;base64,'.$pic.'")';
							$class="icon_ok";
						}
						else
						{
							$pic = 'background-image: url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhLS0gQ3JlYXRlZCB3aXRoIElua3NjYXBlIChodHRwOi8vd3d3Lmlua3NjYXBlLm9yZy8pIC0tPgoKPHN2ZwogICB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iCiAgIHhtbG5zOmNjPSJodHRwOi8vY3JlYXRpdmVjb21tb25zLm9yZy9ucyMiCiAgIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyIKICAgeG1sbnM6c3ZnPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIKICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogICB4bWxuczpzb2RpcG9kaT0iaHR0cDovL3NvZGlwb2RpLnNvdXJjZWZvcmdlLm5ldC9EVEQvc29kaXBvZGktMC5kdGQiCiAgIHhtbG5zOmlua3NjYXBlPSJodHRwOi8vd3d3Lmlua3NjYXBlLm9yZy9uYW1lc3BhY2VzL2lua3NjYXBlIgogICB3aWR0aD0iNjQiCiAgIGhlaWdodD0iNjQiCiAgIHZpZXdCb3g9IjAgMCA2My45OTk5OTkgNjMuOTk5OTk5IgogICBpZD0ic3ZnNDE1NyIKICAgdmVyc2lvbj0iMS4xIgogICBpbmtzY2FwZTp2ZXJzaW9uPSIwLjkxIHIxMzcyNSIKICAgc29kaXBvZGk6ZG9jbmFtZT0iTk9GT1VORC5zdmciPgogIDxkZWZzCiAgICAgaWQ9ImRlZnM0MTU5IiAvPgogIDxzb2RpcG9kaTpuYW1lZHZpZXcKICAgICBpZD0iYmFzZSIKICAgICBwYWdlY29sb3I9IiNmZmZmZmYiCiAgICAgYm9yZGVyY29sb3I9IiM2NjY2NjYiCiAgICAgYm9yZGVyb3BhY2l0eT0iMS4wIgogICAgIGlua3NjYXBlOnBhZ2VvcGFjaXR5PSIwLjAiCiAgICAgaW5rc2NhcGU6cGFnZXNoYWRvdz0iMiIKICAgICBpbmtzY2FwZTp6b29tPSIzLjk1OTc5OCIKICAgICBpbmtzY2FwZTpjeD0iODEuOTQ2MjIiCiAgICAgaW5rc2NhcGU6Y3k9IjMzLjYxODU4NCIKICAgICBpbmtzY2FwZTpkb2N1bWVudC11bml0cz0icHgiCiAgICAgaW5rc2NhcGU6Y3VycmVudC1sYXllcj0ibGF5ZXIxIgogICAgIHNob3dncmlkPSJmYWxzZSIKICAgICB1bml0cz0icHgiCiAgICAgaW5rc2NhcGU6d2luZG93LXdpZHRoPSIxOTIwIgogICAgIGlua3NjYXBlOndpbmRvdy1oZWlnaHQ9IjEwMTciCiAgICAgaW5rc2NhcGU6d2luZG93LXg9Ii04IgogICAgIGlua3NjYXBlOndpbmRvdy15PSItOCIKICAgICBpbmtzY2FwZTp3aW5kb3ctbWF4aW1pemVkPSIxIiAvPgogIDxtZXRhZGF0YQogICAgIGlkPSJtZXRhZGF0YTQxNjIiPgogICAgPHJkZjpSREY+CiAgICAgIDxjYzpXb3JrCiAgICAgICAgIHJkZjphYm91dD0iIj4KICAgICAgICA8ZGM6Zm9ybWF0PmltYWdlL3N2Zyt4bWw8L2RjOmZvcm1hdD4KICAgICAgICA8ZGM6dHlwZQogICAgICAgICAgIHJkZjpyZXNvdXJjZT0iaHR0cDovL3B1cmwub3JnL2RjL2RjbWl0eXBlL1N0aWxsSW1hZ2UiIC8+CiAgICAgICAgPGRjOnRpdGxlPjwvZGM6dGl0bGU+CiAgICAgIDwvY2M6V29yaz4KICAgIDwvcmRmOlJERj4KICA8L21ldGFkYXRhPgogIDxnCiAgICAgaW5rc2NhcGU6bGFiZWw9IkViZW5lIDEiCiAgICAgaW5rc2NhcGU6Z3JvdXBtb2RlPSJsYXllciIKICAgICBpZD0ibGF5ZXIxIgogICAgIHRyYW5zZm9ybT0idHJhbnNsYXRlKDAsLTk4OC4zNjIyKSI+CiAgICA8ZwogICAgICAgaWQ9ImczNzc3IgogICAgICAgdHJhbnNmb3JtPSJtYXRyaXgoMC41NTM1NzQxOSwwLDAsMC41NTM1NzQxOSwtMTczLjk1NzI2LDcyOS4yOTkzNikiPgogICAgICA8cmVjdAogICAgICAgICBpZD0icmVjdDM3NTkiCiAgICAgICAgIHN0eWxlPSJmaWxsOiNmZmZmZmY7c3Ryb2tlOiNhNmE2YTY7c3Ryb2tlLXdpZHRoOjYuMzcyMDk5ODg7c3Ryb2tlLWxpbmVjYXA6cm91bmQ7c3Ryb2tlLWxpbmVqb2luOnJvdW5kIgogICAgICAgICBoZWlnaHQ9IjEwNi4yIgogICAgICAgICB3aWR0aD0iMTA2LjIiCiAgICAgICAgIHk9IjQ3My4wOSIKICAgICAgICAgeD0iMzE4Ljk1OTk5IiAvPgogICAgICA8ZwogICAgICAgICBpZD0iZzM3NjUiCiAgICAgICAgIHN0eWxlPSJmaWxsOm5vbmU7c3Ryb2tlOiNlMDAwMDA7c3Ryb2tlLXdpZHRoOjExLjI5ODk5OTc5IgogICAgICAgICB0cmFuc2Zvcm09Im1hdHJpeCgxLjA2MiwwLDAsMS4wNjIsLTIzLjA3NCwtMzIuNjMxKSI+CiAgICAgICAgPHBhdGgKICAgICAgICAgICBpZD0icGF0aDM3NjEiCiAgICAgICAgICAgZD0ibSAzNDUuMiw0OTkuMzIgNTMuNzMzLDUzLjczMyIKICAgICAgICAgICBzdHlsZT0iZmlsbDpub25lO3N0cm9rZTojZTAwMDAwO3N0cm9rZS13aWR0aDoxMS4yOTg5OTk3OTtzdHJva2UtbGluZWNhcDpyb3VuZDtzdHJva2UtbGluZWpvaW46cm91bmQiCiAgICAgICAgICAgaW5rc2NhcGU6Y29ubmVjdG9yLWN1cnZhdHVyZT0iMCIgLz4KICAgICAgICA8cGF0aAogICAgICAgICAgIGlkPSJwYXRoMzc2MyIKICAgICAgICAgICBzdHlsZT0iZmlsbDpub25lO3N0cm9rZTojZTAwMDAwO3N0cm9rZS13aWR0aDoxMS4yOTg5OTk3OTtzdHJva2UtbGluZWNhcDpyb3VuZDtzdHJva2UtbGluZWpvaW46cm91bmQiCiAgICAgICAgICAgaW5rc2NhcGU6Y29ubmVjdG9yLWN1cnZhdHVyZT0iMCIKICAgICAgICAgICBkPSJtIDM5OC45Myw0OTkuMzIgLTUzLjczMyw1My43MzMiIC8+CiAgICAgIDwvZz4KICAgIDwvZz4KICA8L2c+Cjwvc3ZnPgo=")';
							$class="icon_bad";
							
						}
						$pretty .= "<tr class='".$class."'><td><div style='width:64px; height:64px; ".$pic."'></td><td>".$icon["U"]."</td><td>".$icon["Title"]."</td><td>".$icon["Type"]."</td></tr>";
					}
				}
			}	
		}
	}
	$pretty .= "</Table>";
	$data['xml'] = $xml->asXML();
	$data['xml'] = str_replace(array("<IoData/>","<Display/>"),array("<IoData></IoData>","<Display></Display>"),$data);
	$data['json'] = json_encode($json);
	$data['pretty'] = $pretty;
	return $data;
}
