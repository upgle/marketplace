<?php
/**
 * marketplaceItem class
 * marketItem object
 *
 * @author UPGLE (admin@upgle.com)
 * @package /modules/marketplace
 * @version 0.1
 */
class marketplaceItem extends Object
{
	/**
	 * Document number
	 * @var int
	 */
	var $document_srl = 0;
	/**
	 * Language code
	 * @var string
	 */
	var $lang_code = null;
	/**
	 * Status of allow trackback
	 * @var bool
	 */
	var $allow_trackback_status = null;
	/**
	 * column list
	 * @var array
	 */
	var $columnList = array();
	/**
	 * allow script access list
	 * @var array
	 */
	var $allowscriptaccessList = array();
	/**
	 * allow script access key
	 * @var int
	 */
	var $allowscriptaccessKey = 0;
	/**
	 * upload file list
	 * @var array
	 */
	var $uploadedFiles = array();

	/**
	 * module info
	 * @var stdClass
	 */
	var $module_info = null;

	/**
	 * marketplace list
	 * @static array
	 */
	public static $marketplace_list = array();

	/**
	 * Constructor
	 * @param int $document_srl
	 * @param bool $load_extra_vars
	 * @param array columnList
	 * @return void
	 */
	function marketplaceItem($document_srl = 0, $load_extra_vars = true, $columnList = array())
	{
		$this->document_srl = $document_srl;
		$this->columnList = $columnList;

		$this->_loadFromDB($load_extra_vars);

		$this->setModuleInfo($document_srl);
	}

	function setMarketplaceItem($document_srl, $load_extra_vars = true)
	{
		$this->document_srl = $document_srl;
		$this->_loadFromDB($load_extra_vars);
	}

	function setModuleInfo($document_srl)
	{
		$oModuleModel = getModel('module');
		$this->module_info = $oModuleModel->getModuleInfoByDocumentSrl($document_srl);
	}

	/**
	 * Get data from database, and set the value to marketplaceItem object
	 * @param bool $load_extra_vars
	 * @return void
	 */
	function _loadFromDB($load_extra_vars = true)
	{
		if(!$this->document_srl) return;

		$marketplace_item = false;
		$columnList = array();
		$this->columnList = array();

		// cache controll
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$cache_key = 'marketplace_item:' . getNumberingPath($this->document_srl) . $this->document_srl;
			$marketplace_item = $oCacheHandler->get($cache_key);
			if($marketplace_item !== false)
			{
				$columnList = array('readed_count', 'voted_count', 'blamed_count', 'comment_count', 'item_status');
			}
		}


		$args = new stdClass();
		$args->document_srl = $this->document_srl;
		$output = executeQuery('marketplace.getMarketplaceItem', $args, $columnList);

		// add document_srl if it is notice ( Solution MYSQL Left join Issue )
		if($output->data && $output->data->is_notice == 'Y') 
			$output->data->document_srl = $this->document_srl;

		if($marketplace_item === false)
		{
			$marketplace_item = $output->data;

			//insert in cache
			if($marketplace_item && $oCacheHandler->isSupport())
			{
				$oCacheHandler->put($cache_key, $marketplace_item);
			}
		}
		else
		{
			$marketplace_item->readed_count = $output->data->readed_count;
			$marketplace_item->voted_count = $output->data->voted_count;
			$marketplace_item->blamed_count = $output->data->blamed_count;
			$marketplace_item->comment_count = $output->data->comment_count;
			$marketplace_item->item_status = $output->data->item_status;
		}

