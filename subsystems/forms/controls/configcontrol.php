<?php

##################################################
#
# Copyright (c) 2004-2006 OIC Group, Inc.
# Written and Designed by James Hunt
#
# This file is part of Exponent
#
# Exponent is free software; you can redistribute
# it and/or modify it under the terms of the GNU
# General Public License as published by the Free
# Software Foundation; either version 2 of the
# License, or (at your option) any later version.
#
# GPL: http://www.gnu.org/licenses/gpl.txt
#
##################################################

if (!defined('EXPONENT')) exit('');

/**
 * Quantity Control
 *
 * @author Adam Kessler
 * @copyright 2004-2008 OIC Group, Inc.
 * @version 0.97
 *
 * @package Subsystems
 * @subpackage Forms
 */

/**
 * Manually include the class file for formcontrol, for PHP4
 * (This does not adversely affect PHP5)
 */
require_once(BASE."subsystems/forms/controls/formcontrol.php");

/**
 * Quantity Control
 *
 * @package Subsystems
 * @subpackage Forms
 */
class configcontrol extends formcontrol {
	public $opts  = array();
	public $title = '';

	function name() { return "Configuration Manager Control"; }
	function isSimpleControl() { return false; }
	
	function __construct($title="Config Manager", $welcome="", $opts=array()) {
		$this->type = "configmanager";
		$this->title = $title;
		$this->welcome = $welcome;
		$this->opts = $opts;
	}

	function controlToHTML($name, $label) {
		$opts_template = get_template_for_action('common', 'configopts', null);
		$opts_template->assign('opts', $this->opts);
		$html = '
			<div class="yui-skin-sam">
        			<div id="demo">
					<div id="leftopts">';
						$html .= $opts_template->render();
		$html .=		'</div>
				</div>
			</div>
		';
	
		$script = "
			var cp = new configPanel(".$this->title.", 'leftopts', '".$this->welcome."', 750, 450);
		        cp.fire();
		";	
		exponent_javascript_toFoot('cfgmgr', 'dragdrop,element,animation,resize,layout', null, '//comment', PATH_RELATIVE.'framework/core/assets/js/exp-layout.js');
                return $html;
	}
	
	function form($object) {
		if (!defined("SYS_FORMS")) require_once(BASE."subsystems/forms.php");
		exponent_forms_initialize();
	
		$form = new form();
		
		if (!isset($object->identifier)) {
			$object->identifier = "";
			$object->caption = "";
			$object->default = "";
			$object->rows = 20;
			$object->cols = 60;
			$object->maxchars = 0;
		} 
		
		$i18n = exponent_lang_loadFile('subsystems/forms/controls/texteditorcontrol.php');
		
		$form->register("identifier",$i18n['identifier'],new textcontrol($object->identifier));
		$form->register("caption",$i18n['caption'], new textcontrol($object->caption));
		$form->register("default",$i18n['default'],  new texteditorcontrol($object->default));
		$form->register("rows",$i18n['rows'], new textcontrol($object->rows,4,false,3,"integer"));
		$form->register("cols",$i18n['cols'], new textcontrol($object->cols,4, false,3,"integer"));
		$form->register("submit","",new buttongroupcontrol($i18n['save'],'',$i18n['cancel']));
		return $form;
	}
	
	function update($values, $object) {
		if ($object == null) $object = new texteditorcontrol();
		if ($values['identifier'] == "") {
			$i18n = exponent_lang_loadFile('subsystems/forms/controls/texteditorcontrol.php');
			$post = $_POST;
			$post['_formError'] = $i18n['id_req'];
			exponent_sessions_set("last_POST",$post);
			return null;
		}
		$object->identifier = $values['identifier'];
		$object->caption = $values['caption'];
		$object->default = $values['default'];
		$object->rows = intval($values['rows']);
		$object->cols = intval($values['cols']);
		$object->maxchars = intval($values['maxchars']);
		$object->required = isset($values['required']);
		
		return $object;
	
	}
	
	function parseData($original_name,$formvalues,$for_db = false) {
		return str_replace(array("\r\n","\n","\r"),'<br />', htmlspecialchars($formvalues[$original_name])); 
	}
	
}

?>
