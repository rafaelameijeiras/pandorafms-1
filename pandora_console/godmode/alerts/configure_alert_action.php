<?php 

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Load global vars
global $config;

require_once ($config['homedir'] . '/include/functions_alerts.php');
require_once ($config['homedir'] . '/include/functions_users.php');
enterprise_include_once ('meta/include/functions_alerts_meta.php');

check_login ();

if (! check_acl ($config['id_user'], 0, "LM")) {
	db_pandora_audit("ACL Violation",
		"Trying to access Alert Management");
	require ("general/noaccess.php");
	exit;
}

$id = (int) get_parameter ('id');

$al_action = alerts_get_alert_action ($id);
$pure = get_parameter('pure', 0);

if (defined('METACONSOLE'))
	$sec = 'advanced';
else
	$sec = 'galertas';

if ($al_action !== false){
	// If user tries to edit an action with group=ALL
	if ($al_action['id_group'] == 0){
		// then must have "PM" access privileges
		if (! check_acl ($config['id_user'], 0, "PM")) {
			db_pandora_audit("ACL Violation",
				"Trying to access Alert Management");
			require ("general/noaccess.php");
			exit;
		}
		else {
			// Header
			if (defined('METACONSOLE'))
				alerts_meta_print_header();
			else
				ui_print_page_header (__('Alerts').' &raquo; '.__('Configure alert action'), "images/god2.png", false, "", true);
		}
	} // If user tries to edit an action of others groups
	else {
		$own_info = get_user_info ($config['id_user']);
		if ($own_info['is_admin'] || check_acl ($config['id_user'], 0, "PM"))
			$own_groups = array_keys(users_get_groups($config['id_user'], "LM"));
		else
			$own_groups = array_keys(users_get_groups($config['id_user'], "LM", false));
		$is_in_group = in_array($al_action['id_group'], $own_groups);
		// Then action group have to be in his own groups
		if ($is_in_group) {
			// Header
			if (defined('METACONSOLE'))
				alerts_meta_print_header();
			else
				ui_print_page_header (__('Alerts').' &raquo; '.__('Configure alert action'), "images/god2.png", false, "", true);		
		}
		else {
			db_pandora_audit("ACL Violation",
			"Trying to access Alert Management");
			require ("general/noaccess.php");
			exit;
		}
	}
}
else {
	// Header
	if (defined('METACONSOLE'))
		alerts_meta_print_header();
	else
		ui_print_page_header (__('Alerts').' &raquo; '.__('Configure alert action'), "images/god2.png", false, "", true);
}

$name = '';
$id_command = '';
$group = 0; //All group is 0
$action_threshold = 0; //All group is 0

if ($id) {
	$action = alerts_get_alert_action ($id);
	$name = $action['name'];
	$id_command = $action['id_alert_command'];
	
	$group = $action ['id_group'];
	$action_threshold = $action ['action_threshold'];
}

// Hidden div with help hint to fill with javascript
html_print_div(array('id' => 'help_alert_macros_hint', 'content' => ui_print_help_icon ('alert_macros', true, ui_get_full_url(false, false, false, false)), 'hidden' => true));

$table->width = '98%';
$table->style = array ();
$table->style[0] = 'font-weight: bold';
$table->size = array ();
$table->size[0] = '20%';
$table->data = array ();
$table->data[0][0] = __('Name');
$table->data[0][1] = html_print_input_text ('name', $name, '', 35, 255, true);

$table->data[1][0] = __('Group');

$groups = users_get_groups ();
$own_info = get_user_info ($config['id_user']);
// Only display group "All" if user is administrator or has "PM" privileges
if ($own_info['is_admin'] || check_acl ($config['id_user'], 0, "PM"))
	$display_all_group = true;
else
	$display_all_group = false;
$table->data[1][1] = html_print_select_groups(false, "LW", $display_all_group, 'group', $group, '', '', 0, true);

$table->data[2][0] = __('Command');
$table->data[2][1] = html_print_select_from_sql ('SELECT id, name FROM talert_commands',
	'id_command', $id_command, '', __('None'), 0, true);