		$this->setAttribute($marketplace_item, $load_extra_vars);
	}

	function setAttribute($attribute, $load_extra_vars=true)
	{
		if(!$attribute->document_srl)
		{
			$this->document_srl = null;
			return;
		}
		$this->document_srl = $attribute->document_srl;
		$this->lang_code = $attribute->lang_code;
		$this->adds($attribute);

		// Tags
		if($this->get('tags'))
		{
			$tag_list = explode(',', $this->get('tags'));
			$tag_list = array_map('trim', $tag_list);
			$this->add('tag_list', $tag_list);
		}

		$oDocumentModel = getModel('document');
		if($load_extra_vars)
		{
			self::$marketplace_list[$attribute->document_srl] = $this;
			$oDocumentModel->setToAllDocumentExtraVars();
		}
		self::$marketplace_list[$this->document_srl] = $this;
	}

	function isExists()
	{
		return $this->document_srl ? true : false;
	}

	function isGranted()
	{
		if($_SESSION['own_document'][$this->document_srl]) return true;

		if(!Context::get('is_logged')) return false;

		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;

		$oModuleModel = getModel('module');
		$grant = $oModuleModel->getGrant($oModuleModel->getModuleInfoByModuleSrl($this->get('module_srl')), $logged_info);
		if($grant->manager) return true;

		if($this->get('member_srl') && ($this->get('member_srl') == $logged_info->member_srl || $this->get('member_srl')*-1 == $logged_info->member_srl)) return true;

		return false;
	}
	function isWishitem()
	{
		if(!Context::get('is_logged')) return false;
		$logged_info = Context::get('logged_info');

		$oMarketplaceModel = getModel('marketplace');
		$args->document_srl = $this->document_srl;
		$args->member_srl = $logged_info->member_srl;
		$output = $oMarketplaceModel->getWishlistItem($args);
		if($output->data) return true;
		
		return false;
	}

	function isReinserted()
	{
		if(abs($this->get('list_order')) != abs($this->document_srl))
			return true;
		else return false;
	}

	function setGrant()
	{
		$_SESSION['own_document'][$this->document_srl] = true;
	}

	function isAccessible()
	{
		return $_SESSION['accessible'][$this->document_srl]==true?true:false;
	}

	function allowComment()
	{
		// init write, document is not exists. so allow comment status is true
		if(!$this->isExists()) return true;

		return $this->get('comment_status') == 'ALLOW' ? true : false;
	}

	function allowTrackback()
	{
		static $allow_trackback_status = null;
		if(is_null($allow_trackback_status))
		{
			
			// Check the tarckback module exist
			if(!getClass('trackback'))
			{
				$allow_trackback_status = false;
			}
			else
			{
				// If the trackback module is configured to be disabled, do not allow. Otherwise, check the setting of each module.
				$oModuleModel = getModel('module');
				$trackback_config = $oModuleModel->getModuleConfig('trackback');
				
				if(!$trackback_config)
				{
					$trackback_config = new stdClass();
				}
				
				if(!isset($trackback_config->enable_trackback)) $trackback_config->enable_trackback = 'Y';
				if($trackback_config->enable_trackback != 'Y') $allow_trackback_status = false;
				else
				{
					$module_srl = $this->get('module_srl');
					// Check settings of each module
					$module_config = $oModuleModel->getModulePartConfig('trackback', $module_srl);
					if($module_config->enable_trackback == 'N') $allow_trackback_status = false;
					else if($this->get('allow_trackback')=='Y' || !$this->isExists()) $allow_trackback_status = true;
				}
			}
		}
		return $allow_trackback_status;
	}

	function isLocked()
	{
		if(!$this->isExists()) return false;

		return $this->get('comment_status') == 'ALLOW' ? false : true;
	}

	function isEditable()
	{
		if($this->isGranted() || !$this->get('member_srl')) return true;
		return false;
	}

	function isSecret()
	{
		$oDocumentModel = getModel('document');
		return $this->get('status') == $oDocumentModel->getConfigStatus('secret') ? true : false;
	}

	function isAdvertise()
	{
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getAdvertise($this->document_srl);
		if($output->data) return true;
			else return false;
	}

	function isSelling()
	{
		if($this->get('item_status')=='selling') return true;
		else return false;
	}

	function isSoldout()
	{
		if($this->get('item_status')=='soldout') return true;
		else return false;
	}
	function isCancel()
	{
		if($this->get('item_status')=='cancel') return true;
		else return false;
	}

	function isNotice()
	{
		return $this->get('is_notice') == 'Y' ? true : false;
	}

	function useNotify()
	{
		return $this->get('notify_message')=='Y' ? true : false;
	}

	function doCart()
	{
		if(!$this->document_srl) return false;
		if($this->isCarted()) $this->removeCart();
		else $this->addCart();
	}

	function addCart()
	{
		$_SESSION['document_management'][$this->document_srl] = true;
	}

	function removeCart()
	{
		unset($_SESSION['document_management'][$this->document_srl]);
	}

	function isCarted()
	{
		return $_SESSION['document_management'][$this->document_srl];
	}

	/**
	 * Send notify message to document owner
	 * @param string $type
	 * @param string $content
	 * @return void
	 */
	function notify($type, $content)
	{
		if(!$this->document_srl) return;
		// return if it is not useNotify
		if(!$this->useNotify()) return;
		// Pass if an author is not a logged-in user
		if(!$this->get('member_srl')) return;
		// Return if the currently logged-in user is an author
		$logged_info = Context::get('logged_info');
		if($logged_info->member_srl == $this->get('member_srl')) return;
		// List variables
		if($type) $title = "[".$type."] ";
		$title .= cut_str(strip_tags($content), 10, '...');
		$content = sprintf('%s<br /><br />from : <a href="%s" target="_blank">%s</a>',$content, getFullUrl('','document_srl',$this->document_srl), getFullUrl('','document_srl',$this->document_srl));
		$receiver_srl = $this->get('member_srl');
		$sender_member_srl = $logged_info->member_srl;
		// Send a message
		$oCommunicationController = getController('communication');
		$oCommunicationController->sendMessage($sender_member_srl, $receiver_srl, $title, $content, false);
	}

	function getLangCode()
	{
		return $this->get('lang_code');
	}

	function getIpAddress()
	{
		if($this->isGranted())
		{
			return $this->get('ipaddress');
		}

		return '*' . strstr($this->get('ipaddress'), '.');
	}

	function isExistsHomepage()
	{
		if(trim($this->get('homepage'))) return true;
		return false;
	}

	function getHomepageUrl()
	{
		$url = trim($this->get('homepage'));
		if(!$url) return;

		if(strncasecmp('http://', $url, 7) !== 0 && strncasecmp('https://', $url, 8) !== 0)  $url = 'http://' . $url;

		return $url;
	}

	function getMemberSrl()
	{
		return $this->get('member_srl');
	}

	function getUserID()
	{
		return htmlspecialchars($this->get('user_id'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getUserName()
	{
		$user_name = htmlspecialchars($this->get('user_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		
		if($this->module_info->protect_username == 'Y')
		{
			$len = mb_strlen($user_name, 'UTF-8');
			return mb_substr($user_name, 0, 1, 'UTF-8').'*'.mb_substr($user_name, 2, $len, 'UTF-8');
		}
		return $user_name;
	}

	function getNickName()
	{
		return htmlspecialchars($this->get('nick_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getLastUpdater()
	{
		return htmlspecialchars($this->get('last_updater'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getTitleText($cut_size = 0, $tail='...')
	{
		if(!$this->document_srl) return;

		if($cut_size) $title = cut_str($this->get('title'), $cut_size, $tail);
		else $title = $this->get('title');

		return $title;
	}

	function getTitle($cut_size = 0, $tail='...')
	{
		if(!$this->document_srl) return;

		$title = $this->getTitleText($cut_size, $tail);

		$attrs = array();
		$this->add('title_color', trim($this->get('title_color')));
		if($this->get('title_bold')=='Y') $attrs[] = "font-weight:bold;";
		if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = "color:#".$this->get('title_color');

		if(count($attrs)) return sprintf("<span style=\"%s\">%s</span>", implode(';',$attrs), htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		else return htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}

	function getContentText($strlen = 0)
	{
		if(!$this->document_srl) return;

		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');

		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;

		$content = $this->get('content');
		$content = preg_replace_callback('/<(object|param|embed)[^>]*/is', array($this, '_checkAllowScriptAccess'), $content);
		$content = preg_replace_callback('/<object[^>]*>/is', array($this, '_addAllowScriptAccess'), $content);

		if($strlen) return cut_str(strip_tags($content),$strlen,'...');

		// 연락처 가림
		$this->hideContactNumber($content);

		return htmlspecialchars($content);
	}

	function _addAllowScriptAccess($m)
	{
		if($this->allowscriptaccessList[$this->allowscriptaccessKey] == 1)
		{
			$m[0] = $m[0].'<param name="allowscriptaccess" value="never"></param>';
		}
		$this->allowscriptaccessKey++;
		return $m[0];
	}

	function _checkAllowScriptAccess($m)
	{
		if($m[1] == 'object')
		{
			$this->allowscriptaccessList[] = 1;
		}

		if($m[1] == 'param')
		{
			if(stripos($m[0], 'allowscriptaccess'))
			{
				$m[0] = '<param name="allowscriptaccess" value="never"';
				if(substr($m[0], -1) == '/')
				{
					$m[0] .= '/';
				}
				$this->allowscriptaccessList[count($this->allowscriptaccessList)-1]--;
			}
		}
		else if($m[1] == 'embed')
		{
			if(stripos($m[0], 'allowscriptaccess'))
			{
				$m[0] = preg_replace('/always|samedomain/i', 'never', $m[0]);
			}
			else
			{
				$m[0] = preg_replace('/\<embed/i', '<embed allowscriptaccess="never"', $m[0]);
			}
		}
		return $m[0];
	}

	function getContent($add_popup_menu = true, $add_content_info = true, $resource_realpath = false, $add_xe_content_class = true, $stripEmbedTagException = false)
	{
		if(!$this->document_srl) return;

		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');

		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;

		$content = $this->get('content');
		if(!$stripEmbedTagException) stripEmbedTagForAdmin($content, $this->get('member_srl'));

		// Define a link if using a rewrite module
		$oContext = &Context::getInstance();
		if($oContext->allow_rewrite)
		{
			$content = preg_replace('/<a([ \t]+)href=("|\')\.\/\?/i',"<a href=\\2". Context::getRequestUri() ."?", $content);
		}
		// To display a pop-up menu
		if($add_popup_menu)
		{
			$content = sprintf(
				'%s<div class="document_popup_menu"><a href="#popup_menu_area" class="document_%d" onclick="return false">%s</a></div>',
				$content,
				$this->document_srl, Context::getLang('cmd_document_do')
			);
		}
		// If additional content information is set
		if($add_content_info)
		{
			$memberSrl = $this->get('member_srl');
			if($memberSrl < 0)
			{
				$memberSrl = 0;
			}
			$content = sprintf(
				'<!--BeforeDocument(%d,%d)--><div class="document_%d_%d xe_content">%s</div><!--AfterDocument(%d,%d)-->',
				$this->document_srl, $memberSrl,
				$this->document_srl, $memberSrl,
				$content,
				$this->document_srl, $memberSrl,
				$this->document_srl, $memberSrl
			);
			// Add xe_content class although accessing content is not required
		}
		else
		{
			if($add_xe_content_class) $content = sprintf('<div class="xe_content">%s</div>', $content);
		}
		// Change the image path to a valid absolute path if resource_realpath is true
		if($resource_realpath)
		{
			$content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $content);
		}

		// 연락처 가림
		$this->hideContactNumber($content);
		return $content;
	}

	/**
	 * Return transformed content by Editor codes
	 * @param bool $add_popup_menu
	 * @param bool $add_content_info
	 * @param bool $resource_realpath
	 * @param bool $add_xe_content_class
	 * @return string
	 */
	function getTransContent($add_popup_menu = true, $add_content_info = true, $resource_realpath = false, $add_xe_content_class = true)
	{
		$oEditorController = getController('editor');

		$content = $this->getContent($add_popup_menu, $add_content_info, $resource_realpath, $add_xe_content_class);
		$content = $oEditorController->transComponent($content);

		return $content;
	}

	function getSummary($str_size = 50, $tail = '...')
	{
		$content = $this->getContent(FALSE, FALSE);

		// For a newlink, inert a whitespace
		$content = preg_replace('!(<br[\s]*/{0,1}>[\s]*)+!is', ' ', $content);

		// Replace tags such as </p> , </div> , </li> and others to a whitespace
		$content = str_replace(array('</p>', '</div>', '</li>', '-->'), ' ', $content);

		// Remove Tags
		$content = preg_replace('!<([^>]*?)>!is', '', $content);

		// Replace < , >, "
		$content = str_replace(array('&lt;', '&gt;', '&quot;', '&nbsp;'), array('<', '>', '"', ' '), $content);

		// Delete  a series of whitespaces
		$content = preg_replace('/ ( +)/is', ' ', $content);

		// Truncate string
		$content = trim(cut_str($content, $str_size, $tail));

		// Replace back < , <, "
		$content = str_replace(array('<', '>', '"'),array('&lt;', '&gt;', '&quot;'), $content);

		return $content;
	}

	function getRegdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('regdate'), $format);
	}

	function getRegdateTime()
	{
		$regdate = $this->get('regdate');
		$year = substr($regdate,0,4);
		$month = substr($regdate,4,2);
		$day = substr($regdate,6,2);
		$hour = substr($regdate,8,2);
		$min = substr($regdate,10,2);
		$sec = substr($regdate,12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}


	function getTimeGap()
	{
		$regdate = $this->get('regdate');
		$gap = $_SERVER['REQUEST_TIME'] + zgap() - ztime($regdate);

		$lang_time_gap = Context::getLang('time_gap');

		if ($gap < 30)
		{
			$buff = $lang_time_gap['just_now'];
		}		
		elseif($gap < 60)
		{
			$buff = sprintf($lang_time_gap['second'], (int) $gap);
		}
		elseif($gap < 60 * 2)
		{
			$buff = sprintf($lang_time_gap['min'], (int) ($gap / 60) + 1);
		}
		elseif($gap < 60 * 60)
		{
			$buff = sprintf($lang_time_gap['mins'], (int) ($gap / 60) + 1);
		}
		elseif($gap < 60 * 60 * 2)
		{
			$buff = sprintf($lang_time_gap['hour'], (int) ($gap / 60 / 60) + 1);
		}
		elseif($gap < 60 * 60 * 24)
		{
			$buff = sprintf($lang_time_gap['hours'], (int) ($gap / 60 / 60) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 2)
		{
			$buff = sprintf($lang_time_gap['day'], (int) ($gap / 60 / 60 / 24 ) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 7)
		{
			$buff = sprintf($lang_time_gap['days'], (int) ($gap / 60 / 60 / 24) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 7 * 2)
		{
			$buff = sprintf($lang_time_gap['week'], (int) ($gap / 60 / 60 / 24 / 7) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 30)
		{
			$buff = sprintf($lang_time_gap['weeks'], (int) ($gap / 60 / 60 / 24 / 7 ) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 30 * 2)
		{
			$buff = sprintf($lang_time_gap['month'], (int) ($gap / 60 / 60 / 24 / 30) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 365)
		{
			$buff = sprintf($lang_time_gap['months'], (int) ($gap / 60 / 60 / 24 / 30) + 1);
		}
		elseif($gap < 60 * 60 * 24 * 365 * 2)
		{
			$buff = sprintf($lang_time_gap['year'], (int) ($gap / 60 / 60 / 24 / 365) + 1);
		}
		else
		{
			$buff = sprintf($lang_time_gap['years'], (int) ($gap / 60 / 60 / 24 / 7 / 4 / 12) + 1);
		}

		return $buff;
	}

	function getRegdateGM()
	{
		return $this->getRegdate('D, d M Y H:i:s').' '.$GLOBALS['_time_zone'];
	}

	function getRegdateDT()
	{
		return $this->getRegdate('Y-m-d').'T'.$this->getRegdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}

	function getUpdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('last_update'), $format);
	}

	function getUpdateTime()
	{
		$year = substr($this->get('last_update'),0,4);
		$month = substr($this->get('last_update'),4,2);
		$day = substr($this->get('last_update'),6,2);
		$hour = substr($this->get('last_update'),8,2);
		$min = substr($this->get('last_update'),10,2);
		$sec = substr($this->get('last_update'),12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}

	function getUpdateGM()
	{
		return gmdate("D, d M Y H:i:s", $this->getUpdateTime());
	}

	function getUpdateDT()
	{
		return $this->getUpdate('Y-m-d').'T'.$this->getUpdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}

	function getPermanentUrl()
	{
		return getFullUrl('','document_srl',$this->get('document_srl'));
	}

	/**
	 * Update readed count
	 * @return void
	 */
	function updateReadedCount()
	{
		$oDocumentController = getController('document');
		if($oDocumentController->updateReadedCount($this))
		{
			$readed_count = $this->get('readed_count');
			$this->add('readed_count', $readed_count+1);
		}
	}

	function isExtraVarsExists()
	{
		if(!$this->get('module_srl')) return false;
		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->get('module_srl'));
		return count($extra_keys)?true:false;
	}

	function getExtraVars()
	{
		if(!$this->get('module_srl') || !$this->document_srl) return null;

		$oDocumentModel = getModel('document');
		return $oDocumentModel->getExtraVars($this->get('module_srl'), $this->document_srl);
	}

	function getExtraValue($idx)
	{
		$extra_vars = $this->getExtraVars();
		return $extra_vars[$idx]->value;
	}

	function getExtraValueHTML($idx)
	{
		$extra_vars = $this->getExtraVars();
		if(is_array($extra_vars) && array_key_exists($idx,$extra_vars))
		{
			return $extra_vars[$idx]->getValueHTML();
		}
		else
		{
			return '';
		}
	}

	function getExtraEidValue($eid)
	{
		$extra_vars = $this->getExtraVars();

		if($extra_vars)
		{
			// Handle extra variable(eid)
			foreach($extra_vars as $idx => $key)
			{
				$extra_eid[$key->eid] = $key;
			}
		}
		return $extra_eid[$eid]->value;
	}

	function getExtraEidValueHTML($eid)
	{
		$extra_vars = $this->getExtraVars();
		// Handle extra variable(eid)
		foreach($extra_vars as $idx => $key)
		{
			$extra_eid[$key->eid] = $key;
		}
		return $extra_eid[$eid]->getValueHTML();
	}

	function getExtraVarsValue($key)
	{
		$extra_vals = unserialize($this->get('extra_vars'));
		$val = $extra_vals->$key;
		return $val;
	}

	function getCommentCount()
	{
		return $this->get('comment_count');
	}

	function getComments()
	{
		if(!$this->getCommentCount()) return;
		if(!$this->isGranted() && $this->isSecret()) return;
		// cpage is a number of comment pages
		$cpageStr = sprintf('%d_cpage', $this->document_srl);
		$cpage = Context::get($cpageStr);

		if(!$cpage)
		{
			$cpage = Context::get('cpage');
		}

		// Get a list of comments
		$oCommentModel = getModel('comment');
		$output = $oCommentModel->getCommentList($this->document_srl, $cpage, $is_admin);
		if(!$output->toBool() || !count($output->data)) return;
		// Create commentItem object from a comment list
		// If admin priviledge is granted on parent posts, you can read its child posts.
		$accessible = array();
		$comment_list = array();
		foreach($output->data as $key => $val)
		{
			// 내용에서 연락처 가림
			$this->hideContactNumber($val->content);

			$oCommentItem = new commentItem();
			$oCommentItem->setAttribute($val);
			// If permission is granted to the post, you can access it temporarily
			if($oCommentItem->isGranted()) $accessible[$val->comment_srl] = true;
			// If the comment is set to private and it belongs child post, it is allowable to read the comment for who has a admin privilege on its parent post
			if($val->parent_srl>0 && $val->is_secret == 'Y' && !$oCommentItem->isAccessible() && $accessible[$val->parent_srl]===true)
			{
				$oCommentItem->setAccessible();
			}
			$comment_list[$val->comment_srl] = $oCommentItem;
		}
		// Variable setting to be displayed on the skin
		Context::set($cpageStr, $output->page_navigation->cur_page);
		Context::set('cpage', $output->page_navigation->cur_page);
		if($output->total_page>1) $this->comment_page_navigation = $output->page_navigation;

		return $comment_list;
	}

	function thumbnailExists($width = 80, $height = 0, $type = '')
	{
		if(!$this->document_srl) return false;
		if(!$this->getThumbnail($width, $height, $type)) return false;
		return true;
	}

	function getThumbnail($width = 80, $height = 0, $thumbnail_type = '')
	{
		// Return false if the document doesn't exist
		if(!$this->get('thumbnails_srl')) return;
		// If not specify its height, create a square
		if(!$height) $height = $width;
		// Get thumbnai_type information from document module's configuration
		if(!in_array($thumbnail_type, array('crop','ratio')))
		{
			$config = $GLOBALS['__document_config__'];
			if(!$config)
			{
				$oDocumentModel = getModel('document');
				$config = $oDocumentModel->getDocumentConfig();
				$GLOBALS['__document_config__'] = $config;
			}
			$thumbnail_type = $config->thumbnail_type;
		}
		// Define thumbnail information
		$thumbnail_path = sprintf('files/marketplace/thumbnails/%s',getNumberingPath($this->get('thumbnails_srl'), 3));
		$thumbnail_file = sprintf('%s%dx%d.%s.jpg', $thumbnail_path, $width, $height, $thumbnail_type);
		$thumbnail_url  = Context::getRequestUri().$thumbnail_file;
		// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
		if(file_exists($thumbnail_file))
		{
			if(filesize($thumbnail_file)<1) return false;
			else return $thumbnail_url;
		}
		// Target File
		$source_file = null;
		// Find an iamge file among attached files if exists
		$oFileModel = getModel('file');
		$file_list = $oFileModel->getFiles($this->get('thumbnails_srl'), array(), 'file_srl', true);

		if(count($file_list))
		{
			foreach($file_list as $file)
			{
				if($file->direct_download!='Y') continue;
				if(!preg_match("/\.(jpg|png|jpeg|gif|bmp)$/i",$file->source_filename)) continue;

				$source_file = $file->uploaded_filename;
				if(!file_exists($source_file)) $source_file = null;
				else break;
			}
		}

		if($source_file)
		{
			$output = FileHandler::createImageFile($source_file, $thumbnail_file, $width, $height, 'jpg', $thumbnail_type);
		}
		// Return its path if a thumbnail is successfully genetated
		if($output) return $thumbnail_url;
		// Create an empty file not to re-generate the thumbnail
		else FileHandler::writeFile($thumbnail_file, '','w');

		return;
	}

	function getThumbnails($width = 80, $height = 0, $thumbnail_type = '')
	{
		// Return false if the document doesn't exist
		if(!$this->get('thumbnails_srl')) return;
		// If not specify its height, create a square
		if(!$height) $height = $width;
		// Get thumbnai_type information from document module's configuration
		if(!in_array($thumbnail_type, array('crop','ratio')))
		{
			$config = $GLOBALS['__document_config__'];
			if(!$config)
			{
				$oDocumentModel = getModel('document');
				$config = $oDocumentModel->getDocumentConfig();
				$GLOBALS['__document_config__'] = $config;
			}
			$thumbnail_type = $config->thumbnail_type;
		}
		// Find an iamge file among attached files if exists
		$oFileModel = getModel('file');
		$file_list = $oFileModel->getFiles($this->get('thumbnails_srl'), array(), 'file_srl', true);

		if(count($file_list))
		{
			$thumbnails = array();
			foreach($file_list as $file)
			{
				// Target File
				$source_file = null;
		
				// Define thumbnail information
				$thumbnail_path = sprintf('files/marketplace/thumbnails/%s%s',getNumberingPath($this->get('thumbnails_srl'), 3),getNumberingPath($file->file_srl, 3));
			
				$thumbnail_file = sprintf('%s%dx%d.%s.jpg', $thumbnail_path, $width, $height, $thumbnail_type);
				$thumbnail_url  = Context::getRequestUri().$thumbnail_file;
				// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
				if(file_exists($thumbnail_file))
				{
					if(filesize($thumbnail_file)<1) return false;
					else {
						$thumbnails[] = $thumbnail_url;
						continue;
					}
				}
				if($file->direct_download!='Y') continue;
				if(!preg_match("/\.(jpg|png|jpeg|gif|bmp)$/i",$file->source_filename)) continue;

				$source_file = $file->uploaded_filename;
				if(!file_exists($source_file)) $source_file = false;

				if($source_file)
				{
					$output = FileHandler::createImageFile($source_file, $thumbnail_file, $width, $height, 'jpg', $thumbnail_type);
				}
				// Return its path if a thumbnail is successfully genetated
				if($output) $thumbnails[] = $thumbnail_url;
				// Create an empty file not to re-generate the thumbnail
				else FileHandler::writeFile($thumbnail_file, '','w');
			}
		}
		if(count($thumbnails)) return $thumbnails;
	}

	function getThumbnailsObject($width = 80, $height = 0, $thumbnail_type = '')
	{
		// Return false if the document doesn't exist
		if(!$this->get('thumbnails_srl')) return;
		// If not specify its height, create a square
		if(!$height) $height = $width;
		// Get thumbnai_type information from document module's configuration
		if(!in_array($thumbnail_type, array('crop','ratio')))
		{
			$config = $GLOBALS['__document_config__'];
			if(!$config)
			{
				$oDocumentModel = getModel('document');
				$config = $oDocumentModel->getDocumentConfig();
				$GLOBALS['__document_config__'] = $config;
			}
			$thumbnail_type = $config->thumbnail_type;
		}
		// Find an iamge file among attached files if exists
		$oFileModel = getModel('file');
		$file_list = $oFileModel->getFiles($this->get('thumbnails_srl'), array(), 'file_srl', true);
		
		if(count($file_list))
		{
			$thumbnails = array();
			foreach($file_list as $file)
			{
				// Target File
				$source_file = null;
		
				// Define thumbnail information
				$thumbnail_path = sprintf('files/marketplace/thumbnails/%s%s',getNumberingPath($this->get('thumbnails_srl'), 3),getNumberingPath($file->file_srl, 3));
			
				$thumbnail_file = sprintf('%s%dx%d.%s.jpg', $thumbnail_path, $width, $height, $thumbnail_type);
				$thumbnail_url  = Context::getRequestUri().$thumbnail_file;
				// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
				if(file_exists($thumbnail_file))
				{
					if(filesize($thumbnail_file)<1) return false;
					else {
						$file->thumbnail_url = $thumbnail_url;
						$thumbnails[] = $file;
						continue;
					}
				}
				if($file->direct_download!='Y') continue;
				if(!preg_match("/\.(jpg|png|jpeg|gif|bmp)$/i",$file->source_filename)) continue;

				$source_file = $file->uploaded_filename;
				if(!file_exists($source_file)) $source_file = false;

				if($source_file)
				{
					$output = FileHandler::createImageFile($source_file, $thumbnail_file, $width, $height, 'jpg', $thumbnail_type);
				}
				// Return its path if a thumbnail is successfully genetated
				if($output) {
					$file->thumbnail_url = $thumbnail_url;
					$thumbnails[] = $file;
				}
				// Create an empty file not to re-generate the thumbnail
				else FileHandler::writeFile($thumbnail_file, '','w');
			}
		}
		if(count($thumbnails)) return $thumbnails;
	}

	/**
	 * Functions to display icons for new post, latest update, secret(private) post, image/video/attachment
	 * Determine new post and latest update by $time_interval
	 * @param int $time_interval
	 * @return array
	 */
	function getExtraImages($time_interval = 43200)
	{
		if(!$this->document_srl) return;
		// variables for icon list
		$buffs = array();

		$check_files = false;

		// Check if secret post is
		if($this->isSecret()) $buffs[] = "secret";

		// Set the latest time
		$time_check = date("YmdHis", $_SERVER['REQUEST_TIME']-$time_interval);

		// Check new post
		if($this->get('regdate')>$time_check) $buffs[] = "new";
		else if($this->get('last_update')>$time_check) $buffs[] = "update";

		/*
		   $content = $this->get('content');

		// Check image files
		preg_match_all('!<img([^>]*?)>!is', $content, $matches);
		$cnt = count($matches[0]);
		for($i=0;$i<$cnt;$i++) {
		if(preg_match('/editor_component=/',$matches[0][$i])&&!preg_match('/image_(gallery|link)/i',$matches[0][$i])) continue;
		$buffs[] = "image";
		$check_files = true;
		break;
		}

		// Check video files
		if(preg_match('!<embed([^>]*?)>!is', $content) || preg_match('/editor_component=("|\')*multimedia_link/i', $content) ) {
		$buffs[] = "movie";
		$check_files = true;
		}
		 */

		// Check the attachment
		if($this->hasUploadedFiles()) $buffs[] = "file";

		return $buffs;
	}

	function getStatus()
	{
		if(!$this->get('status')) return $this->getDefaultStatus();
		return $this->get('status');
	}

	function getItemStatus()
	{
		return $this->get('item_status');
	}

	function getPrice($number_format = false)
	{
		if($number_format) return number_format($this->get('price'));
		return $this->get('price');
	}

	function getItemCondition()
	{
		return $this->get('item_condition');
	}

	function getAdvertiseBalance()
	{
		$oMarketplaceModel = getModel('marketplace');
		$balance = $oMarketplaceModel->getAdvertiseBalance($this->document_srl);
		return $balance;
	}

	/**
	 * Return the value obtained from getExtraImages with image tag
	 * @param int $time_check
	 * @return string
	 */
	function printExtraImages($time_check = 43200)
	{
		if(!$this->document_srl) return;
		// Get the icon directory
		$path = sprintf('%s%s',getUrl(), 'modules/document/tpl/icons/');

		$buffs = $this->getExtraImages($time_check);
		if(!count($buffs)) return;

		$buff = array();
		foreach($buffs as $key => $val)
		{
			$buff[] = sprintf('<img src="%s%s.gif" alt="%s" title="%s" style="margin-right:2px;" />', $path, $val, $val, $val);
		}
		return implode('', $buff);
	}

	function hasUploadedFiles()
	{
		if(!$this->document_srl) return;

		if($this->isSecret() && !$this->isGranted()) return false;
		return $this->get('uploaded_count')? true : false;
	}

	function getUploadedFiles($sortIndex = 'file_srl')
	{
		if(!$this->document_srl) return;

		if($this->isSecret() && !$this->isGranted()) return;
		if(!$this->get('uploaded_count')) return;

		if(!$this->uploadedFiles[$sortIndex])
		{
			$oFileModel = getModel('file');
			$this->uploadedFiles[$sortIndex] = $oFileModel->getFiles($this->document_srl, array(), $sortIndex, true);
		}

		return $this->uploadedFiles[$sortIndex];
	}

	/**
	 * Return Editor html
	 * @return string
	 */
	function getEditor()
	{
		$module_srl = $this->get('module_srl');
		if(!$module_srl) $module_srl = Context::get('module_srl');

		$oEditorModel = getModel('editor');
		return $oEditorModel->getModuleEditor('document', $module_srl, $this->document_srl, 'document_srl', 'content');
	}

	/**
	 * Check whether to have a permission to write comment
	 * Authority to write a comment and to write a document is separated
	 * @return bool
	 */
	function isEnableComment()
	{
		// Return false if not authorized, if a secret document, if the document is set not to allow any comment
		if(!$this->allowComment()) return false;
		if(!$this->isGranted() && $this->isSecret()) return false;
		if($this->isSoldout() && $this->module_info->allow_comment_soldout !== 'Y') return false;

		return true;
	}

	/**
	 * Return comment editor's html
	 * @return string
	 */
	function getCommentEditor()
	{
		if(!$this->isEnableComment()) return;

		$oEditorModel = getModel('editor');
		return $oEditorModel->getModuleEditor('comment', $this->get('module_srl'), $comment_srl, 'comment_srl', 'content');
	}

	/**
	 * Return author's profile image
	 * @return string
	 */
	function getProfileImage()
	{
		if(!$this->isExists() || !$this->get('member_srl')) return;
		$oMemberModel = getModel('member');
		$profile_info = $oMemberModel->getProfileImage($this->get('member_srl'));
		if(!$profile_info) return;

		return $profile_info->src;
	}

	/**
	 * Return author's signiture
	 * @return string
	 */
	function getSignature()
	{
		// Pass if a document doesn't exist
		if(!$this->isExists() || !$this->get('member_srl')) return;
		// Get signature information
		$oMemberModel = getModel('member');
		$signature = $oMemberModel->getSignature($this->get('member_srl'));
		// Check if a maximum height of signiture is set in the member module
		if(!isset($GLOBALS['__member_signature_max_height']))
		{
			$oModuleModel = getModel('module');
			$member_config = $oModuleModel->getModuleConfig('member');
			$GLOBALS['__member_signature_max_height'] = $member_config->signature_max_height;
		}
		if($signature)
		{
			$max_signature_height = $GLOBALS['__member_signature_max_height'];
			if($max_signature_height) $signature = sprintf('<div style="max-height:%dpx;overflow:auto;overflow-x:hidden;height:expression(this.scrollHeight > %d ? \'%dpx\': \'auto\')">%s</div>', $max_signature_height, $max_signature_height, $max_signature_height, $signature);
		}

		return $signature;
	}

	/**
	 * Change an image path in the content to absolute path
	 * @param array $matches
	 * @return mixed
	 */
	function replaceResourceRealPath($matches)
	{
		return preg_replace('/src=(["\']?)files/i','src=$1'.Context::getRequestUri().'files', $matches[0]);
	}

	/**
	 * Check accessible by document status
	 * @param array $matches
	 * @return mixed
	 */
	function _checkAccessibleFromStatus()
	{
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;

		$status = $this->get('status');
		if(empty($status)) return false;

		$oDocumentModel = getModel('document');
		$configStatusList = $oDocumentModel->getStatusList();

		if($status == $configStatusList['public'] || $status == $configStatusList['publish'])
			return true;
		else if($status == $configStatusList['private'] || $status == $configStatusList['secret'])
		{
			if($this->get('member_srl') == $logged_info->member_srl)
				return true;
		}
		return false;
	}

	function getTranslationLangCodes()
	{
		$obj = new stdClass;
		$obj->document_srl = $this->document_srl;
		// -2 is an index for content. We are interested if content has other translations.
		$obj->var_idx = -2;
		$output = executeQueryArray('document.getDocumentTranslationLangCodes', $obj);

		if (!$output->data)
		{
			$output->data = array();
		}
		// add original page's lang code as well
		$origLangCode = new stdClass;
		$origLangCode->lang_code = $this->getLangCode();
		$output->data[] = $origLangCode;

		return $output->data;
	}


	/**
	 * Returns the document's mid in order to construct SEO friendly URLs
	 * @return string
	 */
	function getDocumentMid()
	{
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->mid;
	}

	/**
	 * Returns the document's type (document/page/wiki/board/etc)
	 * @return string
	 */
	function getDocumentType()
	{
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->module;
	}

	/**
	 * Returns the document's alias
	 * @return string
	 */
	function getDocumentAlias()
	{
		$oDocumentModel = getModel('document');
		return $oDocumentModel->getAlias($this->document_srl);
	}

	/**
	 * Returns the document's actual title (browser_title)
	 * @return string
	 */
	function getModuleName()
	{
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->browser_title;
	}

	function getBrowserTitle()
	{
		return $this->getModuleName();
	}

	function hideContactNumber(&$content)
	{
		if($this->module_info->protect_contact_type == 'star')
		{
			$replace = "***-****-****";
		}
		else $replace = "<strong style='
		color:red'>[연락처 숨김]</strong>";
		
		if(isCrawler() || ($this->module_info->protect_contact == 'Y' && $this->isSoldout())) {
			$content = eregi_replace("([0-9]{3})([\-])([0-9]{3,4})([\-])([0-9]{4})", $replace, $content); 
		}
	}
}
/* End of file document.item.php */
/* Location: ./modules/document/document.item.php */
