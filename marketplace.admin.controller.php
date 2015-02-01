<?php
/**
 * @class  marketplaceAdminController
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module admin controller class
 **/

class marketplaceAdminController extends marketplace {

	/**
	 * @brief initialization
	 **/
	function init() {
	}

	/**
	 * @brief insert borad module
	 **/
	public function procMarketplaceAdminInsertMarketplace($args = null) {

		// igenerate module model/controller object
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		// setup the marketplace module infortmation
		$args = Context::getRequestVars();
		$args->module = 'marketplace';
		$args->mid = $args->marketplace_name;
		if(is_array($args->use_status)) $args->use_status = implode('|@|', $args->use_status);
		unset($args->marketplace_name);

		// setup extra_order_target
		$extra_order_target = array();
		if($args->module_srl)
		{
			$oDocumentModel = getModel('document');
			$module_extra_vars = $oDocumentModel->getExtraKeys($args->module_srl);
			foreach($module_extra_vars as $oExtraItem)
			{
				$extra_order_target[$oExtraItem->eid] = $oExtraItem->name;
			}
		}

		// setup other variables
		if($args->except_notice != 'Y') $args->except_notice = 'N';
		if(!in_array($args->order_target,$this->order_target) && !in_array($args->order_target, $extra_order_target)) $args->order_target = 'list_order';
		if(!in_array($args->order_type, array('asc', 'desc'))) $args->order_type = 'asc';

		// if there is an existed module
		if($args->module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
		}

		// insert/update the marketplace module based on module_srl
		if(!$args->module_srl) {
			$args->hide_category = 'N';
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		} else {
			$args->hide_category = $module_info->hide_category;
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}

		if(!$output->toBool()) return $output;

		// setup list config
		$list = explode(',',Context::get('list'));
		if(count($list))
		{
			$list_arr = array();
			foreach($list as $val)
			{
				$val = trim($val);
				if(!$val) continue;
				if(substr($val,0,10)=='extra_vars') $val = substr($val,10);
				$list_arr[] = $val;
			}
			$oModuleController = getController('module');
			$oModuleController->insertModulePartConfig('marketplace', $output->get('module_srl'), $list_arr);
		}

		$this->setMessage($msg_code);
		if (Context::get('success_return_url')){
			changeValueInUrl('mid', $args->mid, $module_info->mid);
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMarketplaceAdminMarketplaceInfo', 'module_srl', $output->get('module_srl')));
		}
	}

	public function procMarketplaceAdminInsertItemCondition() 
	{
		$module_srl = Context::get('module_srl');

		// insert part config
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		$obj->eid = Context::get('eid');
		$obj->name = Context::get('name');
		$obj->short_name = Context::get('short_name');
		$obj->desc = Context::get('desc');

		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getSettingCondition($module_srl, Context::get('eid'));
		if($output->data)
		{
			//update if exist
			$this->setMessage('success_updated');
			$output = executeQuery('marketplace.updateSettingCondition', $obj);
			if(!$output->toBool())	return $output;
		}
		else 
		{
			//insert if not exist
			$output = executeQuery('marketplace.getSettingConditionMaxIdx', $obj);
			if(!$output->toBool())	return $output;

			$obj->idx = intval($output->data->idx) + 1;
			$output = executeQuery('marketplace.insertSettingCondition', $obj);
			if(!$output->toBool())	return $output;
		}

		// redirect
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMarketplaceAdminItemConditions', 'module_srl', $module_srl));
		}
	}
	public function procMarketplaceAdminDeleteItemCondition() 
	{	
		$module_srl = Context::get('module_srl');
		$eid = Context::get('eid');

		if(!$module_srl || !$eid) return new Object(-1,'msg_invalid_request');
		
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!$module_info->module_srl) return new Object(-1,'msg_invalid_request');

		$oMarketplaceController = getController('marketplace');
		$oMarketplaceController->deleteItemCondition($module_srl, $eid);
	}

	public function procMarketplaceAdminMoveItemCondition() 
	{	
		$type = Context::get('type');
		$module_srl = Context::get('module_srl');
		$eid = Context::get('eid');

		if(!$module_srl || !$eid || !$type) return new Object(-1,'msg_invalid_request');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!$module_info->module_srl) return new Object(-1,'msg_invalid_request');

		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getSettingCondition($module_srl, $eid);
		if(!$output->data) return new Object(-1,'msg_invalid_request');
		$idx = $output->data->idx;

		if($type == 'up') $new_idx = $idx-1;
		else $new_idx = $idx+1;
		if($new_idx<1) return new Object(-1,'msg_invalid_request');

		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->idx = $new_idx;
		
		$output = executeQuery('marketplace.getSettingConditionByIdx', $args);
		if (!$output->toBool()) return $output;
		if (!$output->data) return new Object(-1, 'msg_invalid_request');
		unset($args);
		
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->idx = $new_idx;
		$args->new_idx = -10000;
		$output = executeQuery('marketplace.updateSettingConditionIdx', $args);
		if(!$output->toBool()) return $output;

		$args->idx = $idx;
		$args->new_idx = $new_idx;
		$output = executeQuery('marketplace.updateSettingConditionIdx', $args);
		if(!$output->toBool()) return $output;

		$args->idx = -10000;
		$args->new_idx = $idx;
		$output = executeQuery('marketplace.updateSettingConditionIdx', $args);
		if(!$output->toBool()) return $output;
	}

	/**
	 * Marketplace info update in basic setup page
	 * @return void
	 */
	public function procMarketplaceAdminUpdateMarketplaceFroBasic()
	{
		$args = Context::getRequestVars();

		// for marketplace info
		$args->module = 'marketplace';
		$args->mid = $args->marketplace_name;
		if(is_array($args->use_status))
		{
			$args->use_status = implode('|@|', $args->use_status);
		}
		unset($args->marketplace_name);

		if(!in_array($args->order_target, $this->order_target))
		{
			$args->order_target = 'list_order';
		}
		if(!in_array($args->order_type, array('asc', 'desc')))
		{
			$args->order_type = 'asc';
		}

		$oModuleController = getController('module');
		$output = $oModuleController->updateModule($args);

		// for grant info, Register Admin ID
		$oModuleController->deleteAdminId($args->module_srl);
		if($args->admin_member)
		{
			$admin_members = explode(',',$args->admin_member);
			for($i=0;$i<count($admin_members);$i++)
			{
				$admin_id = trim($admin_members[$i]);
				if(!$admin_id) continue;
				$oModuleController->insertAdminId($args->module_srl, $admin_id);
			}
		}
	}

	/**
	 * @brief delete the marketplace module
	 **/
	public function procMarketplaceAdminDeleteMarketplace() {
		$module_srl = Context::get('module_srl');

		// get the current module
		$oModuleController = getController('module');
		$output = $oModuleController->deleteModule($module_srl);
		if(!$output->toBool()) return $output;

		$this->add('module','marketplace');
		$this->add('page',Context::get('page'));
		$this->setMessage('success_deleted');
	}

	public function procMarketplaceAdminSaveCategorySettings()
	{
		$module_srl = Context::get('module_srl');
		$mid = Context::get('mid');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if($module_info->mid != $mid)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$module_info->hide_category = Context::get('hide_category') == 'Y' ? 'Y' : 'N';
		$oModuleController = getController('module'); /* @var $oModuleController moduleController */
		$output = $oModuleController->updateModule($module_info);
		if(!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMarketplaceAdminCategoryInfo', 'module_srl', $output->get('module_srl')));
		}
	}
}
