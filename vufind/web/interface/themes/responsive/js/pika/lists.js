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

		submitToLists: function (action, data){
			$('#myListActionHead').val(action);
			$('#myListFormHead').submit();
			return false;
		},

		submitListForm: function(action, page, pageSize, sort){
			$('#myListActionHead').val(action);
			$('#myListPage').val(page);
			$('#myListPageSize').val(pageSize);
			$('#myListSort').val(sort);
			$('#myListFormHead').submit();
			return false;
		},

		submitListFormWithData: function(action, data,page, pageSize, sort){
			$('#myListActionHead').val(action);
			$('#myListActionData').val(data);
			$('#myListPage').val(page);
			$('#myListPageSize').val(pageSize);
			$('#myListSort').val(sort);
			$('#myListFormHead').submit();
			return false;
		},

		makeListPublicAction: function (page, pageSize, sort){
			return this.submitListForm('makePublic', page, pageSize, sort);
		},

		makeListPrivateAction: function (page, pageSize, sort){
			return this.submitListForm('makePrivate', page, pageSize, sort);
		},

		deleteAllListItemsAction: function (page, pageSize, sort){
			Pika.confirm("<p class='alert alert-warning'>Are you sure you want to <strong>delete all titles</strong> in this list?  <strong>This cannot be undone.</strong></p>", function (){
				Pika.Lists.submitListForm('deleteAll', page, pageSize, sort);
			});
			return false;
		},

		deleteListAction: function (page, pageSize, sort){
			Pika.confirm("<p class='alert alert-danger'>Are you sure you want to delete this list?  <strong>This cannot be undone.</strong></p>", function (){
				Pika.Lists.submitListForm('deleteList', page, pageSize, sort);
			});
			return false;
		},

		buttonAjaxHandler: function(ajaxMethod, id, command) {
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/MyAccount/AJAX?method=" + ajaxMethod + "&id=" + id;
				if (command !== undefined){
					url += "&command=" + command;
				}
				$.getJSON(url, function (data) {
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		checkUser: function (id){
			var url = "/MyAccount/AJAX?method=isStaffUser&barcode=" + id;
			$.getJSON(url, function (data){
				if ($("#barcode").val().length > 0){
					let validation = $("#validation");
					validation.show();
					if (data.isStaff == true){
						validation.html("<span style='color:green;'>Valid Barcode</span>");
						$("#transfer").prop('disabled', false);
					}else{
						validation.html("<span style='color:darkred;'>Invalid Barcode</span>");
						$("#transfer").prop('disabled', true);
					}
				}
			});

			return false;
		},

		updateListAction: function (page, pageSize, sort){
			console.log("page:" + page + ", pageSize:" + pageSize + ", sort:" + sort);
			return this.submitListForm('saveList', page, pageSize, sort);
		},

		// No references to the method found. 1/19/23
		// clearSelectedList: function (){
		// 	var ids = Array(),
		// 			idStr = $('#myListActionData').val();
		// 	if(idStr.length >= 2){
		// 		var pos = idStr.lastIndexOf(',');
		// 		idStr = idStr.substring(0,pos);
		// 		ids = idStr.split(",");
		// 		var x = ids.length,
		// 				list = " list";
		// 		if(x !== 1){list = " lists";}
		// 		if(confirm("Are you sure you want to remove all items from " + x + list + "? This cannot be undone.")){
		// 			this.submitToLists("clearSelectedLists");
		// 		}
		// 	}else{
		// 		Pika.showMessage("Error","Please select a list to clear.");
		// 		return false;
		// 	}
		// },

		deleteSelectedList: function (){
			var ids = Array(),
			 idStr = $('#myListActionData').val();
			if(idStr.length >= 2){
				var pos = idStr.lastIndexOf(',');
				idStr = idStr.substring(0,pos);
				ids = idStr.split(",");
				var x = ids.length,
				 list = " list";
				if(x !== 1){list = " lists";}
				Pika.confirm("<p class='alert alert-danger'>Are you sure you want to delete <strong>" + x + "</strong>" + list + "? <strong>This cannot be undone.</strong></p>", function () {
					return Pika.Lists.submitToLists("deleteSelectedLists");
				});
				return false;
			}else{
				Pika.showMessage("Error","Please select a list to delete.");
				return false;
			}
		},

		deleteListItems: function (ids, page, pageSize, sort){
			var x = ids.length;
			if (x > 0){
				var title = (x === 1) ? " title" : " titles";
				if (confirm("Are you sure you want to delete " + x + title + " from this list? This cannot be undone.")){
					var markedTitles = [];
					$.each(ids, function (key, val){
						markedTitles.push(val.value);
					});
					var stringReturn = markedTitles.join(",");
					this.submitListFormWithData('deleteMarked', stringReturn, page, pageSize, sort);
				}
			}else{
				Pika.showMessage('No titles selected', 'No titles have been selected.', true);
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
		exportListAction: function (id, page, pageSize, sort){
			return this.submitListForm('exportToExcel', page, pageSize, sort);
		},

		exportListFromLists: function (id){
			$('#myListActionHead').val("exportToExcel");
			$('#myListActionData').val(id);
			$('#myListFormHead').submit();
			return false;
		},

		citeListAction: function (id, page, pageSize, sort) {
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX?method=getCitationFormatsForm&listId=' + id + "&page=" + page + "&pagesize=" + pageSize + "&sort=" +sort, false);
		},

		processCiteListForm: function(page, pageSize, sort){
			$('#myListpage').val(page);
			$('#myListPageSize').val(pageSize);
			$('#myListsort').val(sort);
			$("#citeListForm").submit();
		},

		batchAddToListAction: function (id){
			return Pika.Account.ajaxLightbox('/MyAccount/AJAX/?method=getBulkAddToListForm&listId=' + id);
		},

		transferListToUser: function(id){
			return this.buttonAjaxHandler('transferListToUser', id, 'transferList');
		},

		transferList: function (id, user){
			if (confirm("Are you sure you want to transfer this list. It will no longer be accessible from this account.")) {
				Pika.Account.ajaxLogin(function () {
					Pika.loadingMessage();
					var url = "/MyAccount/AJAX?method=transferList&id=" + id + "&barcode=" + user;
					$.getJSON(url, function (data) {
						Pika.showMessage(data.title, data.body, 1, 1);
					}).fail(Pika.ajaxFail);
				});
			}
			return false;
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

		copyList: function(id){
			if (confirm("You are copying this list and all items to your lists. This could take a several moments depending on the size of the list. Are you sure you want to continue?"))
			{
				Pika.Account.ajaxLogin(function(){
					Pika.loadingMessage();
					var url = "/MyAccount/AJAX?method=copyList&copyFromId=" + id;
					$.getJSON(url, function(data){
						Pika.showMessage(data.title, data.body, 1,1);
					}).fail(Pika.ajaxFail);
				});
			}
			return false;
		},

		importListsFromClassic: function (){
			Pika.Account.ajaxLogin(function(){
				Pika.confirm("This will import any lists you had defined in the old catalog.  This may take several minutes depending on the size of your lists. Are you sure you want to continue?", function(){
					window.location = "/MyAccount/ImportListsFromClassic";
				});
			});
			return false;
		}//,

		//setDefaultSort: function(selectedElement, selectedValue) {
		//	$('#default-sort').val(selectedValue);
		//	$('#default-sort + div>ul li').css('background-color', 'inherit');
		//	$(selectedElement).css('background-color', 'gray');
		//}
	};
}(Pika.Lists || {}));