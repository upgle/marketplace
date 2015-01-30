<?php
/**
 * @class  marketplaceModel
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module  Model class
 **/
class marketplaceModel extends module
{
	/**
	 * @brief initialization
	 **/
	function init()
	{
	}

	function getMarketplaceItemList($args) 
	{
		$output = executeQueryArray('marketplace.getMarketplaceItemList', $args);		
		return $this->_makeMarketplaceItemsGlobals($output);
	}

	function getMarketplaceItemPage($oDocument, $args)
	{
		$sort_check->sort_index = $args->sort_index;
		if($sort_check->sort_index === 'list_order' || $sort_check->sort_index === 'update_order')
		{
			if($args->order_type === 'desc')
			{
				$args->{'rev_' . $sort_check->sort_index} = $oDocument->get($sort_check->sort_index);
			}
			else
			{
				$args->{$sort_check->sort_index} = $oDocument->get($sort_check->sort_index);
			}
		}
		else
		{
			return 1;
		}

		$output = executeQuery('marketplace.getMarketplaceItemListPage', $args);		
		$count = $output->data->count;
		$page = (int)(($count-1)/$args->list_count)+1;

		return $page;
	}

	function getMarketplaceWishList($member_srl = false)
	{
		$logged_info = Context::get('logged_info');		
		if(!$member_srl) $member_srl = $logged_info->member_srl;
		if(!$member_srl) return new Object(-1, 'msg_invalid_request');

		$module_info = Context::get('module_info');
		$args->module_srl = $module_info->module_srl;
		$args->member_srl = $member_srl;
		$output = executeQueryArray('marketplace.getWishlist', $args);
		if(!$output->toBool()) return $output;
		return $this->_makeMarketplaceItemsGlobals($output);
	}

	function _makeMarketplaceItemsGlobals(&$output, $except_notice = false)
	{
		$idx = 0;
		$data = $output->data;
		unset($output->data);

		if(!isset($virtual_number))
		{
			$keys = array_keys($data);
			$virtual_number = $keys[0];
		}

		if($except_notice)
		{
			foreach($data as $key => $attribute)
			{
				if($attribute->is_notice == 'Y') $virtual_number --;
			}
		}

		foreach($data as $key => $attribute)
		{
			if($except_notice && $attribute->is_notice == 'Y') continue;
			$document_srl = $attribute->document_srl;
			if(!$GLOBALS['XE_MARKETPLACE_LIST'][$document_srl])
			{
				$oDocument = null;
				$oDocument = new marketplaceItem();
				$oDocument->setAttribute($attribute, false);
				if($is_admin) $oDocument->setGrant();
				$GLOBALS['XE_MARKETPLACE_LIST'][$document_srl] = $oDocument;
			}

			$output->data[$virtual_number] = $GLOBALS['XE_MARKETPLACE_LIST'][$document_srl];
			$virtual_number--;
		}
		if(count($output->data))
		{
			foreach($output->data as $number => $document)
			{
				$output->data[$number] = $GLOBALS['XE_MARKETPLACE_LIST'][$document->document_srl];
			}
		}

		return $output;
	}


	function getMarketplaceItemStatusCount($member_srl) 
	{
		$module_info = Context::get('module_info');
		$status_list = array(
			'cancel' => 'cancel',
			'soldout' => 'soldout',
			'selling' => 'selling',
			'total' => ''
		);
		foreach($status_list as $key => $status) {
			$args = new stdClass();
			$args->module_srl = $module_info->module_srl;
			$args->member_srl = $member_srl;
			$args->item_status = $status;

			$output = executeQuery('marketplace.getMarketplaceItemCountByStatus', $args);
			$result->{$key} = $output->data->count;
		}
		return $result;
	}


	function getMarketplaceSellerItemComments($args)
	{
		$logged_info = Context::get('logged_info');
		$oDocumentModel = getModel('document');
		$output = $oDocumentModel->getDocumentListByMemberSrl($logged_info->member_srl, array('document_srl'));

		foreach($output as $key => $val)
		{
			$args->document_srls[] = $val->document_srl;
		}
		$output = executeQuery('marketplace.getCommentsByDocumentSrl', $args);

		return $output;
	}