$table->data[2][1] .= ' ';
if (check_acl ($config['id_user'], 0, "PM")){
	$table->data[2][1] .= html_print_image ('images/add.png', true);
	$table->data[2][1] .= '<a href="index.php?sec='.$sec.'&sec2=godmode/alerts/configure_alert_command&pure='.$pure.'">';
	$table->data[2][1] .= __('Create Command');
	$table->data[2][1] .= '</a>';
}
$table->data[2][1] .= '<div id="command_description" style=""></div>';
$table->data[3][0] = __('Threshold');
$table->data[3][1] = html_print_input_text ('action_threshold', $action_threshold, '', 5, 7, true);
$table->data[3][1] .= ' '.__('seconds') . ui_print_help_icon ('action_threshold', true, ui_get_full_url(false, false, false, false));
$table->data[4][0] = __('Command preview');
$table->data[4][1] = html_print_textarea ('command_preview', 10, 30, '',
	'disabled="disabled"', true);
$row = 5;
for ($i=1; $i<=10; $i++) {
	$table->data['field'.$i][0] = html_print_image('images/spinner.gif',true);
	$table->data['field'.$i][1] = html_print_image('images/spinner.gif',true);
	// Store the value in a hidden to keep it on first execution
	$table->data['field'.$i][1] .= html_print_input_hidden('field'.$i.'_value', isset($action['field'.$i]) ? $action['field'.$i] : '', true);
}

echo '<form method="post" action="index.php?sec='.$sec.'&sec2=godmode/alerts/alert_actions&pure='.$pure.'">';
html_print_table ($table);

echo '<div class="action-buttons" style="width: '.$table->width.'">';
if ($id) {
	html_print_input_hidden ('id', $id);
	html_print_input_hidden ('update_action', 1);
	html_print_submit_button (__('Update'), 'create', false, 'class="sub upd"');
}
else {
	html_print_input_hidden ('create_action', 1);
	html_print_submit_button (__('Create'), 'create', false, 'class="sub wand"');
}
echo '</div>';
echo '</form>';

ui_require_javascript_file ('pandora_alerts');
?>

<script type="text/javascript">
$(document).ready (function () {
	<?php
	if ($id_command) {
	?>
		original_command = "<?php
			$command = alerts_get_alert_command_command ($id_command);
			$command = io_safe_output($command);
			echo addslashes($command);
			?>";
		render_command_preview (original_command);
		command_description = "<?php echo str_replace("\r\n","<br>",addslashes(io_safe_output(alerts_get_alert_command_description ($id_command)))); ?>";
		
		render_command_description(command_description);
	<?php
	}
	?>
	
	$("#id_command").change (function () {
		values = Array ();
		values.push ({name: "page",
			value: "godmode/alerts/alert_commands"});
		values.push ({name: "get_alert_command",
			value: "1"});
		values.push ({name: "id",
			value: this.value});
		jQuery.get (<?php echo "'" . ui_get_full_url("ajax.php", false, false, false) . "'"; ?>,
			values,
			function (data, status) {
				original_command = js_html_entity_decode (data["command"]);
				render_command_preview (original_command);
				command_description = js_html_entity_decode (data["description"]);
				render_command_description(command_description);
				for (i=1; i<=10; i++) {
					var old_value = '';
					// Only keep the value if is provided from hidden (first time)
					if ($("[name=field"+i+"_value]").attr('id') == "hidden-field" + i + "_value") {
						old_value = $("[name=field"+i+"_value]").val();
					}
					
					// If the row is empty, hide de row
					if(data["fields_rows"][i] == '') {
						$('#table1-field'+i).hide();
					}
					else {
						$('#table1-field'+i).replaceWith(data["fields_rows"][i]);
						$("[name=field"+i+"_value]").val(old_value);
						// Add help hint only in first field
						if(i == 1) {
							var td_content = $('#table1-field'+i).find('td').eq(0);
							td_content.html(td_content.html() + $('#help_alert_macros_hint').html());
						}
						$('#table1-field').show();
					}
				}
				
				render_command_preview(original_command);
				
				$(".fields").keyup (function() {
					render_command_preview(original_command);
				});
			},
			"json"
		);
	});
	
	// Charge the fields of the command
	$("#id_command").trigger('change');
});

</script>
