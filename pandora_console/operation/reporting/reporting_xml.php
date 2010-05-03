<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2009 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.


function xml_array ($array) {
	foreach ($array as $name => $value) {
		if (is_int ($name)) {
			echo "<object id=\"".$name."\">";
			$name = "object";
		} else {
			echo "<".$name.">";
		}
		
		if (is_array ($value)) {
			xml_array ($value);
		} else {
			echo $value;
		}
		
		echo "</".$name.">";
	}
}

// Login check
if (isset ($_GET["direct"])) {
	/* 
	This is in case somebody wants to access the XML directly without
	having the possibility to login and handle sessions
	 
	Use this URL: https://yourserver/pandora_console/operation/reporting/reporting_xml.php?id=<reportid>&direct=1
	
	Although it's not recommended, you can put your login and password
	in a GET request (append &nick=<yourlogin>&password=<password>). 
	 	 
	You SHOULD put it in a POST but some programs
	might not be able to handle it without extensive re-programming
	Either way, you should have a read-only user for getting reports
	 
	XMLHttpRequest can do it (example):
	 
	var reportid = 3;
	var login = "yourlogin";
	var password = "yourpassword";
	var url = "https://<yourserver>/pandora_console/operation/reporting/reporting_xml.php?id="+urlencode(reportid)+"&direct=1";
	var params = "nick="+urlencode(login)+"&pass="+urlencode(password);
	var xmlHttp = new XMLHttpRequest();
	var textout = "";
	try { 
		xmlHttp.open("POST", url, false);
		xmlHttp.send(params);
		if(xmlHttp.readyState == 4 && xmlHttp.status == 200) {
			textout = xmlHttp.responseXML;
		}
	} 
	catch (err) {
		alert ("error");
	}
	*/
	require_once ("../../include/config.php");
	require_once ("../../include/functions_reporting.php");
	
	if (!isset ($config["auth"])) {
		require_once ("include/auth/mysql.php");
	} else {
		require_once ("include/auth/".$config["auth"]["scheme"].".php");
	}
	
	$nick = get_parameter ("nick");
	$pass = get_parameter ("pass");
	
	$nick = process_user_login ($nick, $pass);
	
	if ($nick !== false) {
		unset ($_GET["sec2"]);
		$_GET["sec"] = "general/logon_ok";
		logon_db ($nick, $_SERVER['REMOTE_ADDR']);
		$_SESSION['id_usuario'] = $nick;
		$config['id_user'] = $nick;
		//Remove everything that might have to do with people's passwords or logins
		unset ($_GET['pass'], $pass, $_POST['pass'], $_REQUEST['pass'], $login_good);
	} else {
		// User not known
		$login_failed = true;
		require_once ('general/login_page.php');
		audit_db ($nick, $_SERVER['REMOTE_ADDR'], "Logon Failed", "Invalid login: ".$nick);
		exit;
	}
} else {
	require_once ("include/config.php");
	require_once ("include/functions_reporting.php");
	
	if (!isset ($config["auth"])) {
		require_once ("include/auth/mysql.php");
	} else {
		require_once ("include/auth/".$config["auth"]["scheme"].".php");
	}	
}

check_login ();

$id_report = (int) get_parameter ('id');

if (! $id_report) {
	audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "HACK Attempt",
			  "Trying to access graph viewer without valid ID");
	require ("general/noaccess.php");
	exit;
}

$report = get_db_row ('treport', 'id_report', $id_report);

$report["datetime"] = get_system_time();

if (! give_acl ($config['id_user'], $report['id_group'], "AR")) {
	audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "ACL Violation","Trying to access graph reader");
	include ("general/noaccess.php");
	exit;
}

/* Check if the user can see the graph */
if ($report['private'] && ($report['id_user'] != $config['id_user'] && ! dame_admin ($config['id_user']))) {
	return;
}

header ('Content-type: application/xml; charset="utf-8"', true);

echo '<?xml version="1.0" encoding="UTF-8" ?>'; //' - this is to mislead highlighters giving crap about the PHP closing tag

$date = (string) get_parameter ('date', date ('Y-m-j'));
$time = (string) get_parameter ('time', date ('h:iA'));

$datetime = strtotime ($date.' '.$time);

if ($datetime === false || $datetime == -1) {
	echo "<error>Invalid date selected</error>"; //Not translatable because this is an error message and might have to be used on the other end
	exit;
}

$group_name = get_group_name ($report['id_group']);
$contents = get_db_all_rows_field_filter ('treport_content', 'id_report', $id_report, '`order`');

$time = get_system_time ();
echo '<report>';
echo '<generated><unix>'.$time.'</unix>';
echo '<rfc2822>'.date ("r",$time).'</rfc2822></generated>';

