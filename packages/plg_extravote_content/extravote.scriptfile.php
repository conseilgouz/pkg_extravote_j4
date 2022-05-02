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
class plgContentExtraVoteInstallerScript
{
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
}