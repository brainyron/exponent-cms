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

class youtubeController extends expController {
	//protected $basemodel_name = '';
	public $useractions = array('showall'=>'Display a YouTube Video');

    public $remove_configs = array('ealerts','tags','files','rss','comments');

	function name() { return $this->displayname(); } //for backwards compat with old modules
	function displayname() { return "YouTube"; }
	function description() { return "Display youtube videos on your page."; }
	function author() { return "Phillip Ball - OIC Group, Inc"; }
	function hasSources() { return true; }
	function hasViews() { return true; }
	function hasContent() { return true; }
	function supportsWorkflow() { return false; }
	function isSearchable() { return false; }	
	
	function showall() {
        $yt = new $this->basemodel_name();
        $vids = $yt->find('all',$this->aggregateWhereClause());

        if (!empty($this->config['width'])&&!empty($this->config['height'])) {
            foreach ($vids as $key=>$val) {
                $val->embed_code = preg_replace("/height=\"\d+\"/", 'height='.$this->config['height'], $val->embed_code);
                $val->embed_code = preg_replace("/width=\"\d+\"/", 'width='.$this->config['width'], $val->embed_code);
            }
        }
        
        
        assign_to_template(array('items'=>$vids));
    }
	
}

?>
