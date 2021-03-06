<?php

##################################################
#
# Copyright (c) 2004-2008 OIC Group, Inc.
# Written and Designed by Adam Kessler
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

class instorepickupcalculator extends shippingcalculator {
	/*
	 * Returns the name of the shipping calculator, for use in the Shipping Administration Module
	 */
	//overridden methods:
	public function name() { return exponent_lang_getText('In Store Pickup'); }
	public function description() { return exponent_lang_getText('In store pickup calculator'); }
	public function hasUserForm() { return false; }
	public function hasConfig() { return true; }
	public function addressRequired() { return false; }
	public function isSelectable() { return true; }

    public $shippingmethods = array("01"=>"In Store Pickup");

    public function getRates($order) {        
	    $rates = array('01'=>array('id' =>01,'title'=>$this->shippingmethods['01'],'cost'=>$this->configdata['rate']));
	    return $rates;
    }	
    
   	public function configForm() { 
   	    return BASE.'framework/modules/ecommerce/shippingcalculators/views/flatratecalculator/configure.tpl';
   	}
	
	//process config form
	function parseConfig($values) {
	    $config_vars = array('rate');
	    foreach ($config_vars as $varname) {
	        if ($varname == 'rate') {
	            $config[$varname] = isset($values[$varname]) ? preg_replace("/[^0-9.]/","",$values[$varname]) : null;    
	        } else {
	            $config[$varname] = isset($values[$varname]) ? $values[$varname] : null;
	        }
	        
	    }
	    
		return $config;
	}
	
	function availableMethods() {
	    return $this->shippingmethods;
	}
}

?>
