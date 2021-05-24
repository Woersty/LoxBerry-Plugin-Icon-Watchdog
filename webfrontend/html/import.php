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

function report_xml_error($ms, $error, $xml)
{
	global $L, $logfilename, $log;
    $return["line"]    = "Line: ".$error->line;
    $return["column"]  = "Column: ".$error->column;
    $return["message"] = trim($error->message);
	$message = str_replace("<ms>",$ms,$L["ERRORS.ERR_045_PROJECT_XML_ANALYSIS_FAILED"]). "(Miniserver $ms) Code ". $error->code ." @ Line " . $error->line . " & Column " . $error->column . " [" . trim($error->message) . "]"."\n".htmlentities($xml[$error->line - 1]);
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
			LOGWARN ($message);
            break;
         case LIBXML_ERR_ERROR:
			LOGERR ($message);
            break;
        case LIBXML_ERR_FATAL:
			LOGCRIT ($message);
            break;
	}	
    return $return;
}

function str_ireplace_n($search, $replace, $subject, $occurrence)
{
	$search = preg_quote($search);
    return preg_replace("/^((?:(?:.*?$search){".--$occurrence."}.*?))$search/i", "$1$replace", $subject);
}
function import_loxone_project($file,$ms)
{
	global $log, $L;
	libxml_use_internal_errors(true);
	$xml_project_file_to_parse = file_get_contents($file);
	$ProjectSerial="none";
	LOGWARN ("MS#".$ms." ".$L["ERRORS.ERR_054_DTYPE_FIXED"]);
	$fixed_xml_string = "";
	foreach (explode("\n",$xml_project_file_to_parse) as $xml_line)
	{
		if ( strpos($xml_line,' DType="13"') )
		{
			$fixed_xml_line = str_ireplace_n(' DType="13"','',$xml_line,2) . "\n";
			$fixed_xml_string .= $fixed_xml_line;
			LOGDEB ("$ms Before:".htmlentities($xml_line));
			LOGDEB ("$ms After:".htmlentities($fixed_xml_line));
		}
		else if ( strpos($xml_line,' Type="LoxLIVE"') )
		{
			$serial_position = strpos($xml_line, 'Serial="');
			if ($serial_position ) 
			{
				$ProjectSerial = strtoupper(substr($xml_line,$serial_position+8,12));
			}
			$fixed_xml_string .= $xml_line."\n";
		}
		else
		{
			$fixed_xml_string .= $xml_line."\n";
		}
	}
	LOGDEB ("Miniserver $ms = Project-Serial: ".$ProjectSerial);
	file_put_contents($file, $fixed_xml_string);
	$xml_project_file_to_parse = $fixed_xml_string;
	unset($fixed_xml_string);

	// Main Import function
	$xml = simplexml_load_string ( $xml_project_file_to_parse, "SimpleXMLElement" ,LIBXML_NOCDATA | LIBXML_NOWARNING );
	if ($xml === false) 
	{
		$errors = libxml_get_errors();
		$importerrors = array();
		foreach ($errors as $error) 
		{
			report_xml_error($ms, $error, explode("\n",$xml_project_file_to_parse));
		}
		libxml_clear_errors();
		$data['error'] = str_replace("<ms>",$ms,$L["ERRORS.ERR_045_PROJECT_XML_ANALYSIS_FAILED"]);
		$data['errorcode'] = "ERR_045";
		$data['Serial'] = $ProjectSerial;
		return $data;
	}
		
	$IconData = array("Icons" => array());
						
	//$pretty = "<Table><tr class='icon_head'><td style='width:64px; height:64px;'></td><td>UID</td><td>Titel</td><td>Typ</td>";
	$xml = convert_icons($xml,array("IconPlace","IconCat","IconState"));

	foreach ($xml->C->C as $value) 
	{
		if ((string) $value['Title'] == 'Symbole') 
		{
			foreach ($value as $symbol_category) 
			{
				foreach ($symbol_category->C as $icon) 
				{
					if (((string) $symbol_category->C['Type'] == 'IconPlace') || ((string) $symbol_category->C['Type'] == 'IconCat') || ((string) $symbol_category->C['Type'] == 'IconState'))
					{
						if (is_readable(dirname($file)."/../../zip/ms_$ms/".$icon["U"].".svg"))
						{
							$pic = file_get_contents (dirname($file)."/../../zip/ms_$ms/".$icon["U"].".svg");
							$class="icon_ok";
						}
						else
						{
							$pic = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" width="32" height="32" viewBox="0 0 31.999999 31.999998" id="svg_missing" version="1.1" inkscape:version="0.91 r13725" sodipodi:docname="NOTFOUND.svg"> <defs id="defs4158" /> <metadata id="metadata4161"> <rdf:RDF> <cc:Work rdf:about=""> <dc:format>image/svg+xml</dc:format> <dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage" /> <dc:title></dc:title> </cc:Work> </rdf:RDF> </metadata> <g inkscape:label="Ebene 1" inkscape:groupmode="layer" id="layer1" transform="translate(0,-1020.3622)"> <path id="path3761" d="m 8.5398993,1028.0718 16.5086917,16.5086" style="fill:none;stroke:#e00000;stroke-width:3.47145557;stroke-linecap:round;stroke-linejoin:round" inkscape:connector-curvature="0" /> <path id="path3763" style="fill:none;stroke:#e00000;stroke-width:3.47145557;stroke-linecap:round;stroke-linejoin:round" inkscape:connector-curvature="0" d="M 25.047669,1028.0718 8.5389776,1044.5804" /></g></svg>';
							$class="icon_bad";
						}
						array_push($IconData["Icons"], array(
							"U" 			=> $icon["U"],
							"Title" 		=> $icon["Title"],
							"Type"			=> $icon["Type"],
							"ImageSrc"		=> $pic,
							"Class"			=> $class));
						
						//$pretty .= "<tr class='".$class."'><td><div style='width:64px; height:64px; ".$pic."'></td><td>".$icon["U"]."</td><td>".$icon["Title"]."</td><td>".$icon["Type"]."</td></tr>";
					}
				}
			}	
		}
	}
	//$pretty .= "</Table>";
	$data['xml'] = $xml->asXML();
	$data['xml'] = str_replace(array("<IoData/>","<Display/>"),array("<IoData></IoData>","<Display></Display>"),$data);
	$data['json'] = json_encode($IconData);
	$data['Serial'] = $ProjectSerial;
	//$data['pretty'] = $pretty;
	return $data;
}
