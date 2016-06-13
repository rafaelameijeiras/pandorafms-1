<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2016 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * @package General
 */

global $config;

include_once("include/functions_update_manager.php");
$last_message = update_manger_get_last_message ();

if ($last_message === false) return false;

update_manger_set_read_message($last_message["svn_version"], 1);
update_manager_remote_read_messages ($last_message["svn_version"]);

// Prints first step pandora registration
echo '<div id="message_id_dialog" title="' .
	'[' . $last_message["svn_version"] . '] ' . $last_message['db_field_value'] . '">';
	
	echo '<div>';
		echo $last_message["data"];
	echo '</div>';
echo '</div>';

?>

<script type="text/javascript" language="javascript">
/* <![CDATA[ */

$(document).ready (function () {
	
	$("#message_id_dialog").dialog({
		resizable: true,
		draggable: true,
		modal: true,
		width: 850
	});
	
	$(".ui-widget-overlay").css("background", "#000");
	$(".ui-widget-overlay").css("opacity", 0.6);
	
});

/* ]]> */
</script>
