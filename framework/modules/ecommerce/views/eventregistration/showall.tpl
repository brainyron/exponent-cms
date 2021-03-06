{*
 * Copyright (c) 2007-2008 OIC Group, Inc.
 * Written and Designed by Adam Kessler
 *
 * This file is part of Exponent
 *
 * Exponent is free software; you can redistribute
 * it and/or modify it under the terms of the GNU
 * General Public License as published by the Free
 * Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * GPL: http://www.gnu.org/licenses/gpl.txt
 *
 *}
<div class="store showall">
    <h1>{$moduletitle|default:""}</h1>
    <div id="products">
        {$page->links}
	<table class="exp-skin-table">
            <thead>
        	<tr>{$page->header_columns}<th></th></tr>
            </thead>
	    <tbody>
		{foreach from=$page->records item=listing name=listings}
                <tr class="{cycle values="odd,even"}">
                    <td><a href="{link controller=store action=edit id=$listing->id}">{$listing->title}</a></td>
                    <td>{$listing->eventdate|date_format:"%b %d,'%y"} {$listing->event_starttime|date_format:"%l:%M %p"}</td>
                    <td>{$listing->number_of_registrants} of {$listing->quantity}</td>
                    <td>
			{icon img=groupperms.png action=view_registrants id=$listing->id title="View Registrants"}		
                </tr>
                {/foreach}
	    </tbody>
        </table>
        {$page->links}
    </div>
</div>
