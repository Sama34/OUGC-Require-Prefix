<?php

/***************************************************************************
 *
 *   OUGC Require Prefix plugin (/inc/plugins/ougc_requireprefix.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *   Select forums where prefixes are required to create threads.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Add our hooks
defined('IN_ADMINCP') or $plugins->add_hook('datahandler_post_validate_thread', 'ougc_requireprefix');

//Necessary plugin information for the ACP plugin manager.
function ougc_requireprefix_info()
{
	global $lang;
    $lang->load('ougc_requireprefix');

	return array(
		'name'			=> 'OUGC Require Prefix',
		'description'	=> $lang->ougc_requireprefix_d,
		'website'		=> 'http://udezain.com.ar/',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://udezain.com.ar/',
		'version'		=> '1.0',
		'compatibility'	=> '16*'
	);
}

// _activate routine
function ougc_requireprefix_activate()
{
	if(!ougc_requireprefix_is_installed())
	{
		global $db, $lang;
		$lang->load('ougc_requireprefix');

		$query = $db->simple_select('settings', 'MAX(disporder) AS max_disporder', 'gid=\'13\'');
		$disporder = (int)$db->fetch_field($query, 'max_disporder');

		$db->insert_query('settings', array(
			'name'			=> 'ougc_requireprefix',
			'title'			=> $db->escape_string($lang->ougc_requireprefix_forums),
			'description'	=> $db->escape_string($lang->ougc_requireprefix_forums_d),
			'optionscode'	=> 'text',
			'value'			=> '',
			'disporder'		=> $disporder+1,
			'gid'			=> 13
		));
	}
}

// _is_installed routine
function ougc_requireprefix_is_installed()
{
	global $db;

	static $installed = null;
	if(!isset($installed))
	{
		$installed = (bool)$db->fetch_field($db->simple_select('settings', 'sid', 'name=\'ougc_requireprefix\' AND gid=\'13\''), 'sid');
	}

	return $installed;
}

// _uninstall routine
function ougc_requireprefix_uninstall()
{
	global $db;

	// Get settings from this plugin
	$query = $db->simple_select('settings', 'sid', 'name=\'ougc_requireprefix\' AND gid=\'13\'');

	// Delete the group and all its settings.
	while($sid = $db->fetch_field($query, 'sid'))
	{
		$db->delete_query('settings', 'sid=\''.(int)$sid.'\'');
	}
}

function ougc_requireprefix(&$dh)
{
	global $settings, $cache;
	$prefix_cache = $cache->read('threadprefixes');

	$fids = array_unique(array_map('intval', explode(',', $settings['ougc_requireprefix'])));
	if(in_array($dh->data['fid'], $fids) && !isset($prefix_cache[$dh->data['prefix']]))
	{

		$has_prefix = false;
		foreach((array)$prefix_cache as $prefix)
		{
			if($prefix['forums'] != '-1' && !in_array($dh->data['fid'], explode(',', $prefix['forums'])))
			{
				continue;
			}
			if($prefix['groups'] != '-1' && !ougc_requireprefix_check_groups($prefix['groups']))
			{
				continue;
			}
			$has_prefix = true;
			break;
		}

		if($has_prefix)
		{
			global $lang;

			$dh->set_error((isset($lang->ougc_requireprefix_error) ? $lang->ougc_requireprefix_error : 'Thread prefixes are required in this forum, please do select a prefix before continuing.'));
		}
	}
}

// Check if user meets user group memberships
function ougc_requireprefix_check_groups($groups_comma)
{
	if(empty($groups))
	{
		return true;
	}

	global $mybb;
	$usergroups = explode(',', $mybb->user['additionalgroups']);
	$usergroups[] = $mybb->user['usergroup'];

	return (bool)array_intersect(array_map('intval', explode(',', $groups)), array_map('intval', $usergroups));
}