$xml["id"] = $id_report;
$xml["name"] = $report['name'];
$xml["description"] = $report['description'];
$xml["group"]["id"] = $report['id_group'];
$xml["group"]["name"] = $group_name;

if ($contents === false) {
	$contents = array ();
};

xml_array ($xml);

echo '<reports>';
$counter = 0;

foreach ($contents as $content) {
	echo '<object id="'.$counter.'">';
	$data = array ();
	$data["module"] = get_db_value ('nombre', 'tagente_modulo', 'id_agente_modulo', $content['id_agent_module']);
	$data["agent"] = get_agentmodule_agent_name ($content['id_agent_module']);
	$data["period"] = human_time_description ($content['period']);
	$data["uperiod"] = $content['period'];
	$data["type"] = $content["type"];

	switch ($content["type"]) {
		case 1:
		case 'simple_graph':	
			$data["title"] = __('Simple graph');
			$data["objdata"]["img"] = 'include/fgraph.php?tipo=sparse&amp;id='.$content['id_agent_module'].'&amp;height=230&amp;width=720&amp;period='.$content['period'].'&amp;date='.$datetime.'&amp;avg_only=1&amp;pure=1';
			break;
		case 2:
		case 'custom_graph':
			$graph = get_db_row ("tgraph", "id_graph", $content['id_gs']);
			$data["title"] = __('Custom graph');
			$data["objdata"]["img_name"] = $graph["name"];
	
			$result = get_db_all_rows_field_filter ("tgraph_source","id_graph",$content['id_gs']);
			$modules = array ();
			$weights = array ();
		
			if ($result === false) {
				$result = array();
			}
	
			foreach ($result as $content2) {
				array_push ($modules, $content2['id_agent_module']);
				array_push ($weights, $content2["weight"]);
			}
	
			$data["objdata"]["img"] = 'include/fgraph.php?tipo=combined&amp;id='.implode (',', $modules).'&amp;weight_l='.implode (',', $weights).'&amp;height=230&amp;width=720&amp;period='.$content['period'].'&amp;date='.$datetime.'&amp;stacked='.$graph["stacked"].'&amp;pure=1';
			break;
		case 3:
		case 'SLA':
			$data["title"] = __('S.L.A.');
	
			$slas = get_db_all_rows_field_filter ('treport_content_sla_combined','id_report_content', $content['id_rc']);
			if ($slas === false) {
				$data["objdata"]["error"] = __('There are no SLAs defined');
				$slas = array ();
			}
	
			$data["objdata"]["sla"] = array ();
			$sla_failed = false;
			foreach ($slas as $sla) {
				$sla_data = array ();
				$sla_data["agent"] = get_agentmodule_agent_name ($sla['id_agent_module']);
				$sla_data["module"] = get_agentmodule_name ($sla['id_agent_module']);
				$sla_data["max"] = $sla['sla_max'];
				$sla_data["min"] = $sla['sla_min'];
				$sla_value = get_agentmodule_sla ($sla['id_agent_module'], $content['period'], $sla['sla_min'], $sla['sla_max'], $datetime);
				if ($sla_value === false) {
					$sla_data["error"] = __('Unknown');
				} else {
					if ($sla_value < $sla['sla_limit']) {
						$sla_data["failed"] = true;
					}
					$sla_data["value"] = format_numeric ($sla_value);
				}
				array_push ($data["objdata"]["sla"], $sla_data);
			}
			break;
//		case 4:
//		case 'event_report':	
//			$data["title"] = __("Event report");
//			$table_report = event_reporting ($report['id_group'], $content['period'], $datetime, true);
//			$data["objdata"] = "<![CDATA[";
//			$data["objdata"] .= print_table ($table_report, true);
//			$data["objdata"] .= "]]>";
//			break;
//		case 5:
//		case 'alert_report':
//			$data["title"] = __('Alert report');
//			$data["objdata"] = "<![CDATA[";
//			$data["objdata"] .= alert_reporting ($report['id_group'], $content['period'], $datetime, true);
//			$data["objdata"] .= "]]>";
//			break;
		case 6:
		case 'monitor_report':
			$data["title"] = __('Monitor report');
			$monitor_value = format_numeric (get_agentmodule_sla ($content['id_agent_module'], $content['period'], 1, false, $datetime));
			$data["objdata"]["good"] = $monitor_value;
			$data["objdata"]["bad"] = format_numeric (100 - $monitor_value, 2);
			break;
		case 7:
		case 'avg_value':
			$data["title"] = __('Avg. Value');
			$data["objdata"] = format_numeric (get_agentmodule_data_average ($content['id_agent_module'], $content['period'], $datetime));
			break;
		case 8:
		case 'max_value':
			$data["title"] = __('Max. Value');
			$data["objdata"] = format_numeric (get_agentmodule_data_max ($content['id_agent_module'], $content['period'], $datetime));
			break;
		case 9:
		case 'min_value':
			$data["title"] = __('Min. Value');
			$data["objdata"] = format_numeric (get_agentmodule_data_min ($content['id_agent_module'], $content['period'], $datetime));
			break;
		case 10:
		case 'sumatory':
			$data["title"] = __('Sumatory');
			$data["objdata"] = format_numeric (get_agentmodule_data_sum ($content['id_agent_module'], $content['period'], $datetime));
			break;
//		case 11:
//		case 'general_group_report':
//			$data["title"] = __('Group');
//			$data["objdata"] = "<![CDATA[";
//			$data["objdata"] .= print_group_reporting ($report['id_group'], true);
//			$data["objdata"] .= "]]>";
//			break;
//		case 12:
//		case 'monitor_health':
//			$data["title"] = __('Monitor health');
//			$data["objdata"] = "<![CDATA[";
//			$data["objdata"] .= monitor_health_reporting ($report['id_group'], $content['period'], $datetime, true);
//			$data["objdata"] .= "]]>";
//			break;
//		case 13:
//		case 'agents_detailed':
//			$data["title"] = __('Agents detailed view');
//			$data["objdata"] = "<![CDATA[";
//			$data["objdata"] .= get_group_agents_detailed_reporting ($report['id_group'], $content['period'], $datetime, true);
//			$data["objdata"] .= "]]>";
//			break;
		case 'agent_detailed_event':
		case 'event_report_agent':
			$data["title"] = __('Agent detailed event');
			
			$data["objdata"]["event_report_agent"] = array ();
			
			$date = get_system_time ();
			
			$events = get_agent_events ($content['id_agent'], $content['period'], $date );
			if (empty ($events)) {
				$events = array ();
			}
			
			foreach ($events as $event) {
				$objdata = array ();
				$objdata['event'] = $event['evento'];
				$objdata['event_type'] = $event['event_type'];
				$objdata['criticity'] = get_priority_name($event['criticity']);
				$objdata['count'] = $event['count_rep'];
				$objdata['timestamp'] = $event['time2'];
				array_push ($data["objdata"]["event_report_agent"], $objdata);
			}
			break;
		case 'text':
			$data["title"] = __('Text');
			$data["objdata"] = "<![CDATA[";
			$data["objdata"] .= safe_output($content["text"]);
			$data["objdata"] .= "]]>";
			break;
		case 'sql':
			$data["title"] = __('SQL');
			
			//name tags of row
			if ($content['header_definition'] != '') {
				$tags = explode('|', $content['header_definition']);
			}
			
			if ($content['treport_custom_sql_id'] != 0) {
				$sql = get_db_value_filter('`sql`', 'treport_custom_sql', array('id' => $content['treport_custom_sql_id']));
			}
			else {
				$sql = $content['external_source'];
			}
			
			$result = get_db_all_rows_sql($sql);
			if ($result === false) {
				$result = array();
			}
			
			if (isset($result[0])) {
				for ($i = 0; $i < (count($result[0]) - count($tags)); $i) {
					$tags[] = 'unname_' . $i;
				}
			}
			
			$data["objdata"]["data"] = array ();
			
			foreach ($result as $row) {
				$objdata = array ();
				$i = 0;
				foreach ($row as $column) {
					$objdata[$tags[$i]] = $column;
					$i++;
				}
				array_push($data["objdata"]["data"], $objdata);
			}
			break;
		case 'event_report_module':
			$data["title"] = __('Agents detailed event');
			$data["objdata"]["event_report_module"] = array();
			
			$events = get_module_detailed_event_reporting($content['id_agent_module'], $content['period'], $report["datetime"], true, false);
			
			foreach ($events->data as $eventRow) {
				$objdata = array();
				$objdata['event_name'] = $eventRow[0];
				$objdata['event_type'] = $eventRow[1];
				$objdata['criticity'] = $eventRow[2];
				$objdata['count'] = $eventRow[3];
				$objdata['timestamp'] = $eventRow[4];
				
				array_push($data["objdata"]["event_report_module"], $objdata);
			}
			break;
		case 'alert_report_module':
			$data["title"] = __('Alert report module');
			$data["objdata"]["alert_report_module"] = array();
		
			$alerts = alert_reporting_module ($content['id_agent_module'], $content['period'], $report["datetime"], true, false);
			
			foreach ($alerts->data as $row) {
				$objdata = array();
				
				$actionsHtml = strip_tags($row[2], '<li>');
				$actions = explode('</li>', $actionsHtml);
				
				$objdata['template'] = $row[1];
				
				$objdata['action'] = array();
				foreach ($actions as $action) {
					$actionText = strip_tags($action);
					if ($actionText == '') {
						continue;
					}
					$objdata['action'][] = $actionText;
				}
				
				$firedHtml = strip_tags($row[3], '<li>');
				$fireds= explode('</li>', $firedHtml);
				
				$objdata['fired'] = array();
				foreach ($fireds as $fired) {
					$firedText = strip_tags($fired);
					if ($firedText == '') {
						continue;
					}
					$objdata['fired'][] = $firedText;
				}
				array_push($data["objdata"]["alert_report_module"], $objdata);
			}
			break;
		case 'alert_report_agent':
			$data["title"] = __('Alert report agent');
			
			$alerts = alert_reporting_agent ($content['id_agent'], $content['period'], $report["datetime"], true, false);
			$data["objdata"]["alert_report_agent"] = array();
			
			foreach ($alerts->data as $row) {
				$objdata = array();
				
				$objdata['module'] = $row[0];
				$objdata['template'] = $row[1];
				
				$actionsHtml = strip_tags($row[2], '<li>');
				$actions = explode('</li>', $actionsHtml);
				
				$objdata['action'] = array();
				foreach ($actions as $action) {
					$actionText = strip_tags($action);
					if ($actionText == '') {
						continue;
					}
					$objdata['action'][] = $actionText;
				}
				
				$firedHtml = strip_tags($row[3], '<li>');
				$fireds= explode('</li>', $firedHtml);
				
				$objdata['fired'] = array();
				foreach ($fireds as $fired) {
					$firedText = strip_tags($fired);
					if ($firedText == '') {
						continue;
					}
					$objdata['fired'][] = $firedText;
				}
				
				array_push($data["objdata"]["alert_report_agent"], $objdata);
			}
			break;
		case 'url':
			$data["title"] = __('Import text from URL');
				
			$curlObj = curl_init();
			
			curl_setopt($curlObj, CURLOPT_URL, $content['external_source']);
			curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
	        $output = curl_exec($curlObj);
			curl_close($curlObj);
	        
			$data["objdata"] = $output;
			break;
		case 'database_serialized':
			$data["title"] = __('Serialize data');
			
			//Create the head
			$tags = array();
			if ($content['header_definition'] != '') {
				$tags = explode('|', $content['header_definition']);
			}
			array_unshift($tags, 'Date');
			
			$datelimit = $report["datetime"] - $content['period'];
			
			$result = get_db_all_rows_sql('SELECT *
				FROM tagente_datos_string
				WHERE id_agente_modulo = ' . $content['id_agent_module'] . '
				AND utimestamp > ' . $datelimit . ' AND utimestamp <= ' . $report["datetime"]);
			if ($result === false) {
				$result = array();
			}
			
			$data["objdata"]["data"] = array ();
			foreach ($result as $row) {
				$date = date ($config["date_format"], $row['utimestamp']);
				$serialized = $row['datos'];
				$rowsUnserialize = explode($content['line_separator'], $serialized);
				foreach ($rowsUnserialize as $rowUnser) {
					$columnsUnserialize = explode($content['column_separator'], $rowUnser);
					array_unshift($columnsUnserialize, $date);
					
					$objdata = array ();
					$i = 0;
					foreach ($columnsUnserialize as $column) {
						$objdata[$tags[$i]] = $column;
						$i++;
					}
					array_push($data["objdata"]["data"], $objdata);
				}
			}
			break;
		case 'TTRT':
			$ttr = get_agentmodule_ttr ($content['id_agent_module'], $content['period'], $report["datetime"]);
			if ($ttr != 0) $ttr = human_time_description_raw ($ttr);
			
			$data["title"] = __('TTRT');
			$data["objdata"] = format_numeric($ttr);
			break;
		case 'TTO':
			$tto = get_agentmodule_tto ($content['id_agent_module'], $content['period'], $report["datetime"]);
			if ($tto != 0) $tto = human_time_description_raw ($tto);
				
			$data["title"] =  __('TTO');
			$data["objdata"] = format_numeric($tto);
			break;
		case 'MTBF':
			$mtbf = get_agentmodule_mtbf ($content['id_agent_module'], $content['period'], $report["datetime"]);
			if ($mtbf != 0) $mtbf = human_time_description_raw ($mtbf);
				
			$data["title"] = __('MTBF');
			$data["objdata"] = format_numeric($mtbf);
			break;
		case 'MTTR':
			$mttr = get_agentmodule_mttr ($content['id_agent_module'], $content['period'], $report["datetime"]);
			if ($mttr != 0) $mttr = human_time_description_raw ($mttr);
				
			$data["title"] = __('MTTR');
			$data["objdata"] = format_numeric($mttr);
			break;
	}
	xml_array ($data);
	echo '</object>';
	$counter++;
}

echo '</reports></report>';
?>
