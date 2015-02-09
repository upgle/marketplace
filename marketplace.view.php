<?php
/**
 * @class  marketplaceView
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module View class
 **/
class marketplaceView extends marketplace
{
	var $listConfig;
	var $columnList;
	var $condition_list;

	/**
	 * @brief 초기화 initialization
	 * marketplace module can be used in either normal mode or admin mode.
	 **/
	function init()
	{

		// 초기 모듈 설정 체크
		if(!$this->module_info->module_initialize) {
			header('Location: '.getNotEncodedUrl('act','dispMarketplaceAdminMarketplaceInfo'));
			Context::close();
			exit;
		}

		$oSecurity = new Security();
		$oSecurity->encodeHTML('document_srl', 'comment_srl', 'vid', 'mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type', 'trackback_srl', 'price_from', 'price_to', 'priority_area', 'item_condition.', 'used_month', 'item_status', 'list_count');

		/**
		 * setup the module general information
		 **/
		if($this->module_info->list_count)
		{
			$this->list_count = $this->module_info->list_count;
		}
		if($this->module_info->search_list_count)
		{
			$this->search_list_count = $this->module_info->search_list_count;
		}
		if($this->module_info->page_count)
		{
			$this->page_count = $this->module_info->page_count;
		}
		$this->except_notice = $this->module_info->except_notice == 'N' ? FALSE : TRUE;

		// $this->_getStatusNameListecret option backward compatibility
		$oDocumentModel = getModel('document');

		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(isset($statusList['SECRET']))
		{
			$this->module_info->secret = 'Y';
		}

		// hide category
		$count_category = count($oDocumentModel->getCategoryList($this->module_info->module_srl));
		if($count_category > 0) $this->module_info->hide_category = 'N';
		else $this->module_info->hide_category = 'Y';

		/**
		 * setup the template path based on the skin
		 * the default skin is default
		 **/
		$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		if(!is_dir($template_path)||!$this->module_info->skin)
		{
			$this->module_info->skin = 'default';
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);

		/**
		 * use context::set to setup extra variables
		 **/
		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->module_srl);
		Context::set('extra_keys', $extra_keys);

		/**
		 * add extra variables to order(sorting) target
		 **/
		if (is_array($extra_keys))
		{
			foreach($extra_keys as $val)
			{
				$this->order_target[] = $val->eid;
			}
		}
		/**
		 * load javascript, JS filters
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
		Context::addJsFile($this->module_path.'tpl/js/marketplace.js');

		// remove [document_srl]_cpage from get_vars
		$args = Context::getRequestVars();
		foreach($args as $name => $value)
		{
			if(preg_match('/[0-9]+_cpage/', $name))
			{
				Context::set($name, '', TRUE);
				Context::set($name, $value);
			}
		}

		// 제품 구분이 얻어옴
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getSettingConditions($this->module_srl);
		if(!$output->toBool())	return $output;
		foreach($output->data as $key => $val)
		{
			$condition_list[$val->eid] = $val;
		}
		$this->condition_list = $condition_list;
		Context::set('condition_list', $condition_list);

	}

	/**
	 * @brief display marketplace contents
	 **/
	function dispMarketplaceContent()
	{

		/**
		 * check the access grant (all the grant has been set by the module object)
		 **/
		if(!$this->grant->access || !$this->grant->list)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		/**
		 * display the category list, and then setup the category list on context
		 **/
		$this->dispMarketplaceCategoryList();

		/**
		 * display the search options on the screen
		 * add extra vaiables to the search options
		 **/
		// use search options on the template (the search options key has been declared, based on the language selected)
		foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
		$extra_keys = Context::get('extra_keys');
		if($extra_keys)
		{
			foreach($extra_keys as $key => $val)
			{
				if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
			}
		}
		Context::set('search_option', $search_option);

		$oDocumentModel = getModel('document');
		$statusNameList = $this->_getStatusNameList($oDocumentModel);
		if(count($statusNameList) > 0)
		{
			Context::set('status_list', $statusNameList);
		}

		// display the marketplace content
		$this->dispMarketplaceContentView();

		// list config, columnList setting
		$oMarketplaceModel = getModel('marketplace');
		$this->listConfig = $oMarketplaceModel->getListConfig($this->module_info->module_srl);
		if(!$this->listConfig) $this->listConfig = array();
		$this->_makeListColumnList();

		// 모듈 광고 설정 체크 후 광고 출력
		if($this->module_info->use_advertise)
		{
			$this->dispMarketplaceAdvertiseList();
		}

		// display the notice list
		$this->dispMarketplaceNoticeList();

		// list
		$this->dispMarketplaceContentList();

		// setup the tmeplate file
		$this->setTemplateFile('list');
	}