	function getMarketplaceItem($document_srl=0, $is_admin = false)
	{
		if(!$document_srl) return new marketplaceItem();

		if(!$GLOBALS['XE_MARKETPLACE_LIST'][$document_srl])
		{
			$oMarketItem = new marketplaceItem($document_srl, $load_extra_vars, $columnList);
			$GLOBALS['XE_MARKETPLACE_LIST'][$document_srl] = $oMarketItem;
			if($load_extra_vars) $this->setToAllDocumentExtraVars();
		}
		if($is_admin) $GLOBALS['XE_MARKETPLACE_LIST'][$document_srl]->setGrant();

		return $GLOBALS['XE_MARKETPLACE_LIST'][$document_srl];
	}

	function getMarketplaceItemStatus($document_srl=0)
	{
		$output = $this->getMarketplaceItem($document_srl);
		return $output->getItemStatus();
	}


	/* 
	 * 프리미엄 광고 관련 함수
	 * Advertise Functions
	 *
	*/

	function getAdvertiseList($args) 
	{
		$module_info = Context::get('module_info');
		$args->module_srl = $module_info->module_srl;

		$output = executeQueryArray('marketplace.getAdvertiseList', $args);

		foreach($output->data as $key => $attribute)
		{
			$oMarketItem = null;
			$oMarketItem = new marketplaceItem();
			$oMarketItem->setAttribute($attribute, false);
			$output->data[$key] = $oMarketItem;
		}


		return $output;
	}

	function getAdvertise($document_srl)
	{
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$output = executeQuery('marketplace.getAdvertiseByDocumentSrl', $args);

		return $output;
	}

	function getAdvertiseByBidPrice($bid_price)
	{
		$args = new stdClass();
		$args->bid_price = $bid_price;
		$output = executeQuery('marketplace.getAdvertiseByBidPrice', $args);

		return $output;
	}

	function getAdvertiseBalance($document_srl)
	{
		$output = $this->getAdvertise($document_srl);
		return $output->data->balance;
	}


	function getAdvertiseLogList($args)
	{
		$output = executeQueryArray('marketplace.getAdvertiseLogList', $args);

		return $output;
	}

	function getAdvertiseLogLatestOne($args)
	{
		$output = executeQuery('marketplace.getAdvertiseLogLatestOne', $args);

		return $output;
	}

	function isInsertedAdvertise($document_srl)
	{
		$output = $this->getAdvertise($document_srl);
		if($output->data) return true;
			else return false;
	}


	/* 
	 * 키워드 관련 함수
	 * Keyword Functions
	 *
	*/
	function getAllKeywords($return_type = 'stdClass')
	{
		$module_info = Context::get('module_info');
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$object_key = 'keyword_list:' . $module_info->module_srl;
			$cache_key = $oCacheHandler->getGroupKey('marketplace', $object_key);			
			$keyword_list = $oCacheHandler->get($cache_key);
		}

		if(!$keyword_list)
		{
			$args = new stdClass();
			$args->module_srl = $module_info->module_srl;
			$output = executeQuery('marketplace.getAllKeywords', $args);
			$keyword_list = $output->data;
			if($oCacheHandler->isSupport())
				$oCacheHandler->put($cache_key, $keyword_list);
		}
		if($return_type == 'array') 
		{
			$_output = array();
			foreach($keyword_list as $val)
			{
				$_output[] = $val->keyword;
			}
			$keyword_list = $_output;
		}

