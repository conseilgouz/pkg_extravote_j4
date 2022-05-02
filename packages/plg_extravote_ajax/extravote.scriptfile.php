<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Ajax Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from ExtraVote Plugin
# Copyright (C) 2022 www.conseilgouz.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
-------------------------------------------------------------------------*/

// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class plgAjaxExtraVoteInstallerScript
{
	function install($parent) {
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = ' . $db->quote(1))
			->where($db->quoteName('element') . ' = ' . $db->quote('extravote'));
		$db->setQuery($query);
		
		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			echo Text::_('PLG_AJAX_EXTRAVOTE_ENABLED_0');
			
			return;
		}
		
		echo Text::_('PLG_AJAX_EXTRAVOTE_ENABLED_1');
	}
	function update($parent) {
		echo Text::_('PLG_CONTENT_EXTRAVOTE_ENABLED_'.plgAjaxExtraVoteInstallerScript::isEnabled());
	}
	function isEnabled() {
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('enabled'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote('extravote'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('ajax'));
		$db->setQuery($query);
		
		return $db->loadResult();
	}
}