	/**
	 * @brief display the category list
	 **/
	function dispMarketplaceCategoryList(){
		// check if the hide_category option is enabled
		if($this->module_info->hide_category=='N')
		{
			$oDocumentModel = getModel('document');
			Context::set('category_list', $oDocumentModel->getCategoryList($this->module_srl));
		
			$oSecurity = new Security();
			$oSecurity->encodeHTML('category_list.', 'category_list.childs.');
		}
	}

	/**
	 * @brief display the marketplace conent view
	 **/
	function dispMarketplaceContentView(){

		// get the variable value
		$document_srl = Context::get('document_srl');
		$page = Context::get('page');

		// generate document model object
		$oDocumentModel = getModel('document');
		$oMarketplaceModel = getModel('marketplace');

		/**
		 * if the document exists, then get the document information
		 **/
		if($document_srl)
		{
			$oMarketItem = $oMarketplaceModel->getMarketplaceItem($document_srl);
				
			// if the document is existed
			if($oMarketItem->isExists())
			{
				// if the module srl is not consistent
				if($oMarketItem->get('module_srl')!=$this->module_info->module_srl )
				{
					return $this->stop('msg_invalid_request');
				}

				// check the manage grant
				if($this->grant->manager) $oMarketItem->setGrant();
			}
			else
			{
				// if the document is not existed, then alert a warning message
				Context::set('document_srl','',true);
				$this->alertMessage('msg_not_founded');
			}

		/**
		 * if the document is not existed, get an empty document
		 **/
		}
		else
		{
			$oMarketItem = $oMarketplaceModel->getMarketplaceItem(0);
		}

		/**
		 *check the document view grant
		 **/
		if($oMarketItem->isExists())
		{
			$oMarketplaceModel = getModel('marketplace');

			if(!$this->grant->view && !$oMarketItem->isGranted())
			{
				$oMarketItem = $oMarketplaceModel->getMarketplaceItem(0);
				Context::set('document_srl','',true);
				$this->alertMessage('msg_not_permitted');
			}
			else
			{
				// add the document title to the browser
				Context::addBrowserTitle($oMarketItem->getTitleText());

				// update the document view count
				$oMarketItem->updateReadedCount();
			}
		}
		
		// set seller information
		$seller_info = $oMarketplaceModel->getSellerInfo($oMarketItem->getMemberSrl());
		Context::set('seller_info', $seller_info);

		// setup the document oject on context
		$oMarketItem->add('module_srl', $this->module_srl);
		Context::set('oDocument', $oMarketItem);
		Context::set('oMarketItem', $oMarketItem);
	}

	/**
	 * @brief  display the document file list (can be used by API)
	 **/
	function dispMarketplaceContentFileList(){
		$oDocumentModel = getModel('document');
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		Context::set('file_list',$oDocument->getUploadedFiles());

		$oSecurity = new Security();
		$oSecurity->encodeHTML('file_list..source_filename');
	}

	/**
	 * @brief display the document comment list (can be used by API)
	 **/
	function dispMarketplaceContentCommentList(){
		$oDocumentModel = getModel('document');
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		$comment_list = $oDocument->getComments();

		// setup the comment list
		if(is_array($comment_list))
		{
			foreach($comment_list as $key => $val)
			{
				if(!$val->isAccessible())
				{
					$val->add('content',Context::getLang('thisissecret'));
				}
			}
		}
		Context::set('comment_list',$comment_list);
	}

	/**
	 * @brief display notice list (can be used by API)
	 **/
	function dispMarketplaceNoticeList(){
		$oDocumentModel = getModel('document');
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$notice_output = $oDocumentModel->getNoticeList($args, $this->columnList);
		Context::set('notice_list', $notice_output->data);
	}

