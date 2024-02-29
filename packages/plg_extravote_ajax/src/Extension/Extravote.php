<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Ajax Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
-------------------------------------------------------------------------*/
namespace ConseilGouz\Plugin\Ajax\Extravote\Extension;
// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\DatabaseAwareTrait;

class Extravote extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    public static function getSubscribedEvents(): array
    {
        return [
            'onAjaxExtravote'   => 'goAjax',
        ];
    }
	
	function goAjax($event)
	{
		$input	= Factory::getApplication()->input;
		$user = Factory::getApplication()->getIdentity();;
		$plugin	= PluginHelper::getPlugin('content', 'extravote');
		$params = new Registry;
		$params->loadString($plugin->params);

		if ( $params->get('access') == 2 && !$user->get('id') )	{
		    return $event->addResult('login');
		} 
		$user_rating = $input->getInt('user_rating');
		$xid         = $input->getInt('xid');
		$table       = (($params->get('table',1)!=1 && !(int)$xid)?'#__content_rating':'#__content_extravote');
		$cid = 0;
		if ( $params->get('article_id') || $xid == 0 ) {
				$cid = $input->getInt('cid');
		}
		$db  = $this->getDatabase();
		$query	= $db->getQuery(true);
		if ($user_rating < 0.5 || $user_rating > 5) {
			return;
		}
		$currip = $_SERVER['REMOTE_ADDR'];
		$query->select('*')
			->from($db->qn($table))
			->where('content_id = '.$db->quote($cid).($table == '#__content_extravote' ? ' AND extra_id = '.$db->quote($xid) : ''));
		$db->setQuery($query);
		try	{
			$votesdb = $db->loadObject();
		}
		catch (\RuntimeException $e)	{
		    return  $event->addResult('error');
		}
		$query	= $db->getQuery(true);
		if ( !$votesdb ) { // No vote for this article
			$columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
			$values = array($cid, $user_rating, 1, $db->quote($currip));
			if($table=='#__content_extravote') :
				$columns[] = 'extra_id';
				$values[] = $xid;
				// $columns[] = 'user_id';
				// $values[] = $user->id;
			endif;
			$query
				->insert($db->quoteName($table))
				->columns($db->quoteName($columns))
				->values(implode(',', $values));
			$db->setQuery($query);
			try	{
				$result = $db->execute();
			}
			catch (\RuntimeException $e) {
			    return $event->addResult('error');
			}
			if ( $params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
			    return $event->addResult($this->checkuservote($cid,$user->get('id'),$user_rating,$xid,$table,true));
			}
		} else { // vote exists in table
			if ( $params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
			    return $event->addResult($this->checkuservote($cid,$user->get('id'),$user_rating,$xid,$table,false));
			} elseif ($currip != ($votesdb->lastip)) {
				$query
					->update($db->quoteName($table))
					->set( 'rating_sum = rating_sum + ' . $user_rating)
					->set( 'rating_count = rating_count +'. 1)
					->set('lastip = '. $db->quote( $currip))
					->where('content_id = '.$cid.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
				$db->setQuery($query);
				try	{
					$result = $db->execute();
				}
				catch (\RuntimeException $e)		{
				    return $event->addResult('error');
				}
			} else { // last IP 
			    return $event->addResult('voted');
			}
		}
		$event->addResult('thanks');
	}
	/* Extravote : 1 vote per user/article
	*/
	protected function checkuservote($cid,$user_id,$user_rating,$xid,$table,$create) {
	    $db  = $this->getDatabase();
		$query	= $db->getQuery(true);
		$query->select('*')
			->from($db->qn('#__content_extravote_user'))
			->where('content_id = '.$db->quote($cid).' AND user_id = '.$db->quote($user_id).' AND extra_id = '.$db->quote($xid));
		$db->setQuery($query);
		try	{
			$voteuser = $db->loadObject();
		}
		catch (\RuntimeException $e)	{
			return   'error';
		}
		if (!$voteuser) { // No vote for this user/article
			$columns = array('content_id', 'rating', 'user_id', 'created');
			$values = array($cid, $user_rating, $user_id,  $db->quote(Factory::getDate()->toSql()));
			$columns[] = 'extra_id';
			$values[] = $xid;
			$query	= $db->getQuery(true);
			$query
				->insert($db->quoteName('#__content_extravote_user'))
				->columns($db->quoteName($columns))
				->values(implode(',', $values));
			$db->setQuery($query);
			try	{
				$result = $db->execute();
			}
			catch (\RuntimeException $e) {
				return 'error';
			}
			if (!$create) {// update vote count
				$currip = $_SERVER['REMOTE_ADDR'];
				$query	= $db->getQuery(true);
				$query
					->update($db->quoteName($table))
					->set( 'rating_sum = rating_sum + ' . $user_rating)
					->set( 'rating_count = rating_count +'. 1)
					->set('lastip = '. $db->quote( $currip))
					->where('content_id = '.$cid.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
				$db->setQuery($query);
				try	{
					$result = $db->execute();
				}	
				catch (\RuntimeException $e)		{
					return 'error';
				}
			}
		} else {
			return 'voted';
		}
		return 'thanks';		
	}
}
