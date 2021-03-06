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

function smarty_function_help($params,&$smarty) {

    if (HELP_ACTIVE) {
        if (empty($params['module'])) {
            $module = $smarty->_tpl_vars['__loc']->mod;
        } else {
            $module = $params['module'];
        }

        // figure out the params
        $text = empty($params['text']) ? '&nbsp' : $params['text'];

        $title = empty($params['title']) ? 'Get help for '.$params['module'] : $params['title'];

        $class  = 'helplink';
        $class .= empty($params['class']) ? '' : $params['class'];

        $link = help::makeHelpLink($module);
        if (!empty($params['page'])) {
            echo '<a class="'.$class.'" '.$title.' href="'.HELP_URL.'/'.$params['page'].'" target="_blank">'.$text.'</a>';
        } else {
            echo '<a class="'.$class.'" '.$title.' href="'.$link.'" target="_blank">'.$text.'</a>';
        }
        
        expCSS::pushToHead(array(
		    "csscore"=>"admin-global",
		    )
		);

    }
}

?>

