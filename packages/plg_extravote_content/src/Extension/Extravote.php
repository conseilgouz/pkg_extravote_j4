<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
# @license - https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
-------------------------------------------------------------------------*/
namespace ConseilGouz\Plugin\Content\Extravote\Extension;
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\DatabaseAwareTrait;

class Extravote extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    
	protected $article_id;
	protected $article_title;
	protected $view;
    public $myname='Extravote';
    protected $autoloadLanguage = true;
    
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentBeforeDisplay'   => 'checkExtra',
        ];
    }
    public function checkExtra($event)
    {
		if (strpos($event->getContext(), 'com_content') !== false) {
		    $input	= Factory::getApplication()->input;
		    $this->view = $input->getCmd('view');
		    $this->article_id = $event->getItem()->id;
		    $this->article_title = $event->getItem()->title;
			
		    $this->ExtraVotePrepare($event->getItem(), $event->getParams());	
			
			if ( $this->params->get('display') == 0  )
			{
				$hide  = $this->params->get('hide', 1);
				
				if ( $hide != 1 || $this->view == 'article' ) {
				    $event->getItem()->xid = 0;
				    $event->addResult($this->ContentExtraVote($event->getItem(),$event->getParams()));
				}
			}
		}
 	}
	protected function ContentExtraVote(&$article, &$params)
	{ 	
		$table =($this->params->get('table',1)==1?'#__content_extravote':'#__content_rating');
		$rating_count=$rating_sum=0;
		$html=$ip='';

		if ($params->get('show_vote')) {
		    $db	= $this->getDatabase();
			$query='SELECT * FROM ' . $table . ' WHERE content_id='.$this->article_id . ($table == '#__content_extravote' ? ' AND extra_id = 0' : '');
			$db->setQuery($query);
			$vote=$db->loadObject();
			if($vote) {
				$rating_sum = $vote->rating_sum;
				$rating_count = intval($vote->rating_count);
				$ip = $vote->lastip;
			}
			$html .= $this->plgContentExtraVoteStars( $this->article_id, $rating_sum, $rating_count, $article->xid, $ip );
		}
		return $html;
 	}
 	protected function plgContentExtraVoteStars( $id, $rating_sum, $rating_count, $xid, $ip )
	{
		$plg	= 'media/plg_content_extravote/';
			/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	 	if ( $this->params->get('css', 1) ) :
			$wa->registerAndUseStyle('extravotecontent', $plg.'extravote.css');
		endif;
		$wa->registerAndUseScript('extravotecontent', $plg.'extravote.js');

     	global $plgContentExtraVoteAddScript;
		
		$show_counter = $this->params->get('show_counter',1);
		$show_rating  = $this->params->get('show_rating',1);
		$rating_mode  = $this->params->get('rating_mode', 1);
		$show_unrated = $this->params->get('show_unrated',1);
		$initial_hide = $this->params->get('initial_hide',0);
		$currip = $_SERVER['REMOTE_ADDR'];
		$add_snippets = 0;
		$rating  = 0;
		
	 	if(!$plgContentExtraVoteAddScript){ 
         	$wa->addInlineScript("
				var ev_basefolder = '".URI::base(true)."';
				var extravote_text=Array('".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_NO_AJAX')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOADING')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_THANKS')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOGIN')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_RATED')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE')."','".
					TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_RATING').
				"');
			");
     		$plgContentExtraVoteAddScript = 1;
	 	}
		
		if( $rating_count!=0 ) {
			$rating  = ($rating_sum / intval( $rating_count ));
			$add_snippets = $this->params->get('snippets',0);
		} elseif( $show_unrated == 0 ) {
			$show_counter = -1;
			$show_rating  = -1;
		}
		
		$container = 'div';
		$class     = 'size-'.$this->params->get('size', 1);
		if( (int)$xid ) { 
			if ( $show_counter == 2 ) $show_counter = 0;
			if ( $show_rating == 2 ) $show_rating = 0;
			$container = 'span';
			$class    .= ' extravote-small';  
			$add_snippets = 0;
		} else {
			if ( $show_counter == 3 ) $show_counter = 0;
			if ( $show_rating == 3 ) $show_rating = 0;
			$class    .= ' extravote';  
		}
		$stars = (($this->params->get('table',1)!=1 && !(int)$xid)?5:$this->params->get('stars',10));
		$spans = '';
		for ($i=0,$j=5/$stars; $i<$stars; $i++,$j+=5/$stars) :
			$spans .= "
      <span class=\"extravote-star\"><a href=\"javascript:void(null)\" onclick=\"javascript:JVXVote(".$id.",".$j.",".$rating_sum.",".$rating_count.",'".$xid."',".$show_counter.",".$show_rating.",".$rating_mode.");\" title=\"".TEXT::_('PLG_CONTENT_EXTRAVOTE_RATING_'.($j*10).'_OUT_OF_5')."\" class=\"ev-".($j*10)."-stars\">1</a></span>";
		endfor;
		
	 	$html = "