	/**
	 * @brief display marketplace content list
	 **/
	function dispMarketplaceContentList(){

		// check the grant
		if(!$this->grant->list)
		{
			Context::set('marketitem_list', array());
			Context::set('total_count', 0);
			Context::set('total_page', 1);
			Context::set('page', 1);
			Context::set('page_navigation', new PageHandler(0,0,1,10));
			return;
		}

		$oDocumentModel = getModel('document');
		$oMarketplaceModel = getModel('marketplace');

		// setup module_srl/page number/ list number/ page count
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = (Context::get('list_count')) ? Context::get('list_count') : $this->list_count;
		$args->page_count = $this->page_count;

		// get the search target and keyword
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');
		$args->item_condition = Context::get('item_condition');
		if(is_numeric(Context::get('used_month')))
			$args->used_month = Context::get('used_month');
		$args->priority_area = trim(Context::get('priority_area'));
		if(is_numeric(Context::get('price_from')))
			$args->price_from = Context::get('price_from');
		if(is_numeric(Context::get('price_to')))
			$args->price_to = Context::get('price_to');

		if(Context::get('item_status') == 'selling')
			$args->item_status = 'selling';

		$args->not_item_status = 'cancel';
		

		// if the category is enabled, then get the category
		if($this->module_info->hide_category=='N' && Context::get('category'))
		{
			$category_srl = Context::get('category');
			$category_list = $oDocumentModel->getCategoryList($this->module_srl);			
			if($category_srl && $category_list[$category_srl]->child_count)
			{
				foreach($category_list[$category_srl]->childs as $val)
				{
					$args->category_srl[] = $val;
				}
			}
			$args->category_srl[] = $category_srl;
		}

		// setup the sort index and order index
		$args->sort_index = Context::get('sort_index');
		$args->order_type = Context::get('order_type');

		if(!in_array($args->sort_index, $this->order_target))
		{
			$args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
		}
		if(!in_array($args->order_type, array('asc','desc')))
		{
			$args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';
		}

		// 페이지가 없는경우 페이지를 구함
		$document_srl = Context::get('document_srl');
		if(!$args->page && $document_srl)
		{
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if($oDocument->isExists() && !$oDocument->isNotice())
			{
				$page = $oMarketplaceModel->getMarketplaceItemPage($oDocument, $args);
				Context::set('page', $page);
				$args->page = $page;
			}
		}

		// setup the list count to be serach list count, if the category or search keyword has been set
		if($args->category_srl || $args->search_keyword)
		{
			$args->list_count = $this->search_list_count;
		}

		// setup the list config variable on context
		Context::set('list_config', $this->listConfig);

		$output = $oMarketplaceModel->getMarketplaceItemList($args);

		Context::set('marketitem_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
	}


	/**
	 * @brief display marketplace wish list
	 **/
	function dispMarketplaceWishList(){


		// check grant
		if(!Context::get('is_logged'))
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		/**
		 * check the access grant (all the grant has been set by the module object)
		 **/
		if(!$this->grant->access || !$this->grant->list)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		/**
		 * display the category list, and then setup the category list on context
		 **/
		$this->dispMarketplaceCategoryList();

		// display the marketplace content
		$this->dispMarketplaceContentView();

		// check the grant
		if(!$this->grant->list)
		{
			Context::set('wish_list', array());
			Context::set('total_count', 0);
			Context::set('total_page', 1);
			Context::set('page', 1);
			Context::set('page_navigation', new PageHandler(0,0,1,10));
			return;
		}

		// setup module_srl/page number/ list number/ page count
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = (Context::get('list_count')) ? Context::get('list_count') : $this->list_count;
		$args->page_count = $this->page_count;

		// setup the list config variable on context
		Context::set('list_config', $this->listConfig);

		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getMarketplaceWishList();

		Context::set('wish_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		// setup the tmeplate file
		$this->setTemplateFile('wish_list');
	}


	function _makeListColumnList()
	{
		$configColumList = array_keys($this->listConfig);
		$tableColumnList = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'is_notice',
				'title', 'title_bold', 'title_color', 'content', 'readed_count', 'voted_count',
				'blamed_count', 'comment_count', 'trackback_count', 'uploaded_count', 'password', 'user_id',
				'user_name', 'nick_name', 'member_srl', 'email_address', 'homepage', 'tags', 'extra_vars',
				'regdate', 'last_update', 'last_updater', 'ipaddress', 'list_order', 'update_order',
				'allow_trackback', 'notify_message', 'status', 'comment_status');
		$this->columnList = array_intersect($configColumList, $tableColumnList);

		if(in_array('summary', $configColumList)) array_push($this->columnList, 'content');

		// default column list add
		$defaultColumn = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'member_srl', 'last_update', 'comment_count', 'trackback_count', 'uploaded_count', 'status', 'regdate', 'title_bold', 'title_color');

		//TODO guestbook, blog style supports legacy codes.
		if($this->module_info->skin == 'xe_guestbook' || $this->module_info->default_style == 'blog')
		{
			$defaultColumn = $tableColumnList;
		}

		if (in_array('last_post', $configColumList)){
			array_push($this->columnList, 'last_updater');
		}

		// add is_notice
		if ($this->except_notice)
		{
			array_push($this->columnList, 'is_notice');
		}
		$this->columnList = array_unique(array_merge($this->columnList, $defaultColumn));

		// add table name
		foreach($this->columnList as $no => $value)
		{
			$this->columnList[$no] = 'documents.' . $value;
		}
	}

