VuFind.Lists = (function(){
	return {
		addToHomePage: function(listId){
			VuFind.Account.ajaxLightbox('/MyAccount/AJAX?method=getAddBrowseCategoryFromListForm&listId=' + listId, true);
			return false;
		},

		editListAction: function (){
			$('#listDescription,#listTitle,#FavEdit').hide();
			$('#listEditControls,#FavSave').show();
			return false;
		},
		//editListAction: function (){
		//	$('#listDescription').hide();
		//	$('#listTitle').hide();
		//	$('#listEditControls').show();
		//	$('#FavEdit').hide();
		//	$('#FavSave').show();
		//	return false;
		//},

		submitListForm: function(action){
			$('#myListActionHead').val(action);
			$('#myListFormHead').submit();
			return false;
		},

		makeListPublicAction: function (){
			return this.submitListForm('makePublic');
		},

		makeListPrivateAction: function (){
			return this.submitListForm('makePrivate');
		},

		deleteListAction: function (){
			if (confirm("Are you sure you want to delete this list?")){
				this.submitListForm('deleteList');
			}
			return false;
		},

		updateListAction: function (){
			return this.submitListForm('saveList');
		},

		deleteAllListItemsAction: function (){
			if (confirm("Are you sure you want to delete all titles from this list?  This cannot be undone.")){
				this.submitListForm('deleteAll');
			}
			return false;
		},

		emailListAction: function (listId) {
			var urlToDisplay = '/MyAccount/AJAX';
			VuFind.loadingMessage();
			$.getJSON(urlToDisplay, {
					method  : 'getEmailMyListForm'
					,listId : listId
				},
					function(data){
						VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
			});
			return false;
		},

		SendMyListEmail: function () {
			var url = "/MyAccount/AJAX";

			$.getJSON(url,
				{ // form inputs passed as data
					listId   : $('#emailListForm input[name="listId"]').val()
					,to      : $('#emailListForm input[name="to"]').val()
					,from    : $('#emailListForm input[name="from"]').val()
					,message : $('#emailListForm textarea[name="message"]').val()
					,method  : 'sendMyListEmail' // serverside method
				},
				function(data) {
					if (data.result) {
						VuFind.showMessage("Success", data.message);
					} else {
						VuFind.showMessage("Error", data.message);
					}
				}
			);
		},

		citeListAction: function (id) {
			return VuFind.Account.ajaxLightbox('/MyAccount/AJAX?method=getCitationFormatsForm&listId=' + id, false);
			//return false;
			//TODO: ajax call not working
		},

		processCiteListForm: function(){
			$("#citeListForm").submit();
		},

		batchAddToListAction: function (id){
			return VuFind.Account.ajaxLightbox('/MyAccount/AJAX/?method=getBulkAddToListForm&listId=' + id);
			//return false;
		},

		processBulkAddForm: function(){
			$("#bulkAddToList").submit();
		},

		changeList: function (){
			var availableLists = $("#availableLists");
			window.location = "/MyAccount/MyList/" + availableLists.val();
		},

		printListAction: function (){
			window.print();
			return false;
		},

		importListsFromClassic: function (){
			if (confirm("This will import any lists you had defined in the old catalog.  This may take several minutes depending on the size of your lists. Are you sure you want to continue?")){
				window.location = "/MyAccount/ImportListsFromClassic";
			}
			return false;
		}//,

		//setDefaultSort: function(selectedElement, selectedValue) {
		//	$('#default-sort').val(selectedValue);
		//	$('#default-sort + div>ul li').css('background-color', 'inherit');
		//	$(selectedElement).css('background-color', 'gray');
		//}
	};
}(VuFind.Lists || {}));