Pika.Lists = (function(){
	return {
		addToHomePage: function(listId){
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX?method=getAddBrowseCategoryFromListForm&listId=' + listId, true);
		},

		editListAction: function (){
			$('#listDescription,#listTitle,#FavEdit').hide();
			$('#listEditControls,#FavSave').show();
			return false;
		},

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
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX?method=getEmailMyListForm&listId=' + listId, false);
		},

		SendMyListEmail: function () {
			var url = "/MyAccount/AJAX";
					$.getJSON(url,
				{ // form inputs passed as data
					listId   : $('#emailListForm input[name="listId"]').val()
					,to      : $('#emailListForm input[name="to"]').val()
					,from    : $('#emailListForm input[name="from"]').val()
					,message : $('#emailListForm textarea[name="message"]').val()
					,method  : 'sendMyListEmail' // server-side method
					,'g-recaptcha-response' : (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : false
				},
				function(data) {
					if (data.result) {
						Pika.showMessage("Success", data.message);
					} else {
						Pika.showMessage("Error", data.message);
					}
				}
			);
		},
		//Exports list to Excel
		exportListAction: function (id){
			return this.submitListForm('exportToExcel');
		},
		citeListAction: function (id) {
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX?method=getCitationFormatsForm&listId=' + id, false);
		},

		processCiteListForm: function(){
			$("#citeListForm").submit();
		},

		batchAddToListAction: function (id){
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX/?method=getBulkAddToListForm&listId=' + id);
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
}(Pika.Lists || {}));