		return $keyword_list;
	}

	function getKeywordsByMemberSrl($member_srl)
	{
		$module_info = Context::get('module_info');

		$args = new stdClass();
		$args->module_srl = $module_info->module_srl;
		$args->member_srl = $member_srl;
		$output = executeQueryArray('marketplace.getKeywordsByMemberSrl', $args);	
		return $output;
	}

	function getItemListByKeywords($args)
	{
		$output = executeQueryArray('marketplace.getItemListByKeywords', $args);

		return $this->_makeMarketplaceItemsGlobals($output);
	}

	function getMemberListByKeyword($keyword)
	{
		$args = new stdClass();
		$args->keyword = $keyword;
		$output = executeQueryArray('marketplace.getMemberListByKeyword', $args);
		return $output;
	}


	/**
	 * @brief get the list configuration
	 **/
	function getListConfig($module_srl)
	{
		$oModuleModel = getModel('module');
		$oDocumentModel = getModel('document');

		// get the list config value, if it is not exitsted then setup the default value
		$list_config = $oModuleModel->getModulePartConfig('marketplace', $module_srl);
		if(!$list_config || count($list_config) <= 0)
		{
			$list_config = array( 'no', 'title', 'nick_name','regdate','readed_count');
		}

		// get the extra variables
		$inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

		foreach($list_config as $key)
		{
			if(preg_match('/^([0-9]+)$/',$key))
			{
				if($inserted_extra_vars[$key])
				{
					$output['extra_vars'.$key] = $inserted_extra_vars[$key];
				}
				else
				{
					continue;
				}
			}
			else
			{
				$output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
			}
		}
		return $output;
	}


	/**
	 * @brief return the default list configration value
	 **/
	function getDefaultListConfig($module_srl)
	{
		// add virtual srl, title, registered date, update date, nickname, ID, name, readed count, voted count etc.
		$virtual_vars = array( 'no', 'title', 'price', 'product_name', 'original_price', 'item_condition', 'used_month', 'priority_area', 'regdate', 'last_update', 'last_post', 'nick_name',
				'user_id', 'user_name', 'readed_count', 'voted_count', 'blamed_count', 'thumbnail', 'summary', 'comment_status');
		foreach($virtual_vars as $key)
		{
			$extra_vars[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
		}

		// get the extra variables from the document model
		$oDocumentModel = getModel('document');
		$inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

		if(count($inserted_extra_vars))
		{
			foreach($inserted_extra_vars as $obj)
			{
				$extra_vars['extra_vars'.$obj->idx] = $obj;
			}
		}

		return $extra_vars;

	}


	/**
	 * @brief return sellers information
	 **/
	function getSellerInfo($member_srl) 
	{
		$oMemberModel = getModel('member');
		$seller_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);

		$seller_info->secured_user_name = $seller_info->user_name;

		$oDocumentModel = getModel('document');
		$seller_info->count_document = $oDocumentModel->getDocumentCountByMemberSrl($member_srl);

		$oCommentModel = getModel('comment');
		$seller_info->count_comment = $oCommentModel->getCommentCountByMemberSrl($member_srl);

		$status_count = $this->getMarketplaceItemStatusCount($member_srl);
		$seller_info->count_selling = $status_count->selling;
		$seller_info->count_soldout = $status_count->soldout;

		return $seller_info;
	}

	/**
	 * @brief return seller's contact number
	 **/
	function getMarketplaceContactNumber()
	{
		$oDocumentModel = getModel('document');
		$document_srl = Context::get('document_srl');
		$output = $oDocumentModel->getDocument($document_srl, false, false);
		if(!$document_srl) return false;

		$member_srl = $output->getMemberSrl();
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		if(!$member_info) return false;

		$contact_number = implode('-',$member_info->{$this->module_info->contact_number_field});

		$this->add('mobile',$contact_number);
	}

	function getDistrict()
	{
		// get Korea Disticts
		$district_file = FileHandler::readFile(_XE_PATH_ . 'modules/marketplace/districts.ko.csv');
		$output = explode("\n", $district_file); 
		return $output;
	}

	function getWishlistItem($args)
	{
		if(!$args->document_srl || !$args->member_srl) 
			return new Object(-1, 'msg_invalid_request');

		$output = executeQuery('marketplace.getWishlistItem', $args);
		return $output;
	}


	function getSettingCondition($module_srl, $eid)
	{
		$obj->eid = $eid;
		$obj->module_srl = $module_srl;
		$output = executeQuery('marketplace.getSettingCondition', $obj);
		return $output;
	}

	function getSettingConditions($module_srl)
	{
		$obj->module_srl = $module_srl;
		$output = executeQueryArray('marketplace.getSettingConditions', $obj);
		return $output;
	}


	function ajaxGetCategory()
	{
		$category_srl = $_GET['category_srl'];
		$oDocumentModel = getModel('document');

		$module_info = Context::get('module_info');
		$categories = $oDocumentModel->getCategoryList($module_info->module_srl);
	
		$category = $categories[$category_srl];

		if($category->child_count)
		{
			foreach($category->childs as $child)
			{
				$child_categories[$categories[$child]->category_srl] = $categories[$child]->text;
			}
			echo json_encode( $child_categories );
		}
		else echo '{}';
		exit();
	}

	/**
	 * @brief return module name in sitemap
	 **/
	function triggerModuleListInSitemap(&$obj)
	{
		array_push($obj, 'marketplace');
	}
}

