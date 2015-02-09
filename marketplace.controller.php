<?php
/**
 * @class  marketplaceController
 * @author UPGLE (admin@upgle.com)
 * @brief  marketplace module Controller class
 **/

class marketplaceController extends marketplace
{

	/**
	 * @brief initialization
	 **/
	function init()
	{
	}

	/**
	 * @brief insert document
	 **/
	function procMarketplaceInsertDocument()
	{
		// check grant
		if($this->module_info->module != "marketplace")
		{
			return new Object(-1, "msg_invalid_request");
		}

		if(!$this->grant->write_document)
		{
			return new Object(-1, 'msg_not_permitted');
		}
		
		// 업로드 전 섬네일 파일 체크
		$oFileModel = getModel('file');
		$file_module_config = $oFileModel->getFileConfig($this->module_srl);
		$allowed_filesize = $file_module_config->allowed_filesize * 1024 * 1024;
		foreach(Context::get('thumbnail') as $key => $file)
		{
			// 이미지 형식 체크
			if(!preg_match("/\.(jpg|png|jpeg|gif|bmp)$/i",$file['name'])) {
				return new Object(-1, 'msg_thumbnail_image_file_only');
			}	
			// 파일 사이즈 체크
			if($allowed_filesize < filesize($file['tmp_name'])) return new Object(-1, 'msg_thumbnail_exceeds_limit_size');
		}

		// Insert document and item
		$output = $this->_insertDocument();
		$document_srl = $output->get('document_srl');
		$this->_checkKeyword($document_srl);

		// alert a message
		$this->setMessage($output->get('msg_code'));

		if (Context::get('success_return_url')){
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $document_srl));
		}
	}

	/**
	 * @brief insert Notice
	 **/
	function procMarketplaceInsertNotice()
	{
		// check grant
		if($this->module_info->module != "marketplace")
		{
			return new Object(-1, "msg_invalid_request");
		}

		if(!$this->grant->manager)
		{
			return new Object(-1, 'msg_not_permitted');
		}

		// Insert document and item
		$output = $this->_insertDocument();
		$document_srl = $output->get('document_srl');

		// alert a message
		$this->setMessage($output->get('msg_code'));

		if (Context::get('success_return_url')){
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $document_srl));
		}
	}

	private function _insertDocument()
	{
		global $lang;

		// get logged info
		$logged_info = Context::get('logged_info');

		// begin transaction
		$oDB = &DB::getInstance();
		$oDB->begin();

		// setup variables
		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;
		if($obj->is_notice!='Y'||!$this->grant->manager) $obj->is_notice = 'N';

		settype($obj->title, "string");
		if($obj->title == '') $obj->title = cut_str(strip_tags($obj->content),20,'...');
		//setup dpcument title tp 'Untitled'
		if($obj->title == '') $obj->title = 'Untitled';

		// unset document style if the user is not the document manager
		if(!$this->grant->manager)
		{
			unset($obj->title_color);
			unset($obj->title_bold);
		}

		// generate document module model object
		$oDocumentModel = getModel('document');

		// generate document module의 controller object
		$oDocumentController = getController('document');

		// check if the document is existed
		$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);

		// update the document if it is existed
		$is_update = false;
		if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl)
		{
			$is_update = true;
		}

		// set status
		$obj->status = 'PUBLIC';
		$obj->commentStatus = 'ALLOW';

		// update the document if it is existed
		if($is_update)
		{
			if(!$oDocument->isGranted())
			{
				return new Object(-1,'msg_not_permitted');
			}

			if(!$this->grant->manager)
			{
				// notice & document style same as before if not manager
				$obj->is_notice = $oDocument->get('is_notice');
				$obj->title_color = $oDocument->get('title_color');
				$obj->title_bold = $oDocument->get('title_bold');
			}
			
			// modify list_order if document status is temp
			if($oDocument->get('status') == 'TEMP')
			{
				$obj->last_update = $obj->regdate = date('YmdHis');
				$obj->update_order = $obj->list_order = (getNextSequence() * -1);
			}

			$output = $oDocumentController->updateDocument($oDocument, $obj);
			$output->add('msg_code','success_updated');

		// insert a new document otherwise
		} else {
			$output = $oDocumentController->insertDocument($obj);
			$output->add('msg_code','success_registed');

			$obj->document_srl = $output->get('document_srl');

			// insert detailed information
			if($obj->is_notice != 'Y')
			{
				$output2 = $this->_insertMarketItem($obj);
				if(!$output2->toBool()) 
				{
					$oDB->rollback();
					return $output2;
				}
			}

			// send an email to admin user
			if($output->toBool() && $this->module_info->admin_mail)
			{
				$oMail = new Mail();
				$oMail->setTitle($obj->title);
				$oMail->setContent( sprintf("From : <a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $obj->content));
				$oMail->setSender($obj->user_name, $obj->email_address);

				$target_mail = explode(',',$this->module_info->admin_mail);
				for($i=0;$i<count($target_mail);$i++)
				{
					$email_address = trim($target_mail[$i]);
					if(!$email_address) continue;
					$oMail->setReceiptor($email_address, $email_address);
					$oMail->send();
				}
			}
		}

		// if there is an error
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// commit
		$oDB->commit();

		return $output;
	}

	private function _insertMarketItem($obj) 
	{
		$args = new stdClass;

		//thumbnail upload
		$args->thumbnails_srl = getNextSequence();
		$oFileController = getController('file');
		foreach($obj->thumbnail as $key => $file)
		{
			if(!is_uploaded_file($file['tmp_name'])) continue;

            $output = $oFileController->insertFile($file, $this->module_srl, $args->thumbnails_srl);
            if(!$output->toBool()) return $output;
			
			$obj->file_srl = $output->get('file_srl');
			$obj->comment = $key;
			$output = executeQuery('marketplace.updateFileComment', $obj);
		}
		$args->document_srl = $obj->document_srl;
		$args->original_price = removeHackTag(str_replace(",","",$obj->item_original_price));
		$args->price = removeHackTag(str_replace(",","",$obj->item_price));
		$args->item_condition = removeHackTag($obj->item_condition);

		$args->priority_area = removeHackTag($obj->priority_area);
		$args->product_name = removeHackTag($obj->item_name);
		$args->used_month = removeHackTag($obj->item_used_month);

		$args->delivery =  ($obj->item_delivery)? $obj->item_delivery : 'N';
		$args->direct_dealing = ($obj->item_direct_dealing)? $obj->item_direct_dealing : 'N';
		$args->safe_dealing = ($obj->item_safe_dealing)? $obj->item_safe_dealing : 'N';

		$output = executeQuery('marketplace.insertMarketplaceItemInfo', $args);

		// make thumbnail files valid
		if($output->toBool()) $oFileController->setFilesValid($args->thumbnails_srl);
		return $output;
	}

	private function _checkKeyword($document_srl)
	{
		global $lang;

		$obj = Context::getRequestVars();

		// Check Keywords in content and notice
		$oMarketplaceModel = getModel('marketplace');
		$keywords = $oMarketplaceModel->getAllKeywords($this->module_srl);

		foreach($keywords as $keyword) {
			if (stripos($obj->content, $keyword) !== false) {
				
				$args = new stdClass();
				$args->module_srl = $this->module_srl;
				$args->document_srl = $document_srl;
				$args->keyword = $keyword;
				executeQuery('marketplace.insertKeywordDocument', $args);

				$output = $oMarketplaceModel->getMemberListByKeyword($keyword, $this->module_srl);
				if($output->toBool() && $output->data) {
					foreach($output->data as $val) {
						//send message
						$oCommunicationController = getController('communication');
						$msg_title = sprintf($lang->msg_find_keyword_title, $keyword);
						$msg_content 
							= $lang->msg_find_keyword_content
							."<br /><a href=".getUrl('', 'mid', Context::get('mid'), 'document_srl', $document_srl).">".getUrl('', 'mid', Context::get('mid'), 'document_srl', $document_srl)."</a>";
						$oCommunicationController->sendMessage($val->member_srl, $val->member_srl, $msg_title, $msg_content, true);
					}
				}
			}
		}

	}

	function procMarketplaceAddContent()
	{
		// check grant
		if($this->module_info->module != "marketplace")
		{
			return new Object(-1, "msg_invalid_request");
		}

		if(!$this->grant->write_document)
		{
			return new Object(-1, 'msg_not_permitted');
		}
		$document_srl = Context::get('document_srl');

		$oDocumentController = getController('document');
		$oDocumentModel = getModel('document');

		$document = $oDocumentModel->getDocument($document_srl,false,false,array('content'));

		// update document content
		$obj = new stdClass();
		$obj->document_srl = $document_srl;
		$obj->content =		
			$document->get('content')
				."<br /><p><strong>[".sprintf(Context::getLang('add_content_time'),date('Y.m.d H:i:s',time()))."]</strong></p>"
				.Context::get('content');
		$output = executeQuery('marketplace.updateDocumentContent', $obj);

		// remove cache
		$this->removeItemCache($document_srl);

		global $lang;

		htmlHeader();
		alertScript($lang->success_registed);
		reload(true);
		closePopupScript();
		htmlFooter();
		Context::close();
		exit;
	}
	
	function procMarketplaceItemModify()
	{
		// check grant
		if($this->module_info->module != "marketplace")
		{
			return new Object(-1, "msg_invalid_request");
		}

		// 상품 수정 기능 옵션 체크
		if(!$this->module_info->item_modify || $this->module_info->item_modify =='N')
		{
			return new Object(-1, 'msg_invalid_request');
		}

		if(!$this->grant->write_document)
		{
			return new Object(-1, 'msg_not_permitted');
		}
		$logged_info = Context::get('logged_info');
		$document_srl = (int)Context::get('document_srl');

		// Update Category
		$obj->category_srl =  Context::get('category_srl');
		$obj->document_srl =  $document_srl;
		$oMarketplaceModel = getModel('marketplace');
		$oMarketItem = $oMarketplaceModel->getMarketplaceItem($obj->document_srl);

		if($obj->category_srl != $oMarketItem->get('category_srl'))
		{
			$output = executeQuery('marketplace.updateMarketplaceItemCategory', $obj);
		}

		// setup variables
		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;

		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->original_price = (int)str_replace(",","",$obj->item_original_price);
		$args->price = (int)str_replace(",","",$obj->item_price);
		$args->item_condition = $obj->item_condition;

		$args->priority_area = $obj->priority_area;
		$args->product_name = $obj->item_name;
		$args->used_month = (int)$obj->item_used_month;
			
		$args->delivery =  ($obj->item_delivery)? $obj->item_delivery : 'N';
		$args->direct_dealing = ($obj->item_direct_dealing)? $obj->item_direct_dealing : 'N';
		$args->safe_dealing = ($obj->item_safe_dealing)? $obj->item_safe_dealing : 'N';

		$output = executeQuery('marketplace.updateMarketplaceItemInfo', $args);
		
		// 제품 구분이 얻어옴
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getSettingConditions($this->module_srl);
		if(!$output->toBool())	return $output;
		foreach($output->data as $key => $val)
		{
			$condition_list[$val->eid] = $val;
		}

		// 기존 데이터와 비교하여 로그 남김
		if($this->module_info->item_modify == 'LOG')
		{
			$option_allow = Context::getLang('option_allow');
			foreach($args as $key => $val)
			{
				$org_val = $oMarketItem->get($key);
				if($org_val !== $val)
				{
					if(is_numeric($val)) $val = number_format($val);
					if(is_numeric($org_val)) $org_val = number_format($org_val);
					if($key == 'item_condition') 
					{
						$val = $condition_list[$val]->name;
						$org_val = $condition_list[$org_val]->name;
					}

					switch ($key) {
					case 'delivery':
					case 'direct_dealing':
					case 'safe_dealing':
						$_log .= Context::getLang($key)."가 <strong>{$option_allow[$val]}</strong>으로 변경되었습니다.<br />";
						break;
					case 'used_month' :
						$_log .= Context::getLang($key)."이 <strong>{$org_val}개월</strong>에서 <strong>{$val}개월</strong>로 변경되었습니다.<br />";
						break;
					default:
						$_log .= Context::getLang($key)."이 <strong>{$org_val}</strong>에서 <strong>{$val}</strong>으로 변경되었습니다.<br />";
					}
				}
			}
			// update document content
			if($_log)
			{
				$obj = new stdClass();
				$obj->document_srl = $document_srl;
				$obj->content =		
					$oMarketItem->get('content')
						."<br /><p><strong>[".sprintf(Context::getLang('add_content_time'),date('Y.m.d H:i:s',time()))."]</strong></p>"
						.$_log;
				$output = executeQuery('marketplace.updateDocumentContent', $obj);
			}
		}

		// remove cache
		$this->removeItemCache($document_srl);

		global $lang;

		htmlHeader();
		alertScript($lang->success_updated);
		reload(true);
		closePopupScript();
		htmlFooter();
		Context::close();
		exit;
	}


	/**
	 * @brief delete the document
	 **/
	function procMarketplaceDeleteDocument()
	{
		// get the document_srl
		$document_srl = Context::get('document_srl');

		// if the document is not existed
		if(!$document_srl)
		{
			return $this->doError('msg_invalid_document');
		}
		
		// 관리자가 아니면 삭제할 수 없음
		if($this->grant->manager==false)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);

		// generate document module controller object
		$oDocumentController = getController('document');

		// delete the document
		$output = $oDocumentController->deleteDocument($document_srl, $this->grant->manager);
		if(!$output->toBool())
		{
			return $output;
		}

		// remove cache
		$this->removeItemCache($document_srl);

		// alert an message
		$this->add('mid', Context::get('mid'));
		$this->add('page', $output->get('page'));
		$this->setMessage('success_deleted');
	}


	/**
	 * @brief insert comments
	 **/
	function procMarketplaceInsertComment()
	{
		// check grant
		if(!$this->grant->write_comment)
		{
			return new Object(-1, 'msg_not_permitted');
		}
		$logged_info = Context::get('logged_info');

		// get the relevant data for inserting comment
		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;

		if(!$this->module_info->use_status) $this->module_info->use_status = 'PUBLIC';
		if(!is_array($this->module_info->use_status))
		{
			$this->module_info->use_status = explode('|@|', $this->module_info->use_status);
		}

		if(in_array('SECRET', $this->module_info->use_status))
		{
			$this->module_info->secret = 'Y';
		}
		else
		{
			unset($obj->is_secret);
			$this->module_info->secret = 'N';
		}

		// check if the doument is existed
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		if(!$oDocument->isExists())
		{
			return new Object(-1,'msg_not_founded');
		}

		// generate comment  module model object
		$oCommentModel = getModel('comment');

		// generate comment module controller object
		$oCommentController = getController('comment');

		// check the comment is existed
		// if the comment is not existed, then generate a new sequence
		if(!$obj->comment_srl)
		{
			$obj->comment_srl = getNextSequence();
		} else {
			$comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
		}

		// if comment_srl is not existed, then insert the comment
		if($comment->comment_srl != $obj->comment_srl)
		{

			// parent_srl is existed
			if($obj->parent_srl)
			{
				$parent_comment = $oCommentModel->getComment($obj->parent_srl);
				if(!$parent_comment->comment_srl)
				{
					return new Object(-1, 'msg_invalid_request');
				}

				$output = $oCommentController->insertComment($obj);

			// parent_srl is not existed
			} else {
				$output = $oCommentController->insertComment($obj);
			}
		// update the comment if it is not existed
		} else {
			// check the grant
			if(!$comment->isGranted())
			{
				return new Object(-1,'msg_not_permitted');
			}

			$obj->parent_srl = $comment->parent_srl;
			$output = $oCommentController->updateComment($obj, $this->grant->manager);
			$comment_srl = $obj->comment_srl;
		}

		if(!$output->toBool())
		{
			return $output;
		}

		// alert a message
		$this->setMessage('success_registed');

		if (Context::get('success_return_url')){
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $obj->document_srl)."#comment_".$obj->comment_srl);
		}



	}

	/**
	 * @brief delete the comment
	 **/
	function procMarketplaceDeleteComment()
	{
		// get the comment_srl
		$comment_srl = Context::get('comment_srl');
		if(!$comment_srl)
		{
			return $this->doError('msg_invalid_request');
		}

		// generate comment  controller object
		$oCommentController = getController('comment');

		$output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
		if(!$output->toBool())
		{
			return $output;
		}

		$this->add('mid', Context::get('mid'));
		$this->add('page', Context::get('page'));
		$this->add('document_srl', $output->get('document_srl'));
		$this->setMessage('success_deleted');
	}


	/**
	 * @brief check the password for document and comment
	 **/
	function procMarketplaceVerificationPassword()
	{
		// get the id number of the document and the comment
		$password = Context::get('password');
		$document_srl = Context::get('document_srl');
		$comment_srl = Context::get('comment_srl');

		$oMemberModel = getModel('member');

		// if the comment exists
		if($comment_srl)
		{
			// get the comment information
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl);
			if(!$oComment->isExists())
			{
				return new Object(-1, 'msg_invalid_request');
			}

			// compare the comment password and the user input password
			if(!$oMemberModel->isValidPassword($oComment->get('password'),$password))
			{
				return new Object(-1, 'msg_invalid_password');
			}

			$oComment->setGrant();
		} else {
			 // get the document information
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument->isExists())
			{
				return new Object(-1, 'msg_invalid_request');
			}

			// compare the document password and the user input password
			if(!$oMemberModel->isValidPassword($oDocument->get('password'),$password))
			{
				return new Object(-1, 'msg_invalid_password');
			}

			$oDocument->setGrant();
		}
	}

	/**
	 * @brief manage(change) marketplace item's status
	 **/
	function procMarketplaceChangeStatus()
	{
		$type = Context::get('type');

		// Check login information
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$logged_info = Context::get('logged_info');

		// Check document information
		$document_srl = (int)Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');

		// Get Document Item
		$oMarketplaceModel = getModel('marketplace');
		$oMarketItem = $oMarketplaceModel->getMarketplaceItem($document_srl);
	
		if(!$oMarketItem->isGranted())
		{
			return new Object(-1,'msg_not_permitted');
		}

		$args->document_srl = $document_srl;
		$args->item_status = $type;
		$output = executeQuery('marketplace.updateMarketplaceItemStatus', $args);

		// remove cache
		$this->removeItemCache($document_srl);

		// Delete advertise
		$this->deleteAdvertise($document_srl);
		$this->setMessage('success_changed');

		if($type == 'cancel')
			$this->add('success_return_url',getAutoEncodedUrl('','mid',$this->mid,'act','dispMarketplaceItemManage','item_status','cancel'));
	}

	/**
	 * @brief Inert new advertise
	 **/
	function procMarketplaceInsertAdvertise()
	{	
		$oMarketplaceModel = getModel('marketplace');

		// Check login information
		if(!Context::get('is_logged')) return new Object(-1, 'msg_invalid_request');
		$logged_info = Context::get('logged_info');

		$document_srl = Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');

		$balance = Context::get('balance');
		$bid_price = Context::get('bid_price');
		if($bid_price > $balance) return new Object(-1,'입찰가는 최대 광고료보다 클 수 없습니다.');

		if($bid_price < $this->module_info->minimum_bid_price)
			return new Object(-1,'입찰가는 최저 입찰 금액보다 같거나 높게 설정하셔야 합니다.');

		$oPointModel = getModel('point');
		$member_point = $oPointModel->getPoint($logged_info->member_srl);

		if($member_point < $bid_price || $member_point < $balance)
		{
			return new Object(-1,'포인트가 부족하여 광고를 등록하실 수 없습니다.');
		}

		// 같은 입찰가의 광고가 진행중인지 체크
		$output = $oMarketplaceModel->getAdvertiseByBidPrice($bid_price, $this->module_srl);
		if($output->data && $output->data->document_srl != $document_srl) 
		{
			return new Object(-1,'해당 입찰가는 이미 등록되어있습니다.');
		}
	
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->bid_price = $bid_price;
		$args->balance = $balance;
		$args->module_srl = $this->module_srl;

		// update if exist
		if($oMarketplaceModel->isInsertedAdvertise($document_srl)) {
			$output = executeQuery('marketplace.updateMarketplaceAdvertise', $args);
		}
		// insert if not exist
		else {
			$output = executeQuery('marketplace.insertMarketplaceAdvertise', $args);
		}

		global $lang;
		htmlHeader();
		alertScript($lang->success_changed);
		closePopupScript();
		reload(true);
		htmlFooter();
		Context::close();
		exit;
	}
	
	function procMarketplaceDeleteAdvertise()
	{
		$document_srl = Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');

		$this->deleteAdvertise($document_srl);
		$this->setMessage('success_advertise_stopped');
	}

	function procMarketplaceActionRecord()
	{
		$mid = Context::get('mid');
		$document_srl = Context::get('document_srl');
		$logged_info = Context::get('logged_info');
		$pass = false;

		// 광고 정보를 얻어옴
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getAdvertise($document_srl);
		if(!$output->toBool()) return $output;
		$advertise_info = $output->data;

		// 광고주 포인트 정보를 얻어옴
		$oPointModel = getModel('point');
		$member_point = $oPointModel->getPoint($advertise_info->member_srl);

		// 광고주의 포인트가 부족할 경우 광고 제거
		if($member_point < $advertise_info->bid_price)
		{
			$output = $this->deleteAdvertise($document_srl);
		}
		if(!$output->toBool()) return $output;

		// 광고주의 Balance가 모두 소진되었을 경우 광고 제거
		$output = $oMarketplaceModel->getAdvertise($document_srl);
		if(!$output->toBool()) return $output;

		$left_balance = $output->data->balance;
		if($left_balance < 0 || $left_balance < $advertise_info->bid_price)
		{
			$output = $this->deleteAdvertise($document_srl);
		}

		// 글 보기 권한 체크 (권한이 없으면 패스)
		$oModuleModel = getModel('module');
		$grant = $oModuleModel->getGrant($oModuleModel->getModuleInfoByModuleSrl($this->module_srl), $logged_info);
		if(!$grant->view) $pass = true;

		// 크롤러 및 광고주 체크 (비과금 사용자가 접근 시 패스)
		if($advertise_info->member_srl == $logged_info->member_srl) $pass = true;
		if(isCrawler()) $pass = true;

		// 광고비 비회원 미적용으로 설정된 경우 비회원 패스
		if($this->module_info->advertise_guest != 'Y' && !$logged_info) $pass = true;

		if($pass)
		{
			$url = getNotEncodedUrl('','mid',$mid,'document_srl',$document_srl);	
			header("Location: ".$url); exit; 
		}

		// 가장 최근 클릭정보를 얻어옴
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->member_srl = $logged_info->member_srl;
		if(!$logged_info) $args->ipaddress = $_SERVER['REMOTE_ADDR'];
		$output = $oMarketplaceModel->getAdvertiseLogLatestOne($args);

		$time_gap = floor(($_SERVER['REQUEST_TIME'] + zgap() - ztime($output->data->regdate))/60);
		$time_interval = ($this->module_info->advertise_interval)? $this->module_info->advertise_interval : 360;

		// 기본 6시간(360분) 기준
		if($time_gap > $time_interval) {

			// Insert Action
			$args = new stdClass();
			$args->module_srl = $this->module_srl;
			$args->document_srl = $document_srl;
			$args->member_srl = $logged_info->member_srl;
			$args->action = "click";
			$args->charging = $advertise_info->bid_price*-1;
			$output = executeQuery('marketplace.insertAdvertiseLog', $args);
			if(!$output->toBool()) return $output;

			// Update Advertise
			$args = new stdClass();
			$args->document_srl = $document_srl;
			$args->balance = $advertise_info->balance - $advertise_info->bid_price;
			$args->regdate = $advertise_info->regdate;
			$output = executeQuery('marketplace.updateMarketplaceAdvertise', $args);
			if(!$output->toBool()) return $output;

			// Set Point
			$oPointController = getController('point');
			$oPointController->setPoint($advertise_info->member_srl, $advertise_info->bid_price, 'minus');	
		}

		// redirect to market item
		$url = getNotEncodedUrl('','mid',$mid,'document_srl',$document_srl);	
		header("Location: ".$url); exit; 

	}

	function deleteAdvertise($document_srl)
	{
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$output = executeQuery('marketplace.deleteAdvertise', $args);
		return $output;
	}


	/**
	 * @brief Reinsert Marketplace Item 
	 **/
	function procMarketplaceReinsertDocument()
	{
		$document_srl = Context::get('document_srl');
		if(!$document_srl) return new Object(-1,'msg_invalid_request');
		
		// 재등록 기능 사용여부 체크
		if(!$this->module_info->use_reinsert) return new Object(-1,'msg_invalid_request');

		// Get Marketplace Item
		$oMarketplaceModel = getModel('marketplace');
		$oMarketItem = $oMarketplaceModel->getMarketplaceItem($document_srl);
	
		// 권한 체크
		if(!$oMarketItem->isGranted()) return new Object(-1,'msg_not_permitted');
		
		// 판매중인 상품이 아니라면 return
		if(!$oMarketItem->isSelling()) return new Object(-1,'msg_invalid_request');

		// set defualt interval if no setting
		$interval = ($this->module_info->reinsert_interval)? $this->module_info->reinsert_interval : 5;
		
		if(!$oMarketItem->get('reinsert_date'))
			$last_date = $oMarketItem->get('regdate');
		else $last_date = $oMarketItem->get('reinsert_date');

		$now = $_SERVER['REQUEST_TIME'];
		$limit_time = strtotime($interval.' days', strtotime($last_date));

		if($limit_time <= $now)
		{
			$obj = new stdClass();
			$obj->document_srl = $document_srl;
			$obj->update_order = $obj->list_order = (getNextSequence() * -1);
			$obj->regdate = $oMarketItem->get('regdate');
			$output = executeQuery('document.updateDocumentOrder', $obj);
			$output = executeQuery('marketplace.updateMarketplaceItemReinsertDate', $obj);

			$this->setMessage('success_reinsert');
		}
		else
		{
			$left_date = date("Y-m-d H:i",$limit_time);
			$this->setMessage(sprintf(Context::getLang('guide_reinsert'),$left_date,$interval));
		}
	}


	/**
	 * @brief Inert new keyword
	 **/
	function procMarketplaceInsertKeyword()
	{
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');

		$logged_info = Context::get('logged_info');
		$keyword = Context::get('keyword');
		$keyword = trim(removeHackTag($keyword));

		// get member keywords
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getKeywordsByMemberSrl($logged_info->member_srl, $this->module_srl);
		if(!$output->toBool()) return new Object(-1, $output->message);

		// limit keyword insert
		$limit_count = $this->module_info->limit_keyword_count;
		if($limit_count && count($output->data)>=$limit_count) {
			$msg_code = 'msg_cannot_insert_anymore';
		}
		else
		{
			// check exist
			$output = $oMarketplaceModel->getKeywordByMemberSrl($keyword, $logged_info->member_srl, $this->module_srl);
			if($output->data) return new Object(-1,'msg_already_exist_keyword');

			// DB insert
			$args = new stdClass();
			$args->module_srl = $this->module_srl;
			$args->member_srl = $logged_info->member_srl;
			$args->keyword = $keyword;
			$output = executeQuery('marketplace.insertKeywordMember', $args);
			if(!$output->toBool()) return new Object(-1, $output->message);

			$oCacheHandler = CacheHandler::getInstance('object');
			if($oCacheHandler->isSupport())
			{
				$object_key = 'keyword_list:' . $this->module_srl;
				$cache_key = $oCacheHandler->getGroupKey('marketplace', $object_key);
				$oCacheHandler->delete($cache_key);
			}
			$msg_code = 'success_registed';
		}

		// alert a message
		$this->setMessage($msg_code);

		// Redirect
		if (Context::get('success_return_url')){
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispMarketplaceKeywordManage'));
		}
	}

	function procMarketplaceDeleteKeyword()
	{
		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');

		$logged_info = Context::get('logged_info');
		$keyword = Context::get('keyword');

		// DB delete
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->keyword = $keyword;
		$output = executeQuery('marketplace.deleteKeywordMember', $args);
		if(!$output->toBool()) return $output;

		// 키워드가 존재하는지 확인 후 없으면 문서도 제거
		$args = new stdClass();
		$args->keyword = $keyword;
		$output = executeQuery('marketplace.getKeywordCount', $args);
		if(!$output->toBool()) return $output;
		if(!$output->data->count)
		{
			// DB delete
			$args = new stdClass();
			$args->keyword = $keyword;
			$output = executeQuery('marketplace.deleteKeywordDocumentByKeyword', $args);
			if(!$output->toBool()) return $output;
		}
		$this->setMessage('success_deleted');
	}

	function triggerDeleteMarketplaceItem(&$obj)
	{
		// marketplace 모듈 문서인지 체크
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($obj->module_srl, array('module'));
		if($module_info->module != 'marketplace') return new Object();

		// get marketplace item
		$args->document_srl = $obj->document_srl;
		$output = executeQuery('marketplace.getMarketplaceItemOnly', $args);
		if(!$output->toBool()) return $output;
		$target_srl = $output->data->thumbnails_srl;

		// delete file
		$oFileController = getController('file');
		$output = $oFileController->deleteFiles($target_srl);
		if(!$output->toBool()) return $output;

		// delete item
		$args->document_srl = $obj->document_srl;
		$output = executeQuery('marketplace.deleteMarketplaceItem', $args);
		if(!$output->toBool()) return $output;

		// delete advertise
		$this->deleteAdvertise($obj->document_srl);
		if(!$output->toBool()) return $output;

		// delete keyword
		$args->document_srl = $obj->document_srl;
		$output = executeQuery('marketplace.deleteKeywordDocument', $args);
		if(!$output->toBool()) return $output;

		// delete cache
		$this->removeItemCache($obj->document_srl);

		return new Object();
	}

	function procMarketplaceToggleWishlist()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) {
			$this->setMessage('로그인이 필요합니다.');
			return false;
		}
		$document_srl = Context::get('document_srl');
		if(!$document_srl) return new Object(-1, 'msg_invalid_request');

		// get wishlist
		$args->document_srl = $document_srl;
		$args->member_srl = $logged_info->member_srl;
		$oMarketplaceModel = getModel('marketplace');
		$output = $oMarketplaceModel->getWishlistItem($args);
		if(!$output->toBool()) return $output;

		// if exist delete wish item
		if($output->data) 
		{
			// delete wishlist
			$args->document_srl = $document_srl;
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery('marketplace.deleteWishlistItem', $args);
			if(!$output->toBool()) return $output;
			$this->setMessage('관심 목록에서 제거하였습니다.');
			return;
		}

		// insert wishlist
		$args->document_srl = $document_srl;
		$args->member_srl = $logged_info->member_srl;
		$output = executeQuery('marketplace.insertWishlist', $args);
		if(!$output->toBool()) return $output;

		$this->setMessage('이 상품을 관심목록에 등록(찜)하였습니다.');
	}


	function deleteItemCondition($module_srl, $eid = null)
	{
		if(!$module_srl) return new Object(-1,'msg_invalid_request');
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		if(!is_null($eid)) 
		{
			$oMarketplaceModel = getModel('marketplace');
			$output = $oMarketplaceModel->getSettingCondition($module_srl, $eid);
			$obj->idx = $output->data->idx;
			$obj->eid = $eid;
		}

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $oDB->executeQuery('marketplace.deleteSettingCondition', $obj);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if($eid != NULL)
		{
			$output = $oDB->executeQuery('marketplace.updateSettingConditionIdxOrder', $obj);
			if(!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}
		$oDB->commit();

		return new Object();
	}

	function insertSettingCondition($obj)
	{
		//insert if not exist
		$output = executeQuery('marketplace.getSettingConditionMaxIdx', $obj);
		if(!$output->toBool())	return $output;

		$obj->idx = intval($output->data->idx) + 1;
		$output = executeQuery('marketplace.insertSettingCondition', $obj);
		if(!$output->toBool())	return $output;
	}

	function removeItemCache($document_srl)
	{
		//remove from cache
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$cache_key = 'document_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);

			$cache_key = 'marketplace_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
	}

}
