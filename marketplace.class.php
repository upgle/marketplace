<?php
require_once(_XE_PATH_.'modules/marketplace/marketplace.item.php');

/**
 * @class  marketplace
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module high class
 **/

class marketplace extends ModuleObject
{
	var $search_option = array('title_content','title','content','comment','user_name','nick_name','user_id','tag'); ///< 검색 옵션

	var $order_target = array('list_order', 'update_order', 'regdate', 'voted_count', 'blamed_count', 'readed_count', 'comment_count', 'title', 'nick_name', 'user_name', 'user_id', 'price'); // 정렬 옵션

	var $skin = "default"; ///< skin name
	var $list_count = 20; ///< the number of documents displayed in a page
	var $page_count = 10; ///< page number
	var $category_list = NULL; ///< category list
	var $default_column = array();

	/**
	 * @brief install the module
	 **/
	function moduleInstall()
	{
		// use action forward(enabled in the admin model)
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		// install marketplace module
		$args = new stdClass;
		$args->site_srl = 0;
		$output = executeQuery('module.getSite', $args);
		if(!$output->data->index_module_srl)
		{
			$args->mid = 'marketplace';
			$args->module = 'marketplace';
			$args->browser_title = 'XpressEngine';
			$args->skin = 'default';
			$args->site_srl = 0;
			$output = $oModuleController->insertModule($args);

			if($output->toBool())
			{
				$module_srl = $output->get('module_srl');

				$site_args = new stdClass;
				$site_args->site_srl = 0;
				$site_args->index_module_srl = $module_srl;

				$oModuleController = getController('module');
				$oModuleController->updateSite($site_args);
			}
		}
		return new Object();
	}

	/**
	 * @brief chgeck module method
	 **/
	function checkUpdate()
	{
		$oModuleModel = getModel('module');

		// when add new menu in sitemap, custom menu add
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'marketplace', 'model', 'triggerModuleListInSitemap', 'after')) return true;

		if(!$oModuleModel->getTrigger('document.deleteDocument', 'marketplace', 'controller', 'triggerDeleteMarketplaceItem', 'after')) return true;

		// 오래된 키워드 및 검색된 문서 삭제 keyword_expiry_date	
		$doDelete = true;
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$doDelete = false;
			$cache_key = $oCacheHandler->getGroupKey('marketplace', 'last_olditem_delete');
			$last_date = $oCacheHandler->get($cache_key);
			if($last_date != date('Ymd',time())) $doDelete = true;
		}
		if($doDelete)
		{
			$args->module = 'marketplace';
			$module_list = $oModuleModel->getModuleSrlList($args);
			foreach($module_list as $val)
			{
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($val->module_srl);
				if(!$module_info->keyword_expiry_date) $module_info->keyword_expiry_date = 1;
				$expire_month = $module_info->keyword_expiry_date*-1;

				$args = new stdClass();
				$args->module_srl = $val->module_srl;
				$args->regdate = date('YmdHis', strtotime($expire_month.'month'));
				$output = executeQuery('marketplace.deleteKeywordDocumentOld', $args);
				$output = executeQuery('marketplace.deleteKeywordMemberOld', $args);
			}
			if($oCacheHandler->isSupport())
			{
				$oCacheHandler->put($cache_key, date('Ymd',time()));
			}
		}

	}

	/**
	 * @brief update module
	 **/
	function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		// when add new menu in sitemap, custom menu add
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'marketplace', 'model', 'triggerModuleListInSitemap', 'after'))
		{
			$oModuleController->insertTrigger('menu.getModuleListInSitemap', 'marketplace', 'model', 'triggerModuleListInSitemap', 'after');
		}

		// when add new menu in sitemap, custom menu add
		if(!$oModuleModel->getTrigger('document.deleteDocument', 'marketplace', 'controller', 'triggerDeleteMarketplaceItem', 'after'))
		{
			$oModuleController->insertTrigger('document.deleteDocument', 'marketplace', 'controller', 'triggerDeleteMarketplaceItem', 'after');
		}


		return new Object(0, 'success_updated');
	}

	function moduleUninstall()
	{
		$output = executeQueryArray("marketplace.getAllMarketplace");
		if(!$output->data) return new Object();
		@set_time_limit(0);

		$oModuleController = getController('module');

		foreach($output->data as $marketplace)
		{
			$oModuleController->deleteModule($marketplace->module_srl);
		}

		return new Object();
	}
}