	function dispMarketplaceAdvertiseList()
	{
		// 모듈 판매자 광고 설정 체크
		if(!$this->module_info->use_advertise)
		{
			return $this->stop('msg_invalid_request');
		}

		if($this->module_info->advertise_list_count)
			$list_count = $this->module_info->advertise_list_count;
		else $list_count = 5;

		$oMarketplaceModel = getModel('marketplace');

		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$args->sort_index = "advertise.bid_price";
		$args->order_type = "desc";
		$args->list_count = $list_count;

		$output = $oMarketplaceModel->getAdvertiseList($args);
		Context::set('advertise_list', $output->data);	
	}



	/**
	 * @brief display document write form
	 **/
	function dispMarketplaceWrite()
	{

		// check grant
		if(!Context::get('is_logged') || !$this->grant->write_document)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		$oDocumentModel = getModel('document');

		/**
		 * check if the category option is enabled not not
		 **/
		if($this->module_info->hide_category=='N')
		{
			// get the user group information
			if(Context::get('is_logged'))
			{
				$logged_info = Context::get('logged_info');
				$group_srls = array_keys($logged_info->group_list);
			}
			else
			{
				$group_srls = array();
			}
			$group_srls_count = count($group_srls);

			// check the grant after obtained the category list
			$normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
			if(count($normal_category_list))
			{
				foreach($normal_category_list as $category_srl => $category)
				{
					$is_granted = TRUE;
					if($category->group_srls)
					{
						$category_group_srls = explode(',',$category->group_srls);
						$is_granted = FALSE;
						if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = TRUE;

					}
					if($is_granted) $category_list[$category_srl] = $category;
				}
			}
			Context::set('category_list', $category_list);
		}

		// GET parameter document_srl from request
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
		$oDocument->setDocument($document_srl);

		if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = TRUE;
		$oDocument->add('module_srl', $this->module_srl);

		// if the document is not granted, then back to the password input form
		$oModuleModel = getModel('module');
		if($oDocument->isExists()&&!$oDocument->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}

		if(!$oDocument->isExists())
		{
			$point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
			$logged_info = Context::get('logged_info');
			$oPointModel = getModel('point');
			$pointForInsert = $point_config["insert_document"];
			if($pointForInsert < 0)
			{
				if( !$logged_info )
				{
					return $this->dispMarketplaceMessage('msg_not_permitted');
				}
				else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 )
				{
					return $this->dispMarketplaceMessage('msg_not_enough_point');
				}
			}
		}
		if(!$oDocument->get('status')) $oDocument->add('status', $oDocumentModel->getDefaultStatus());

		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(count($statusList) > 0) Context::set('status_list', $statusList);

		// get Document status config value
		Context::set('document_srl',$document_srl);
		Context::set('oDocument', $oDocument);

		// get Korea Disticts
		$district_file = FileHandler::readFile(_XE_PATH_ . 'modules/marketplace/districts.ko.csv');
		$district_arr = explode("\n", $district_file); 
		Context::set('korea_districts', $district_arr);

		// get member contact
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($logged_info->member_srl);
		if($this->module_info->contact_number_field)
		{
			$contact_number = implode('-',$member_info->{$this->module_info->contact_number_field});
			Context::set('contact_number', $contact_number);
		}

		// apply xml_js_filter on header
		$oDocumentController = getController('document');
		$oDocumentController->addXmlJsFilter($this->module_info->module_srl);

