/**
 * @file   modules/marketplace/js/marketplace.js
 * @author NHN (developers@xpressengine.com)
 * @brief  marketplace 모듈의 javascript
 **/

/* delete the document */
function completeDeleteDocument(ret_obj)
{
	var error = ret_obj.error;
	var message = ret_obj.message;
	var mid = ret_obj.mid;
	var page = ret_obj.page;

	var url = current_url.setQuery('mid',mid).setQuery('act','').setQuery('document_srl','');
	if(page) url = url.setQuery('page',page);

	//alert(message);

	location.href = url;
}

// current page reload
function completeReload(ret_obj)
{
	var error = ret_obj.error;
	var message = ret_obj.message;

	location.href = location.href;
}

/* delete the comment */
function completeDeleteComment(ret_obj)
{
	var error = ret_obj.error;
	var message = ret_obj.message;
	var mid = ret_obj.mid;
	var document_srl = ret_obj.document_srl;
	var page = ret_obj.page;

	var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','');
	if(page) url = url.setQuery('page',page);

	//alert(message);

	location.href = url;
}

/* change category */
function doChangeCategory()
{
	var category_srl = jQuery('#marketplace_category option:selected').val();
	location.href = decodeURI(current_url).setQuery('category',category_srl).setQuery('page', '');
}


function getSellerContact(document_srl, selector) {
	if (!jQuery(selector).is(':visible') )
	{
		jQuery.exec_json(
			"marketplace.getMarketplaceContactNumber",
			{document_srl:document_srl }, 
			function(data)
			{
				jQuery(selector).find('.response').text(data.mobile);
				jQuery(selector).show();
			}
		);
	}
	else jQuery(selector).hide();
}

/* scrap */
function doToggleWishItem(document_srl)
{
	var params = [];
	var responses = [ 'error', 'message', 'document_srl' ];

	params.document_srl = document_srl;
	exec_xml('marketplace','procMarketplaceToggleWishlist', params, 
		function(ret_obj){
			alert(ret_obj.message);
			location.reload();
		}, responses);
}

function doChangeItemStatus(document_srl, type)
{
	var params = [];
	var message;

	if(type == 'soldout') message = xe.lang.ask_change_soldout;
	else if(type == 'cancel') message = xe.lang.ask_change_cancel;
	else if(type == 'selling') message = xe.lang.ask_change_selling;
	else return;

    if(confirm(message)==false) return;

	params.document_srl = document_srl;
	params.type = type;

	exec_xml('marketplace','procMarketplaceChangeStatus', params, 
		function(ret_obj){
			alert(ret_obj.message);
			location.reload();
		});
}

function doStopItemSelling(document_srl)
{
	var params = [];
	var response_tags = ["error","message","success_return_url"];

	params.document_srl = document_srl;
	params.type = 'cancel';

    if(confirm(xe.lang.ask_change_cancel)==false) return;

	exec_xml('marketplace','procMarketplaceChangeStatus', params, 
		function(ret_obj){
			alert(ret_obj.message);
			location.href = ret_obj.success_return_url;
		}, response_tags);
}

function doStopAdvertise(document_srl)
{
	var params = [];
	params.document_srl = document_srl;

	exec_xml('marketplace','procMarketplaceDeleteAdvertise', params, 
		function(ret_obj){
			alert(ret_obj.message);
			location.reload();
		});
}

function doItemReinsert(document_srl)
{
	var params = [];
	params.document_srl = document_srl;

	exec_xml('marketplace','procMarketplaceReinsertDocument', params, 
		function(ret_obj){
			alert(ret_obj.message);
			//location.reload();
		});
}

function deleteKeyword(keyword) {

	var params = [];
	params.keyword = keyword;

	exec_xml('marketplace','procMarketplaceDeleteKeyword', params, 
		function(ret_obj){
			alert(ret_obj.message);
			location.reload();
		});
}

function int_to_han(arg) {
   var int_to_han_unit0 = new Array('','일','이','삼','사','오','육','칠','팔','구');
   var int_to_han_unit1 = new Array('','십','백','천');
   var int_to_han_unit2 = new Array('','만 ','억','조','경','해','자','양','구','간','정','재','극','항하사','아승기','나유타','불가사의','무량대수');

   var han_result = "";
   var han_temp;
   var int_unit;

   var tmp,i,j,k;

   for(i=0,k=0;k<arg.length;i++){
		  han_temp = "";
		  for(j=0;j<4 && k<arg.length;j++,k++){
				 int_unit = arg.substr(arg.length-(k+1),1);
				 // int_to_han_unit1 의 단위 추가
				 if(int_unit > 0){
						tmp = k%4;
						han_temp = int_to_han_unit1[tmp] + han_temp;
				 }
				 // int_to_han_unit0 의 단위 추가
				 if(int_unit==1){
						if(j==0)han_temp = int_to_han_unit0[int_unit] + han_temp;
				 }else{
						han_temp = int_to_han_unit0[int_unit] + han_temp;
				 }
		  }
		  // int_to_han_unit2 의 단위 추가
		  if(han_temp != ""){
				 if(arg.length == 5 && han_temp == "일"){
						han_result = int_to_han_unit2[i]+ han_result;
				 }else{
						han_result = han_temp +int_to_han_unit2[i]+ han_result;
				 }
		  }
   }
   return han_result;
}