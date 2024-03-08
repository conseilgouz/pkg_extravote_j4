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

    public function goAjax($event)
    {
        $input	= Factory::getApplication()->input;
        $user = Factory::getApplication()->getIdentity();
        $action = $input->getString('action');
        if ($action == 'sync') {
            $event->addResult($this->goSync());
            return;
        }

        $plugin	= PluginHelper::getPlugin('content', 'extravote');
        $params = new Registry();
        $params->loadString($plugin->params);

        if ($params->get('access') == 2 && !$user->get('id')) {
            return $event->addResult('login');
        }
        $user_rating = $input->getInt('user_rating');
        $xid         = $input->getInt('xid');
        $table       = (($params->get('table', 1) != 1 && !(int)$xid) ? '#__content_rating' : '#__content_extravote');
        $cid = 0;
        if ($params->get('article_id') || $xid == 0) {
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
        try {
            $votesdb = $db->loadObject();
        } catch (\RuntimeException $e) {
            return  $event->addResult('error');
        }
        $query	= $db->getQuery(true);
        if (!$votesdb) { // No vote for this article
            $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
            $values = array($cid, $user_rating, 1, $db->quote($currip));
            if($table == '#__content_extravote') :
                $columns[] = 'extra_id';
                $values[] = $xid;
            endif;
            $query
                ->insert($db->quoteName($table))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return $event->addResult('error');
            }
            if ($params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
                return $event->addResult($this->checkuservote($cid, $user->get('id'), $user_rating, $xid, $table, true));
            }
        } else { // vote exists in table
            if ($params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
                return $event->addResult($this->checkuservote($cid, $user->get('id'), $user_rating, $xid, $table, false));
            } elseif ($currip != ($votesdb->lastip)) {
                $query
                    ->update($db->quoteName($table))
                    ->set('rating_sum = rating_sum + ' . $user_rating)
                    ->set('rating_count = rating_count +'. 1)
                    ->set('lastip = '. $db->quote($currip))
                    ->where('content_id = '.$cid.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return $event->addResult('error');
                }
            } else { // last IP
                return $event->addResult('voted');
            }
        }
        if ($params->get('sync', 0) &&  ($table == '#__content_extravote')) {// synchronize Vote and ExtraVote table
            return $event->addResult($this->sync_vote($cid, $xid, $currip));
        }
        $event->addResult('thanks');
    }
    /* Extravote : 1 vote per user/article
    */
    protected function checkuservote($cid, $user_id, $user_rating, $xid, $table, $create)
    {
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->qn('#__content_extravote_user'))
            ->where('content_id = '.$db->quote($cid).' AND user_id = '.$db->quote($user_id).' AND extra_id = '.$db->quote($xid));
        $db->setQuery($query);
        try {
            $voteuser = $db->loadObject();
        } catch (\RuntimeException $e) {
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
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
            if (!$create) {// update vote count
                $currip = $_SERVER['REMOTE_ADDR'];
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName($table))
                    ->set('rating_sum = rating_sum + ' . $user_rating)
                    ->set('rating_count = rating_count +'. 1)
                    ->set('lastip = '. $db->quote($currip))
                    ->where('content_id = '.$cid.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
            }
        } else {
            return 'voted';
        }
        return 'thanks';
    }
    /*
    Synchronize Vote table with ExtraVote infos
    */
    protected function sync_vote($cid, $xid, $currip)
    {
        // get extra_vote infos
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->qn('#__content_extravote'))
            ->where('content_id = '.$db->quote($cid).' AND extra_id = '.$db->quote($xid));
        $db->setQuery($query);
        try {
            $extravote = $db->loadObject();
        } catch (\RuntimeException $e) {
            return 'error';
        }
        // store extravoteinfo in vote table
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->qn('#__content_rating'))
            ->where('content_id = '.$db->quote($cid));
        $db->setQuery($query);
        try {
            $vote = $db->loadObject();
        } catch (\RuntimeException $e) {
            return  'error';
        }
        $query	= $db->getQuery(true);
        if (!$vote) { // No vote for this article
            $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
            $values = array($cid, $extravote->rating_sum, $extravote->rating_count, $db->quote($currip));
            $query
                ->insert($db->quoteName('#__content_rating'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
        } else { // vote exists in table
            $query
                ->update($db->quoteName('#__content_rating'))
                ->set('rating_sum = ' . $extravote->rating_sum)
                ->set('rating_count = ' . $extravote->rating_count)
                ->set('lastip = '. $db->quote($currip))
                ->where('content_id = '.$cid);
            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
        }
        return	'thanks';
    }
    protected function goSync()
    {
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $q2  	= $db->getQuery(true);
        // in extravote and not in rating
        $q2->select('rating.content_id,rating.rating_sum,rating.rating_count,rating.lastip,extra.content_id as extraid, extra.rating_sum as extrasum ,extra.rating_count as extracount,extra.lastip as extralastip')
            ->from($db->qn('#__content_rating').' as rating')
            ->join('RIGHT', $db->qn('#__content_extravote').' as extra on rating.content_id = extra.content_id');
        // in rating but not in extravote
        $query->select('rating.content_id,rating.rating_sum,rating.rating_count,rating.lastip,extra.content_id as extraid,extra.rating_sum as extrasum ,extra.rating_count as extracount,extra.lastip as extralastip')
            ->from($db->qn('#__content_rating').' as rating')
            ->join('LEFT', $db->qn('#__content_extravote').' as extra on rating.content_id = extra.content_id')
            ->union($q2);
        $db->setQuery($query);
        try {
            $tosync = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return 'error';
        }
        if (!sizeof($tosync)) {
            return 'empty';
        }
        foreach ($tosync as $one) {
            if (!$one->rating_sum) {// does not exist in extravote : create it
                $query	= $db->getQuery(true);
                $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
                $values = array($one->extraid, $one->extrasum, $one->extracount, $db->quote($one->lastid));
                $query
                    ->insert($db->quoteName('#__content_rating'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if (!$one->extrasum) {// does not exist in extravote : create it
                $query	= $db->getQuery(true);
                $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip','extra_id');
                $values = array($one->content_id, $one->rating_sum, $one->rating_count, $db->quote($one->lastid),0);
                $query
                    ->insert($db->quoteName('#__content_extravote'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if ($one->rating_sum > $one->extrasum) {
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName('#__content_extravote'))
                    ->set('rating_sum = ' . $one->rating_sum)
                    ->set('rating_count = ' . $one->rating_count)
                    ->set('lastip = '. $db->quote($one->lastip))
                    ->where('content_id = '.$one->content_id.' AND extra_id = 0');
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if ($one->rating_sum < $one->extrasum) {
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName('#__content_rating'))
                    ->set('rating_sum = ' . $one->extrasum)
                    ->set('rating_count = ' . $one->extracount)
                    ->set('lastip = '. $db->quote($one->extralastip))
                    ->where('content_id = '.$one->content_id);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
            }
        }
        return 'ok';
    }
}