		// if the document exists, then setup extra variabels on context
		if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());

		/**
		 * add JS filters
		 **/
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.text', 'category_list.title');

		$this->setTemplateFile('write_form');
	}



	/**
	 * @brief display document write form
	 **/
	function dispMarketplaceNoticeWrite()
	{

		// check grant
		if(!$this->grant->manager)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		$oDocumentModel = getModel('document');

		/**
		 * check if the category option is enabled not not
		 **/
		if($this->module_info->hide_category=='N')
		{
			// get the user group information
			if(Context::get('is_logged'))
			{
				$logged_info = Context::get('logged_info');
				$group_srls = array_keys($logged_info->group_list);
			}
			else
			{
				$group_srls = array();
			}
			$group_srls_count = count($group_srls);

			// check the grant after obtained the category list
			$normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
			if(count($normal_category_list))
			{
				foreach($normal_category_list as $category_srl => $category)
				{
					$is_granted = TRUE;
					if($category->group_srls)
					{
						$category_group_srls = explode(',',$category->group_srls);
						$is_granted = FALSE;
						if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = TRUE;

					}
					if($is_granted) $category_list[$category_srl] = $category;
				}
			}
			Context::set('category_list', $category_list);
		}

		// GET parameter document_srl from request
		$document_srl = Context::get('document_srl');
		$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
		$oDocument->setDocument($document_srl);

		if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = TRUE;
		$oDocument->add('module_srl', $this->module_srl);

		// if the document is not granted, then back to the password input form
		$oModuleModel = getModel('module');
		if($oDocument->isExists()&&!$oDocument->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}

		if(!$oDocument->isExists())
		{
			$point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
			$logged_info = Context::get('logged_info');
			$oPointModel = getModel('point');
			$pointForInsert = $point_config["insert_document"];
			if($pointForInsert < 0)
			{
				if( !$logged_info )
				{
					return $this->dispMarketplaceMessage('msg_not_permitted');
				}
				else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 )
				{
					return $this->dispMarketplaceMessage('msg_not_enough_point');
				}
			}
		}
		if(!$oDocument->get('status')) $oDocument->add('status', $oDocumentModel->getDefaultStatus());

		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(count($statusList) > 0) Context::set('status_list', $statusList);

		// get Document status config value
		Context::set('document_srl',$document_srl);
		Context::set('oDocument', $oDocument);

		// apply xml_js_filter on header
		$oDocumentController = getController('document');
		$oDocumentController->addXmlJsFilter($this->module_info->module_srl);

		// if the document exists, then setup extra variabels on context
		if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());

		/**
		 * add JS filters
		 **/
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.text', 'category_list.title');

		$this->setTemplateFile('write_notice_form');
	}


	function dispMarketplaceItemManage()
	{
		$logged_info = Context::get('logged_info');
		$oMarketplaceModel = getModel('marketplace');
		$item_status = Context::get('item_status');

		// check grant
		if(!$logged_info) {
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}
	
		// Disp category list
		$this->dispMarketplaceCategoryList();

		// Get Item List
		$args = new stdClass();		
		$args->module_srl = $this->module_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->item_status = $item_status;
		$args->list_count = 5;
		$args->page = Context::get('page');
		$output = $oMarketplaceModel->getMarketplaceItemList($args);

		Context::set('item_status', Context::get('item_status'));		
		Context::set('marketitem_list', $output->data);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		// get item status count
		$output = $oMarketplaceModel->getMarketplaceItemStatusCount($logged_info->member_srl);
		Context::set('total_count', $output->total);
		Context::set('selling_count', $output->selling);
		Context::set('soldout_count', $output->soldout);
		Context::set('cancel_count', $output->cancel);

		Context::set('oMarketplaceModel', getModel('marketplace'));

		$this->setTemplateFile('item_manage');
	}


	/**
	 * @brief display comments of seller items
	 **/
	function dispMarketplaceItemComments()
	{
		// check grant
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}
		$args->page = Context::get('page');
		$args->list_count = (Context::get('list_count')) ? Context::get('list_count') : 7;
		$args->page_count = $this->page_count;

		$oMemberModel = getModel('member');
		$oMarketplaceModel = getModel('marketplace');
		$args->member_srl = $logged_info->member_srl;
		$output = $oMarketplaceModel->getMarketplaceSellerItemComments($args);
		if(!$output->toBool())
		{
			return;
		}

		$comment_list = $output->data;
		if($comment_list)
		{
			if(!is_array($comment_list))
			{
				$comment_list = array($comment_list);
			}

			$comment_count = count($comment_list);
			foreach($comment_list as $key => $attribute)
			{
				$oCommentModel = getModel('comment');
				if(!$attribute->comment_srl)
				{
					continue;
				}
				$result[$attribute->comment_srl] = 	$oCommentModel->getComment($attribute->comment_srl);

				$obj = new StdClass();
				$obj->comment_srl = $attribute->comment_srl;
				$obj->oMarketItem = $oMarketplaceModel->getMarketplaceItem($attribute->document_srl);
				$result[$attribute->comment_srl]->setAttribute($obj);
			}
		}
		Context::set('comment_list', $result);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('item_comments');

	}



	function dispMarketplaceMemberModify()
	{
		// Set logged info
		$logged_info = Context::get('logged_info');
		Context::set('logged_info', $logged_info);

		// Check grant
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		$this->setTemplateFile('member_modify');

	}
	function dispMarketplaceAdvertiseInsert()
	{
		// 모듈 판매자 광고 설정 체크
		if(!$this->module_info->use_advertise)
		{
			return $this->stop('msg_invalid_request');
		}

		// check grant
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		$document_srl = Context::get('document_srl');
		$output = executeQueryArray('marketplace.getAdvertiseBidList', $args);
		Context::set('advertise_list', $output->data);
		
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getAdvertise($document_srl);
		if($output->data)
		Context::set('advertise_info', $output->data);

		$oMarketItem = $oMarketplaceModel->getMarketplaceItem(Context::get('document_srl'));
		Context::set('oMarketItem', $oMarketItem);

		$this->setBlankLayout();
		$this->setTemplateFile('advertise_insert_cpc');
	}


	function dispMarketplaceAdvertiseManage()
	{
		// 모듈 판매자 광고 설정 체크
		if(!$this->module_info->use_advertise)
		{
			return $this->stop('msg_invalid_request');
		}

		// check grant
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// Disp category list
		$this->dispMarketplaceCategoryList();

		$oMarketplaceModel = getModel('marketplace');
		$args->module_srl = $this->module_srl;
		$args->member_srl = $logged_info->member_srl;
		$output = $oMarketplaceModel->getAdvertiseList($args);

		Context::set('oMarketplaceModel', $oMarketplaceModel);

		Context::set('advertise_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('advertise_manage');
	}


	function dispMarketplaceAdvertiseLog()
	{

		// 모듈 판매자 광고 설정 체크
		if(!$this->module_info->use_advertise)
		{
			return $this->stop('msg_invalid_request');
		}

		// check grant
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}
		$module_info = Context::get('module_info');
		
		$args= new stdClass();
		$args->module_srl = $module_info->module_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->page = Context::get('page');
		$args->list_count = (Context::get('list_count')) ? Context::get('list_count') : 12;
		$args->page_count = $this->page_count;
		$args->order_type = 'desc';

		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getAdvertiseLogList($args);

		// hide ip address
		foreach($output->data as $key => $val)
		{
			$output->data[$key]->ipaddress = '*' . strstr($output->data[$key]->ipaddress, '.');
		}

		Context::set('log_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('advertise_log');
	}


	function dispMarketplaceKeywordManage()
	{
		// check grant
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// check using this function
		if(!$this->module_info->use_keyword_notify)
			return new Object(-1, 'msg_invalid_request');

		$member_srl = $logged_info->member_srl;

		// Get user keyword
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getKeywordsByMemberSrl($member_srl, $this->module_srl);
		$keyword_list = $output->data;

		// Get documents by keyword
		if(count($keyword_list))
		{
			$args->module_srl = $this->module_srl;
			$args->page = Context::get('page');
			$args->list_count = (Context::get('list_count')) ? Context::get('list_count') : 6;
			$args->page_count = $this->page_count;

			foreach($keyword_list as $key) 
				$args->keywords[] = $key->keyword;
			$output = $oMarketplaceModel->getItemListByKeywords($args);
		}
		
		// Display category list
		$this->dispMarketplaceCategoryList();

		// Set variable for template
		Context::set('keyword_list', $keyword_list);
		Context::set('marketitem_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('keyword_manage');
	}

	function _getStatusNameList(&$oDocumentModel)
	{
		$resultList = array();
		if(!empty($this->module_info->use_status))
		{
			$statusNameList = $oDocumentModel->getStatusNameList();
			$statusList = explode('|@|', $this->module_info->use_status);

			if(is_array($statusList))
			{
				foreach($statusList as $key => $value)
				{
					$resultList[$value] = $statusNameList[$value];
				}
			}
		}
		return $resultList;
	}

	/**
	 * @brief display marketplace module deletion form
	 **/
	function dispMarketplaceDelete()
	{
		// check grant
		if(!$this->grant->write_document)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// get the document_srl from request
		$document_srl = Context::get('document_srl');

		// if document exists, get the document information
		if($document_srl)
		{
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
		}

		// if the document is not existed, then back to the marketplace content page
		if(!$oDocument->isExists())
		{
			return $this->dispMarketplaceContent();
		}

		// if the document is not granted, then back to the password input form
		if(!$oDocument->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}

		Context::set('oDocument',$oDocument);

		/**
		 * add JS filters
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_document.xml');

		$this->setTemplateFile('delete_form');
	}

	/**
	 * @brief display comment wirte form
	 **/
	function dispMarketplaceWriteComment()
	{
		$document_srl = Context::get('document_srl');

		// check grant
		if(!$this->grant->write_comment)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// get the document information
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists())
		{
			return $this->dispMarketplaceMessage('msg_invalid_request');
		}

		// Check allow comment
		if(!$oDocument->allowComment())
		{
			return $this->dispMarketplaceMessage('msg_not_allow_comment');
		}

		// obtain the comment (create an empty comment document for comment_form usage)
		$oCommentModel = getModel('comment');
		$oSourceComment = $oComment = $oCommentModel->getComment(0);
		$oComment->add('document_srl', $document_srl);
		$oComment->add('module_srl', $this->module_srl);

		// setup document variables on context
		Context::set('oDocument',$oDocument);
		Context::set('oSourceComment',$oSourceComment);
		Context::set('oComment',$oComment);

		/**
		 * add JS filter
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
	}

	/**
	 * @brief display comment replies page
	 **/
	function dispMarketplaceReplyComment()
	{
		// check grant
		if(!$this->grant->write_comment)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// get the parent comment ID
		$parent_srl = Context::get('comment_srl');

		// if the parent comment is not existed
		if(!$parent_srl)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		// get the comment
		$oCommentModel = getModel('comment');
		$oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);

		// if the comment is not existed, opoup an error message
		if(!$oSourceComment->isExists())
		{
			return $this->dispMarketplaceMessage('msg_invalid_request');
		}
		if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl'))
		{
			return $this->dispMarketplaceMessage('msg_invalid_request');
		}

		// Check allow comment
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($oSourceComment->get('document_srl'));
		if(!$oDocument->allowComment())
		{
			return $this->dispMarketplaceMessage('msg_not_allow_comment');
		}

		// get the comment information
		$oComment = $oCommentModel->getComment();
		$oComment->add('parent_srl', $parent_srl);
		$oComment->add('document_srl', $oSourceComment->get('document_srl'));

		// setup comment variables
		Context::set('oSourceComment',$oSourceComment);
		Context::set('oComment',$oComment);
		Context::set('module_srl',$this->module_info->module_srl);

		/**
		 * add JS filters
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
	}

	/**
	 * @brief display the comment modification from
	 **/
	function dispMarketplaceModifyComment()
	{
		// check grant
		if(!$this->grant->write_comment)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// get the document_srl and comment_srl
		$document_srl = Context::get('document_srl');
		$comment_srl = Context::get('comment_srl');

		// if the comment is not existed
		if(!$comment_srl)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		// get comment information
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

		// if the comment is not exited, alert an error message
		if(!$oComment->isExists())
		{
			return $this->dispMarketplaceMessage('msg_invalid_request');
		}

		// if the comment is not granted, then back to the password input form
		if(!$oComment->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}

		// setup the comment variables on context
		Context::set('oSourceComment', $oCommentModel->getComment());
		Context::set('oComment', $oComment);

		/**
		 * add JS fitlers
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
	}

	/**
	 * @brief display the delete comment  form
	 **/
	function dispMarketplaceDeleteComment()
	{
		// check grant
		if(!$this->grant->write_comment)
		{
			return $this->dispMarketplaceMessage('msg_not_permitted');
		}

		// get the comment_srl to be deleted
		$comment_srl = Context::get('comment_srl');

		// if the comment exists, then get the comment information
		if($comment_srl)
		{
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
		}

		// if the comment is not existed, then back to the marketplace content page
		if(!$oComment->isExists() )
		{
			return $this->dispMarketplaceContent();
		}

		// if the comment is not granted, then back to the password input form
		if(!$oComment->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}

		Context::set('oComment',$oComment);

		/**
		 * add JS filters
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');

		$this->setTemplateFile('delete_comment_form');
	}

	/**
	 * @brief display the delete trackback form
	 **/
	function dispMarketplaceDeleteTrackback()
	{
		$oTrackbackModel = getModel('trackback');

		if(!$oTrackbackModel)
		{
			return;
		}

		// get the trackback_srl
		$trackback_srl = Context::get('trackback_srl');

		// get the trackback data
		$columnList = array('trackback_srl');
		$output = $oTrackbackModel->getTrackback($trackback_srl, $columnList);
		$trackback = $output->data;

		// if no trackback, then display the marketplace content
		if(!$trackback)
		{
			return $this->dispMarketplaceContent();
		}

		//Context::set('trackback',$trackback);	//perhaps trackback variables not use in UI

		/**
		 * add JS filters
		 **/
		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');

		$this->setTemplateFile('delete_trackback_form');
	}

	/**
	 * @brief display marketplace message
	 **/
	function dispMarketplaceMessage($msg_code)
	{
		$msg = Context::getLang($msg_code);
		if(!$msg) $msg = $msg_code;
		Context::set('message', $msg);
		$this->setTemplateFile('message');
	}


	function dispMarketplaceAddContent()
	{
		$oMarketplaceModel = getModel('marketplace');

		// GET parameter document_srl from request
		$document_srl = Context::get('document_srl');
		$oMarketItem = $oMarketplaceModel->getMarketplaceItem($document_srl);
		if(!$oMarketItem->isSelling()) {
			$this->setBlankLayout();
			return new Object(-1,'msg_invalid_request');
		}

		// check grant
		if($oMarketItem->isExists()&&!$oMarketItem->isGranted())
		{
			return $this->setTemplateFile('input_password_form');
		}
		Context::set('oDocument', $oMarketItem);

		$this->setTemplateFile('add_content');
		$this->setBlankLayout();
	}

	function dispMarketplaceModifyItem()
	{
		$oDocumentModel = getModel('document');
		$oMarketplaceModel = getModel('marketplace');

		// GET parameter document_srl from request
		$document_srl = Context::get('document_srl');
		$oMarketItem = $oMarketplaceModel->getMarketplaceItem($document_srl);

		// check grant
		if($oMarketItem->isExists()&&!$oMarketItem->isGranted())
		{
			$this->setBlankLayout();		
			$this->setTemplateFile('input_password_form');
			return false;
		}
		Context::set('oMarketItem', $oMarketItem);

		// 상품 수정 기능 옵션 체크
		if(!$this->module_info->item_modify || $this->module_info->item_modify =='N')
		{
			return new Object(-1, 'msg_invalid_request');
		}

		/**
		 * check if the category option is enabled not not
		 **/
		if($this->module_info->hide_category=='N')
		{
			// get the user group information
			if(Context::get('is_logged'))
			{
				$logged_info = Context::get('logged_info');
				$group_srls = array_keys($logged_info->group_list);
			}
			else
			{
				$group_srls = array();
			}
			$group_srls_count = count($group_srls);

			// check the grant after obtained the category list
			$normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
			if(count($normal_category_list))
			{
				foreach($normal_category_list as $category_srl => $category)
				{
					$is_granted = TRUE;
					if($category->group_srls)
					{
						$category_group_srls = explode(',',$category->group_srls);
						$is_granted = FALSE;
						if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = TRUE;

					}
					if($is_granted) $category_list[$category_srl] = $category;
				}
			}
			Context::set('category_list', $category_list);
		}

		// get Korea Disticts
		$district_file = FileHandler::readFile(_XE_PATH_ . 'modules/marketplace/districts.ko.csv');
		$district_arr = explode("\n", $district_file); 

		Context::set('korea_districts', $district_arr);


		$this->setTemplateFile('modify_item');
		$this->setBlankLayout();

	}

	/**
	 * @brief the method for displaying the warning messages
	 * display an error message if it has not  a special design
	 **/
	function alertMessage($message)
	{
		$script =  sprintf('<script> jQuery(function(){ alert("%s"); } );</script>', Context::getLang($message));
		Context::addHtmlFooter( $script );
	}

	function setBlankLayout()
	{
		$this->setLayoutPath('./modules/marketplace/tpl/');
		$this->setLayoutFile('blank_layout.html');
	}

}
