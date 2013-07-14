<?php

/***************************************************************************
 *
 *   OUGC Require Prefix plugin (/inc/plugins/ougc_requireprefix.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 - 2013 Omar Gonzalez
 *   
 *   Website: http://omarg.me
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
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_formcontainer_output_row', 'ougc_requireprefix_settings');
	$plugins->add_hook('admin_config_settings_change_commit', 'ougc_requireprefix_settings_do');
}
else
{
	$plugins->add_hook('datahandler_post_validate_thread', 'ougc_requireprefix');
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

//Necessary plugin information for the ACP plugin manager.
function ougc_requireprefix_info()
{
	global $lang;
    $lang->load('ougc_requireprefix');

	return array(
		'name'			=> 'OUGC Require Prefix',
		'description'	=> $lang->ougc_requireprefix_d,
		'website'		=> 'http://mods.mybb.com/view/ougc-require-prefix',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '1.0',
		'compatibility'	=> '16*',
		'guid'			=> '880c8f78b84a26968e356500498c85a4',
		'pl_ver'		=> 11
	);
}

// _activate routine
function ougc_requireprefix_activate()
{
	global $lang, $PL;
	$info = ougc_requireprefix_info();

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message($lang->sprintf($lang->ougc_requireprefix_pl, $info['pl_ver']), 'error');
		admin_redirect('index.php?module=config-plugins');
	}
	
	$PL or require_once PLUGINLIBRARY;

	// Add our settings
	$PL->settings('ougc_requireprefix', $lang->ougc_requireprefix, $lang->ougc_requireprefix_d, array(
		'forums'	=> array(
			'title'			=> $lang->ougc_requireprefix_forums,
			'description'	=> $lang->ougc_requireprefix_forums_d,
			'optionscode'	=> 'text',
			'value'			=> '',
		),
	));
}

// _is_installed routine
function ougc_requireprefix_is_installed()
{
	global $settings;

	return isset($settings['ougc_requireprefix_forums']);
}

// _uninstall routine
function ougc_requireprefix_uninstall()
{
	global $PL;
	$PL or require_once PLUGINLIBRARY;

	// Delete the setting
	$PL->settings_delete('ougc_requireprefix');
}

// Hijack settings page
function ougc_requireprefix_settings(&$args)
{
	if($args['row_options']['id'] == 'row_setting_ougc_requireprefix_forums')
	{
		global $form, $settings;

		$args['content'] = $form->generate_forum_select('ougc_requireprefix_forums[]', explode(',', (string)$settings['ougc_requireprefix_forums']), array('multiple' => true, 'size' => 5));
	}
}

// Save changes to settings
function ougc_requireprefix_settings_do()
{
	global $db, $mybb;

	$query = $db->simple_select('settinggroups', 'name', 'gid=\''.(int)$mybb->input['gid'].'\'');

	if($db->fetch_field($query, 'name') != 'ougc_requireprefix')
	{
		return;
	}

	$fids = array_filter(array_unique(array_map('intval', (array)$mybb->input['ougc_requireprefix_forums'])));
	$fids = (string)implode(',', $fids);

	$db->update_query('settings', array('value' => $fids), 'name=\'ougc_requireprefix_forums\'');

	rebuild_settings();
}

// Thread validation
function ougc_requireprefix(&$dh)
{
	global $settings, $cache;
	$prefix_cache = $cache->read('threadprefixes');

	$fids = array_unique(array_map('intval', explode(',', $settings['ougc_requireprefix_forums'])));
	if(in_array($dh->data['fid'], $fids) && !isset($prefix_cache[$dh->data['prefix']]))
	{
		global $PL;
		$PL or require_once PLUGINLIBRARY;

		$has_prefix = false;
		foreach((array)$prefix_cache as $prefix)
		{
			if($prefix['forums'] != '-1' && !in_array($dh->data['fid'], explode(',', $prefix['forums'])))
			{
				continue;
			}
			if(!in_array($prefix['groups'], array('', '-1'))  && !(bool)$PL->is_member($prefix['groups']))
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