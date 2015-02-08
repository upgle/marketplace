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

	/**
	 * @brief return marketplace item list
	 * @param stdClass $args
	 *  
	 **/
	function getMarketplaceItemList($args) 
	{
		$output = executeQueryArray('marketplace.getMarketplaceItemList', $args);		
		return $this->_makeItemsListStatic($output);
	}

	/**
	 * @brief return marketplace item list
	 * @param itemObject $oDocument
	 *   document item object or marketplace item object
	 * @param stdClass $args
	 *  
	 **/
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
		return (int)(($count-1)/$args->list_count)+1;
	}

	/**
	 * @brief return member's wishlist
	 * @param int $member_srl
	 *   xe member module member_srl
	 *  
	 **/
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
		return $this->_makeItemsListStatic($output);
	}

	/**
	 * @brief make item globals
	 *  
	 **/
	function _makeItemsListStatic(&$output, $except_notice = false)
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

			$oDocument = new marketplaceItem();
			if(!marketplaceItem::$marketplace_list[$document_srl])
			{
				$oDocument->setAttribute($attribute, false);
				if($is_admin) $oDocument->setGrant();
			}

			$output->data[$virtual_number] = marketplaceItem::$marketplace_list[$document_srl];
			$virtual_number--;
		}

		if(count($output->data))
		{
			foreach($output->data as $number => $document)
			{
				$output->data[$number] = marketplaceItem::$marketplace_list[$document->document_srl];
			}
		}
		
		return $output;
	}

	/**
	 * @brief return each member's item status count
	 *   cancel, soldout, selling, total	
	 * @param int $member_srl
	 *   xe member module member_srl
	 **/
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

	/**
	 * @brief return all comments from seller's items
	 * @param stdClass $args
	 **/
	function getMarketplaceSellerItemComments($args)
	{
		$module_info = Context::get('module_info');
		if(!$args->module_srl) $args->module_srl = $module_info->module_srl;
		if(!$args->module_srl || !$args->member_srl) return new Object(-1, 'msg_invalid_request');

		return executeQueryArray('marketplace.getSellerItemComments', $args);
	}

	/**
	 * @brief return marketplace item object
	 * @param int $document_srl
	 * @param bool $is_admin
	 **/
	function getMarketplaceItem($document_srl=0, $is_admin = false)
	{
		if(!$document_srl) return new marketplaceItem();

		if(!marketplaceItem::$marketplace_list[$document_srl])
		{
			$oMarketItem = new marketplaceItem($document_srl, $load_extra_vars, $columnList);
			marketplaceItem::$marketplace_list[$document_srl] = $oMarketItem;
		}
		if($is_admin) marketplaceItem::$marketplace_list[$document_srl]->setGrant();

		return marketplaceItem::$marketplace_list[$document_srl];
	}

	/**
	 * @brief return marketplace item status
	 * @param int $document_srl
	 **/
	function getMarketplaceItemStatus($document_srl=0)
	{
		$output = $this->getMarketplaceItem($document_srl);
		return $output->getItemStatus();
	}

	/**
	 * @brief return advertise list
	 * @param stdClass $args
	 **/
	function getAdvertiseList($args) 
	{
		if(!$args->module_srl) return new Object(-1, 'msg_invalid_request');

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

	/**
	 * @brief return advertise info
	 * @param int $document_srl
	 **/
	function getAdvertise($document_srl)
	{
		$args = new stdClass();
		$args->document_srl = $document_srl;
		return executeQuery('marketplace.getAdvertiseByDocumentSrl', $args);
	}

	/**
	 * @brief return advertise info by bid price
	 * @param int $bid_price
	 **/
	function getAdvertiseByBidPrice($bid_price, $module_srl = false)
	{
		$args = new stdClass();
		$args->bid_price = $bid_price;
		$args->module_srl = ($module_srl) ? $module_srl : null;

		return executeQuery('marketplace.getAdvertiseByBidPrice', $args);
	}

	/**
	 * @brief return advertise left balance
	 * @param int $document_srl
	 **/
	function getAdvertiseBalance($document_srl)
	{
		$output = $this->getAdvertise($document_srl);
		return $output->data->balance;
	}

	/**
	 * @brief return advertise log list
	 * @param stdClass $args
	 **/
	function getAdvertiseLogList($args)
	{
		return executeQueryArray('marketplace.getAdvertiseLogList', $args);
	}

	/**
	 * @brief return last advertise log
	 * @param stdClass $args
	 **/
	function getAdvertiseLogLatestOne($args)
	{
		return executeQuery('marketplace.getAdvertiseLogLatestOne', $args);
	}

	/**
	 * @brief return bool is inserted advertise
	 * @param int $document_srl
	 **/
	function isInsertedAdvertise($document_srl)
	{
		$output = $this->getAdvertise($document_srl);
		if($output->data) return true;
			else return false;
	}

	/**
	 * @brief return inserted all keyword
	 * @param char $return_type
	 **/
	function getAllKeywords($module_srl)
	{
		if(!$module_srl) return new Object(-1, 'msg_invalid_request');

		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$object_key = 'keyword_list:' . $module_srl;
			$cache_key = $oCacheHandler->getGroupKey('marketplace', $object_key);			
			$keyword_list = $oCacheHandler->get($cache_key);
		}

		if(!$keyword_list)
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQuery('marketplace.getAllKeywords', $args);
			$keyword_list = $output->data;
			if($oCacheHandler->isSupport())
				$oCacheHandler->put($cache_key, $keyword_list);
		}
		
		// make return array
		$_keyword_list = array();
		foreach($keyword_list as $val)
		{
			$_keyword_list[] = $val->keyword;
		}
		return $_keyword_list;
	}

	/**
	 * @brief return inserted keywords by member_srl
	 * @param int $member_srl
	 **/
	function getKeywordsByMemberSrl($member_srl, $module_srl = false)
	{
		$args = new stdClass();
		$args->module_srl = ($module_srl) ? $module_srl : null;
		$args->member_srl = $member_srl;
		return executeQueryArray('marketplace.getKeywordsByMemberSrl', $args);	
	}

	/**
	 * @brief return inserted keyword by member_srl
	 * @param int $member_srl
	 **/
	function getKeywordByMemberSrl($keyword, $member_srl, $module_srl = false)
	{
		if(!$keyword) return new Object(-1, 'msg_invalid_request');

		$args = new stdClass();
		$args->module_srl = ($module_srl) ? $module_srl : null;
		$args->member_srl = $member_srl;
		$args->keyword = $keyword;

		return executeQueryArray('marketplace.getKeywordByMemberSrl', $args);	
	}

	/**
	 * @brief get Item list by keyword
	 **/
	function getItemListByKeywords($args)
	{
		$output = executeQueryArray('marketplace.getItemListByKeywords', $args);

		return $this->_makeItemsListStatic($output);
	}

	/**
	 * @brief get Member list by keyword
	 **/
	function getMemberListByKeyword($keyword, $module_srl = false)
	{
		$args = new stdClass();
		$args->keyword = $keyword;
		$args->module_srl = ($module_srl) ? $module_srl : null;
		return executeQueryArray('marketplace.getMemberListByKeyword', $args);
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
			$list_config = array( 'no', 'title', 'nick_name','regdate','readed_count', 'price', 'product_name', 'original_price', 'item_condition');
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


	/**
	 * @brief return district list from csv
	 **/
	function getDistrict()
	{
		// get Korea Disticts
		$district_file = FileHandler::readFile(_XE_PATH_ . 'modules/marketplace/districts.ko.csv');
		return explode("\n", $district_file); 
	}

	function getWishlistItem($args)
	{
		if(!$args->document_srl || !$args->member_srl) 
			return new Object(-1, 'msg_invalid_request');

		return executeQuery('marketplace.getWishlistItem', $args);
	}

	function getSettingCondition($module_srl, $eid)
	{
		$obj->eid = $eid;
		$obj->module_srl = $module_srl;
		return executeQuery('marketplace.getSettingCondition', $obj);
	}

	function getSettingConditions($module_srl)
	{
		$obj->module_srl = $module_srl;
		return executeQueryArray('marketplace.getSettingConditions', $obj);
	}

	/**
	 * @brief return categories
	 **/
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

