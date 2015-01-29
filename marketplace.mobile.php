<?php
/**
 * @class  marketplaceMobile
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module Mobile class
 **/

require_once(_XE_PATH_.'modules/marketplace/marketplace.view.php');

class marketplaceMobile extends marketplaceView
{
	function init()
	{
		$oSecurity = new Security();
		$oSecurity->encodeHTML('document_srl', 'comment_srl', 'vid', 'mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type', 'trackback_srl', 'price_from', 'price_to', 'priority_area', 'item_condition.', 'used_month', 'item_status', 'list_count');

		if($this->module_info->list_count) $this->list_count = $this->module_info->list_count;
		if($this->module_info->search_list_count) $this->search_list_count = $this->module_info->search_list_count;
		if($this->module_info->page_count) $this->page_count = $this->module_info->page_count;
		$this->except_notice = $this->module_info->except_notice == 'N' ? false : true;

		// $this->_getStatusNameListecret option backward compatibility
		$oDocumentModel = getModel('document');

		$statusList = $this->_getStatusNameList($oDocumentModel);
		if(isset($statusList['SECRET']))
		{
			$this->module_info->secret = 'Y';
		}

		// hide category
		$count_category = count($oDocumentModel->getCategoryList($this->module_info->module_srl));
		if($count_category) $this->module_info->hide_category = 'N';
		else $this->module_info->hide_category = 'Y';


		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getSettingConditions($this->module_srl);
		if(!$output->toBool())	return $output;
		foreach($output->data as $key => $val)
		{
			$condition_list[$val->eid] = $val;
		}
		$this->condition_list = $condition_list;
		Context::set('condition_list', $condition_list);

		Context::addJsFile($this->module_path.'tpl/js/marketplace.js');

		$oDocumentModel = getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
		Context::set('extra_keys', $extra_keys);

		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin)
		{
			$this->module_info->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		}
		$this->setTemplatePath($template_path);
		Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
	}

	function dispMarketplaceCategory()
	{
		$this->dispMarketplaceCategoryList();
		$category_list = Context::get('category_list');
		$this->setTemplateFile('category.html');
	}

	function getMarketplaceCommentPage()
	{
		$document_srl = Context::get('document_srl');
		$oDocumentModel =& getModel('document');
		if(!$document_srl)
		{
			return new Object(-1, "msg_invalid_request");
		}
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists())
		{
			return new Object(-1, "msg_invalid_request");
		}
		Context::set('oDocument', $oDocument);
		$oTemplate = TemplateHandler::getInstance();
		$html = $oTemplate->compile($this->getTemplatePath(), "comment.html");
		$this->add("html", $html);
	}

	function dispMarketplaceMessage($msg_code)
	{
		$msg = Context::getLang($msg_code);
		$oMessageObject = &ModuleHandler::getModuleInstance('message','mobile');
		$oMessageObject->setError(-1);
		$oMessageObject->setMessage($msg);
		$oMessageObject->dispMessage();

		$this->setTemplatePath($oMessageObject->getTemplatePath());
		$this->setTemplateFile($oMessageObject->getTemplateFile());
	}
}