<".$container." class=\"".$class."\">
  <span class=\"extravote-stars\"".">"."<span id=\"rating_".$id."_".$xid."\" class=\"current-rating\"".((!$initial_hide||$currip==$ip)?" style=\"width:".round($rating*20)."%;\"":"")."".($add_snippets?" itemprop=\"ratingValue\"":"").">".($add_snippets?$rating:"")."</span>"
	.$spans."
  </span>
  <span class=\"extravote-info".(($initial_hide&&$currip!=$ip)?" ihide\"":"")."\" id=\"extravote_".$id."_".$xid."\">";
  
  		if ( $show_rating > 0 ) {
			if ( $rating_mode == 0 ) {
				$rating = round($rating*20) . '%';	
			} else {
				$rating = number_format($rating,2);	
			}
			$html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_RATING', $rating);
		}
  		if ( $show_counter > 0 ) {
			if($rating_count!=1) {
				$html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES', $rating_count);
			} else { 
				$html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE', $rating_count);
			}
		}
 	 	$html .="</span>";
 	 	$html .="
</".$container.">";
 	 	if ($add_snippets) {
 	 	    $html .= "<div class=\"visually-hidden\" itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\">";
			$html .= "<div itemprop=\"itemReviewed\" itemscope itemtype=\"https://schema.org/WebApplication\">";
			$html .= "<span itemprop=\"name\">".$this->article_title."</span>";
			$html .= "<span itemprop=\"operatingSystem\">Joomla</span>";
			$html .= "<div itemprop=\"offers\" itemscope itemtype=\"https://schema.org/Offer\"><span itemprop=\"price\">0</span></div>";
 	 	    $html .= "</div>";
 	 	    $html .= "<meta itemprop=\"ratingCount\" content=\"".$rating_count."\" />";
 	 	    $html .= "<meta itemprop=\"ratingCount\" content=\"".$rating_count."\" />";
 	 	    $html .= "<meta itemprop=\"ratingValue\" content=\"".$rating."\" />";
 	 	    $html .= "<meta itemprop=\"bestRating\" content=\"5\" />";
 	 	    $html .= "<meta itemprop=\"worstRating\" content=\"1\" />";
 	 	    $html .= "</div>";
 	 	}
	 	return $html;
 	}
 	protected function ExtraVotePrepare( &$article, &$params ) 
	{
	    if (isset($this->article_id)) {
	        $extra = $this->params->get('extra', 1);
			$main  = $this->params->get('main', 1);
 	 	    if ( $extra != 0 ) {
   	 		    $regex = "/{extravote\s*([0-9]+)}/i";
				if ( $this->view != 'article' && isset($article->introtext) && stripos($article->introtext, 'extravote') ) {
					if ( $extra == 2 ) {
						$article->introtext = preg_replace( $regex, '', $article->introtext );	
					} else {
						$article->introtext = preg_replace_callback( $regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext );	
					}
				} elseif (stripos($article->text, 'extravote')) {
//				    $this->article_id = $article->id;
   	 			    $article->text = preg_replace_callback( $regex, array($this,'plgContentExtraVoteReplacer'), $article->text );
			    }
		    }
 	 	    if ( $main != 0 ) {
				$strposIntro = isset($article->introtext)?stripos($article->introtext, 'mainvote'):false;
				$strposText  = stripos($article->text, 'mainvote');
				$regex = "/{mainvote\s*([0-9]*)}/i";
				if ( $main == 2 && $this->view != 'article' && $strposIntro) {
   	 			    $article->introtext = preg_replace( $regex, '', $article->introtext );
			    } else {
				    $this->article_id = $article->id;
					if ( $this->view == 'article' && $strposText ) {
   	 			    	$article->text = preg_replace_callback( $regex, array($this,'plgContentExtraVoteReplacer'), $article->text );
					} elseif( $strposIntro ) {
   	 			    	$article->introtext = preg_replace_callback( $regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext );
					}
			    }
		    }
		    if ( $this->params->get('display') == 1 )  {
		        $article->xid = 0;
				if ( $this->view == 'article' ) {
			        $article->text .= $this->ContentExtraVote($article, $params);
				} elseif ( $this->params->get('hide') == 0 ) {
			        $article->introtext .= $this->ContentExtraVote($article, $params);
				}
		    }
 	    }
 	    return $article;
 	}
 
	protected function plgContentExtraVoteReplacer(&$matches ) 
	{
	    $db	 = $this->getDatabase();
  		$cid = 0;
		$xid = 0;
		if (isset($matches[1])) {
			if(stripos($matches[0], 'extravote')) {
				$xid = (int)$matches[1];	
			} else {
				$cid = (int)$matches[1];
			}
		}
		if ( $cid == 0 && ($this->params->get('article_id') || $xid == 0) ) {
			$cid = $this->article_id;
		}
  		$rating_sum = 0;
  		$rating_count = 0;
		if ( $xid == 0 ) :
			global $extravote_mainvote;
			$extravote_mainvote .= 'x';
			$xid = $extravote_mainvote;
			$table =($this->params->get('table',1)==1?'#__content_extravote':'#__content_rating');
			$db->setQuery('SELECT * FROM ' . $table . ' WHERE content_id='.(int)$cid);
		else :  		
			$db->setQuery('SELECT * FROM #__content_extravote WHERE content_id='.(int)$cid.' AND extra_id='.(int)$xid);
		endif;  		
  		$vote = $db->loadObject();
  		if($vote) {
	 		if($vote->rating_count!=0)
				$rating_sum = $vote->rating_sum;
				$rating_count = intval($vote->rating_count);
	 	}
		return $this->plgContentExtraVoteStars( $cid, $rating_sum, $rating_count, $xid, ($vote?$vote->lastip:'') );
	}
	
}
