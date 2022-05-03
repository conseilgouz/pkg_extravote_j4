<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2022 www.conseilgouz.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
-------------------------------------------------------------------------*/

// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;

class plgContentExtraVoteInstallerScript
{
	private $min_joomla_version      = '4.0';
	private $min_php_version         = '7.2';
 
 function preflight($type, $parent)
    {
		if ( ! $this->passMinimumJoomlaVersion())
		{
			return false;
		}
		if ( ! $this->passMinimumPHPVersion())
		{
			return false;
		}
    }
	
	function install($parent) {
		echo Text::_('PLG_CONTENT_EXTRAVOTE_ENABLED_0');
	}
	function update($parent) {
		echo Text::_('PLG_CONTENT_EXTRAVOTE_ENABLED_'.plgContentExtraVoteInstallerScript::isEnabled());
	}
	function isEnabled() {
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('enabled'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote('extravote'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('content'));
		$db->setQuery($query);
		
		return $db->loadResult();
	}
    function postflight($type, $parent)
    {
		if (($type=='install') || ($type == 'update')) { // remove obsolete dir/files
			$this->postinstall_cleanup();
		}
	}
	private function postinstall_cleanup() {
		$obsloteFolders = ['assets', 'language'];
		foreach ($obsloteFolders as $folder)
		{
			$f = JPATH_SITE . '/plugins/content/extravote/' . $folder;
			if (!@file_exists($f) || !is_dir($f) || is_link($f))
			{
				continue;
			}
			Folder::delete($f);
		}
	}
	// Check if Joomla version passes minimum requirement
	private function passMinimumJoomlaVersion()
	{
		if (version_compare(JVERSION, $this->min_joomla_version, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				'Incompatible Joomla version : found <strong>' . JVERSION . '</strong>, Minimum : <strong>' . $this->min_joomla_version . '</strong>',
				'error'
			);

			return false;
		}

		return true;
	}

	// Check if PHP version passes minimum requirement
	private function passMinimumPHPVersion()
	{

		if (version_compare(PHP_VERSION, $this->min_php_version, '<'))
		{
			Factory::getApplication()->enqueueMessage(
					'Incompatible PHP version : found  <strong>' . PHP_VERSION . '</strong>, Minimum <strong>' . $this->min_php_version . '</strong>',
				'error'
			);
			return false;
		}

		return true;
	}
	
}