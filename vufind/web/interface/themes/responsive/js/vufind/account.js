/**
 * Created by mark on 1/14/14.
 */
VuFind.Account = (function(){

	return {
		ajaxCallback: null,
		closeModalOnAjaxSuccess: false,
		showCovers: null,

		addAccountLink: function(){
			var url = "/MyAccount/AJAX?method=getAddAccountLinkForm";
			VuFind.Account.ajaxLightbox(url, true);
		},

		/**
		 * Creates a new list in the system for the active user.
		 *
		 * Called from list-form.tpl
		 * @returns {boolean}
		 */
		addList: function(){
			var form = $("#addListForm"),
					isPublic = form.find("#public").prop("checked"),
					groupedWorkId = form.find("input[name=groupedWorkId]").val(),
					title = form.find("input[name=title]").val(),
					desc = $("#listDesc").val(),
					url = "/MyAccount/AJAX",
					params = {
						'method': 'AddList',
						title: title,
						public: isPublic,
						desc: desc,
						groupedWorkIdId: groupedWorkId
					};
			$.getJSON(url, params,function (data) {
					if (data.success) {
						VuFind.showMessage("Added Successfully", data.message, true, true);
					} else {
						VuFind.showMessage("Error", data.message);
					}
			}).fail(VuFind.ajaxFail);
			return false;
		},

		/**
		 * Do an ajax process, but only if the user is logged in.
		 * If the user is not logged in, force them to login and then do the process.
		 * Can also be called without the ajax callback to just login and not go anywhere
		 *
		 * @param trigger
		 * @param ajaxCallback
		 * @param closeModalOnAjaxSuccess
		 * @returns {boolean}
		 */
		ajaxLogin: function (ajaxCallback, trigger, closeModalOnAjaxSuccess) {
			if (Globals.loggedIn) {
				if (ajaxCallback !== undefined && typeof(ajaxCallback) === "function") {
					ajaxCallback();
				} else if (VuFind.Account.ajaxCallback != null && typeof(VuFind.Account.ajaxCallback) === "function") {
					VuFind.Account.ajaxCallback();
					VuFind.Account.ajaxCallback = null;
				}
			} else {
				var multistep = false,
						loginLink = false;
				if (ajaxCallback !== undefined && typeof(ajaxCallback) === "function") {
					multistep = true;
				}
				VuFind.Account.ajaxCallback = ajaxCallback;
				VuFind.Account.closeModalOnAjaxSuccess = closeModalOnAjaxSuccess;
				if (trigger !== undefined && trigger != null) {
					var dialogTitle = trigger.attr("title") ? trigger.attr("title") : trigger.data("title");
					loginLink = trigger.data('login');
					/*
					  Set the trigger html element attribute data-login="true" to cause the pop-up login dialog
					  to act as if the only action is login, ie not a multi-step process.

					 */
				}
				var dialogDestination = '/MyAccount/AJAX?method=LoginForm';
				if (multistep && !loginLink){
					dialogDestination += "&multistep=true";
				}
				var modalDialog = $("#modalDialog");
				$('.modal-body').html("Loading...");
				$(".modal-content").load(dialogDestination);
				$(".modal-title").text(dialogTitle);
				modalDialog.modal("show");
			}
			return false;
		},

		followLinkIfLoggedIn: function (trigger, linkDestination) {
			if (trigger === undefined) {
				alert("You must provide the trigger to follow a link after logging in.");
			}
			var jqTrigger = $(trigger);
			if (linkDestination === undefined) {
				linkDestination = jqTrigger.attr("href");
			}
			this.ajaxLogin( function () {
				document.location = linkDestination;
			}, jqTrigger, true);
			return false;
		},

		loadMenuData: function (){
			var url = "/MyAccount/AJAX?method=getMenuData&activeModule=" + Globals.activeModule + '&activeAction=' + Globals.activeAction;
			$.getJSON(url, function(data){
				$("#lists-placeholder").html(data.lists);
				$(".checkouts-placeholder").html(data.checkouts);
				$(".holds-placeholder").html(data.holds);
				$(".readingHistory-placeholder").html(data.readingHistory);
				$(".materialsRequests-placeholder").html(data.materialsRequests);
				$(".bookings-placeholder").html(data.bookings);
				$("#availableHoldsNotice-placeHolder").html(data.availableHoldsNotice);
				$(".expirationFinesNotice-placeholder").html(data.expirationFinesNotice);
				$("#tagsMenu-placeholder").html(data.tagsMenu);
			});
			return false;
		},

		preProcessLogin: function (){
			var username = $("#username").val(),
				password = $("#password").val(),
				loginErrorElem = $('#loginError');
			if (!username || !password) {
				loginErrorElem
						.text($("#missingLoginPrompt").text())
						.show();
				return false;
			}
			if (VuFind.hasLocalStorage()){
				var rememberMe = $("#rememberMe").prop('checked'),
						showPwd = $('#showPwd').prop('checked');
				if (rememberMe){
					window.localStorage.setItem('lastUserName', username);
					window.localStorage.setItem('lastPwd', password);
					window.localStorage.setItem('showPwd', showPwd);
					window.localStorage.setItem('rememberMe', rememberMe);
				}else{
					window.localStorage.removeItem('lastUserName');
					window.localStorage.removeItem('lastPwd');
					window.localStorage.removeItem('showPwd');
					window.localStorage.removeItem('rememberMe');
				}
			}
			return true;
		},

		processAjaxLogin: function (ajaxCallback) {
			if(this.preProcessLogin()) {
				var username = $("#username").val(),
						password = $("#password").val(),
						rememberMe = $("#rememberMe").prop('checked'),
						loginErrorElem = $('#loginError'),
						loadingElem = $('#loading'),
						url = "/AJAX/JSON?method=loginUser",
						params = {username: username, password: password, rememberMe: rememberMe};
				if (!Globals.opac && VuFind.hasLocalStorage()){
					var showCovers = window.localStorage.getItem('showCovers') || false;
					if (showCovers && showCovers.length > 0) { // if there is a set value, pass it back with the login info
						params.showCovers = showCovers
					}
				}
				loginErrorElem.hide();
				loadingElem.show();
				//VuFind.loadingMessage();
				$.post(url, params, function(response){
							loadingElem.hide();
							if (response.result.success == true) {
								// Hide "log in" options and show "log out" options:
								$('.loginOptions, #loginOptions').hide();
								$('.logoutOptions, #logoutOptions').show();

								// Show user name on page in case page doesn't reload
								var name = $.trim(response.result.name);
								//name = 'Logged In As ' + name.slice(0, name.lastIndexOf(' ') + 2) + '.';
								name = 'Logged In As ' + name.slice(0, 1) + '. ' + name.slice(name.lastIndexOf(' ') + 1, name.length) + '.';
								$('#side-bar #myAccountNameLink').html(name);

								if (VuFind.Account.closeModalOnAjaxSuccess) {
									VuFind.closeLightbox();
								}

								Globals.loggedIn = true;
								if (ajaxCallback !== undefined && typeof(ajaxCallback) === "function") {
									ajaxCallback();
								} else if (VuFind.Account.ajaxCallback !== undefined && typeof(VuFind.Account.ajaxCallback) === "function") {
									VuFind.Account.ajaxCallback();
									VuFind.Account.ajaxCallback = null;
								}
							} else {
								loginErrorElem.text(response.result.message).show();
							}
						}, 'json'
				).fail(function(){
					loginErrorElem.text("There was an error processing your login, please try again.").show();
				})
			}
			return false;
		},

		processAddLinkedUser: function (){
			if (this.preProcessLogin()){
				var username = $("#username").val(),
						password = $("#password").val(),
						loginErrorElem = $('#loginError'),
						url = "/MyAccount/AJAX?method=addAccountLink";
				loginErrorElem.hide();
				$.post(url, {username: username, password: password}, function (response){
					if (response.result == true){
						VuFind.showMessage("Account to Manage", response.message ? response.message : "Successfully linked the account.", true, true);
					}else{
						loginErrorElem.text(response.message).show();
					}
				}, 'json'
				).fail(function(){
					loginErrorElem.text("There was an error processing the account, please try again.").show();
				});
			}
			return false;
		},


		removeLinkedUser: function(idToRemove){
			VuFind.confirm("Are you sure you want to stop managing this account?", function () {
				var url = "/MyAccount/AJAX?method=removeAccountLink&idToRemove=" + idToRemove;
				$.getJSON(url, function(data){
					if (data.result == true){
						VuFind.showMessage('Linked Account Removed', data.message, true, true);
					}else{
						VuFind.showMessage('Unable to Remove Account Link', data.message);
					}
				});
			});
			return false;
		},

		removeTag: function(tag){
			VuFind.confirm("Are you sure you want to remove the tag \"" + tag + "\" from all titles?",function () {
				var url = "/MyAccount/AJAX",
						params = {method:'removeTag', tag: tag};
				$.getJSON(url, params, function(data){
					if (data.result == true){
						VuFind.showMessage('Tag Deleted', data.message, true, true);
					}else{
						VuFind.showMessage('Tag Not Deleted', data.message);
					}
				});
			});
			return false;
		},

		renewTitle: function(patronId, recordId, renewIndicator) {
			VuFind.Account.ajaxLogin(function (){
				VuFind.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=renewItem&patronId=" + patronId + "&recordId=" + recordId + "&renewIndicator="+renewIndicator, function(data){
					VuFind.showMessage(data.title, data.modalBody, data.success, data.success); // autoclose when successful
				}).fail(VuFind.ajaxFail)
			});
			return false;
		},

		renewAll: function() {
			VuFind.Account.ajaxLogin(function (){
				VuFind.confirm('Renew All Items?', function () {
					VuFind.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=renewAll", function (data) {
						VuFind.showMessage(data.title, data.modalBody, data.success);
						// autoclose when all successful
						if (data.success || data.renewed > 0) {
							// Refresh page on close when a item has been successfully renewed, otherwise stay
							$("#modalDialog").on('hidden.bs.modal', function (e) {
								location.reload(true);
							});
						}
					}).fail(VuFind.ajaxFail);
				})
			}, null, true);
				//auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			return false;
		},

		renewSelectedTitles: function () {
			VuFind.Account.ajaxLogin(function (){
				var selectedTitles = VuFind.getSelectedTitles();
				if (selectedTitles) {
					VuFind.confirm('Renew selected Items?', function(){
						VuFind.loadingMessage();
						$.getJSON("/MyAccount/AJAX?method=renewSelectedItems&" + selectedTitles, function (data) {
							var reload = data.success || data.renewed > 0;
							VuFind.showMessage(data.title, data.modalBody, data.success, reload);
						}).fail(VuFind.ajaxFail);
					})
				}
			}, null, true);
				 //auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			return false
		},

		resetPin: function(){
			var barcode = $('#card_number').val();
			if (barcode.length == 0){
				alert("Please enter your library card number");
			}else{
				var url = path + '/MyAccount/AJAX?method=requestPinReset&barcode=' + barcode;
				$.getJSON(url, function(data){
					if (data.error == false){
						alert(data.message);
						if (data.result == true){
							hideLightbox();
						}
					}else{
						alert("There was an error requesting your pin reset information.  Please contact the library for additional information.");
					}
				});
			}
			return false;
		},

		ajaxLightbox: function (urlToDisplay, requireLogin) {
			if (requireLogin === undefined) {
				requireLogin = false;
			}
			if (requireLogin && !Globals.loggedIn) {
				VuFind.Account.ajaxLogin(function (){
					VuFind.Account.ajaxLightbox(urlToDisplay, requireLogin);
				});
			} else {
				VuFind.loadingMessage();
				$.getJSON(urlToDisplay, function(data){
					if (data.success){
						data = data.result;
					}
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}
			return false;
		},

		confirmCancelHold: function(patronId, recordId, holdIdToCancel) {
			VuFind.loadingMessage();
			$.getJSON("/MyAccount/AJAX?method=confirmCancelHold&patronId=" + patronId + "&recordId=" + recordId + "&cancelId="+holdIdToCancel, function(data){
				VuFind.showMessageWithButtons(data.title, data.body, data.buttons);
			}).fail(VuFind.ajaxFail);

			return false
		},

		cancelHold: function(patronId, recordId, holdIdToCancel){
			VuFind.Account.ajaxLogin(function (){
				VuFind.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=cancelHold&patronId=" + patronId + "&recordId=" + recordId + "&cancelId="+holdIdToCancel, function(data){
					VuFind.showMessage(data.title, data.body, data.success, data.success); // autoclose when successful
				}).fail(VuFind.ajaxFail)
			});
			return false
		},

/* TODO This functionality is currently not employed, but it could be restored now. plb 11-23-15
        If that happens, implement the confirmation process for single cancels above to give the user clear
         choices when asked to confirm.

		cancelSelectedHolds: function() {
			if (Globals.loggedIn) {
				var selectedTitles = this.getSelectedTitles()
								.replace(/waiting|available/g, ''),// strip out of name for now.
						numHolds = $("input.titleSelect:checked").length;
				// if numHolds equals 0, quit because user has canceled in getSelectedTitles()
				if (numHolds > 0 && confirm('Cancel ' + numHolds + ' selected hold' + (numHolds > 1 ? 's' : '') + '?')) {
					VuFind.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=cancelHolds&"+selectedTitles, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect:checked").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect:checked").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(function(){
						VuFind.ajaxFail();
					});
				}
			} else {
				this.ajaxLogin(null, function () {
					VuFind.Account.cancelSelectedHolds();
				}, false);
		}
		return false;
	},
*/

		cancelBooking: function(patronId, cancelId){
			VuFind.confirm("Are you sure you want to cancel this scheduled item?", function(){
				VuFind.Account.ajaxLogin(function (){
					VuFind.loadingMessage();
					var c = {};
					c[patronId] = cancelId;
					$.getJSON("/MyAccount/AJAX", {method:"cancelBooking", cancelId:c}, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled item from page
							var escapedId = cancelId.replace(/:/g, "\\:"); // needed for jquery selector to work correctly
							// first backslash for javascript escaping, second for css escaping (within jquery)
							$('div.result').has('#selected'+escapedId).remove();
						}
					}).fail(VuFind.ajaxFail)
				});
			});

			return false
		},

		cancelSelectedBookings: function(){
			VuFind.Account.ajaxLogin(function (){
				var selectedTitles = this.getSelectedTitles(),
						numBookings = $("input.titleSelect:checked").length;
				// if numBookings equals 0, quit because user has canceled in getSelectedTitles()
				if (numBookings > 0 && confirm('Cancel ' + numBookings + ' selected scheduled item' + (numBookings > 1 ? 's' : '') + '?')) {
					VuFind.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=cancelBooking&"+selectedTitles, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect:checked").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect:checked").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(VuFind.ajaxFail);
				}
			});
			return false;

		},

		cancelAllBookings: function(){
			VuFind.confirm('Cancel all of your scheduled items?',function () {
				VuFind.Account.ajaxLogin(function (){
					VuFind.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=cancelBooking&cancelAll=1", function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(VuFind.ajaxFail);
				});
			});
			return false;
		},

		changeAccountSort: function (newSort, sortParameterName){
			if (typeof sortParameterName === 'undefined') {
				sortParameterName = 'accountSort'
			}
			var paramString = VuFind.replaceQueryParam(sortParameterName, newSort);
			location.replace(location.pathname + paramString)
		},

		changeHoldPickupLocation: function (patronId, recordId, holdId){
			VuFind.Account.ajaxLogin(function (){
				VuFind.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=getChangeHoldLocationForm&patronId=" + patronId + "&recordId=" + recordId + "&holdId=" + holdId, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				});
			});
			return false;
		},

		deleteSearch: function(searchId){
			VuFind.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX";
				var params = "method=deleteSearch&searchId=" + encodeURIComponent(searchId);
				$.getJSON(url + '?' + params,
						function(data) {
							if (data.result) {
								VuFind.showMessage("Success", data.message);
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				);
			});
			return false;
		},

		doChangeHoldLocation: function(){
			var url = "/MyAccount/AJAX"
					,params = {
						'method': 'changeHoldLocation'
						,patronId : $('#patronId').val()
						,recordId : $('#recordId').val()
						,holdId : $('#holdId').val()
						,newLocation : $('#newPickupLocation').val()
					};

			$.getJSON(url, params,
					function(data) {
						if (data.success) {
							VuFind.showMessage("Success", data.message, true, true);
						} else {
							VuFind.showMessage("Error", data.message);
						}
					}
			).fail(VuFind.ajaxFail);
		},

		freezeHold: function(patronId, recordId, holdId, promptForReactivationDate, caller){
			VuFind.loadingMessage();
			var url = '/MyAccount/AJAX',
					params = {
						patronId : patronId
						,recordId : recordId
						,holdId : holdId
					};
			if (promptForReactivationDate){
				//Prompt the user for the date they want to reactivate the hold
				params['method'] = 'getReactivationDateForm'; // set method for this form
				$.getJSON(url, params, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				}).fail(VuFind.ajaxFail);

			}else{
				var popUpBoxTitle = $(caller).text() || "Freezing Hold"; // freezing terminology can be customized, so grab text from click button: caller
				VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
				params['method'] = 'freezeHold'; //set method for this ajax call
				$.getJSON(url, params, function(data){
					if (data.success) {
						VuFind.showMessage("Success", data.message, true, true);
					} else {
						VuFind.showMessage("Error", data.message);
					}
				}).fail(VuFind.ajaxFail);
			}
		},

// called by ReactivationDateForm when fn freezeHold above has promptForReactivationDate is set
		doFreezeHoldWithReactivationDate: function(caller){
			var popUpBoxTitle = $(caller).text() || "Freezing Hold"  // freezing terminology can be customized, so grab text from click button: caller
					,params = {
						'method' : 'freezeHold'
						,patronId : $('#patronId').val()
						,recordId : $('#recordId').val()
						,holdId : $("#holdId").val()
						,reactivationDate : $("#reactivationDate").val()
					}
					,url = '/MyAccount/AJAX';
			VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			$.getJSON(url, params, function(data){
				if (data.success) {
					VuFind.showMessage("Success", data.message, true, true);
				} else {
					VuFind.showMessage("Error", data.message);
				}
			}).fail(VuFind.ajaxFail);
		},

		/* Hide this code for now. I should be to re-enable when re-enable selections for Holds
		plb 9-14-2015

		freezeSelectedHolds: function (){
			var selectedTitles = this.getSelectedTitles();
			if (selectedTitles.length == 0){
				return false;
			}
			var suspendDate = '',
					suspendDateTop = $('#suspendDateTop'),
					url = '',
					queryParams = '';
			if (suspendDateTop.length) { //Check to see whether or not we are using a suspend date.
				if (suspendDateTop.val().length > 0) {
					suspendDate = suspendDateTop.val();
				} else {
					suspendDate = $('#suspendDateBottom').val();
				}
				if (suspendDate.length == 0) {
					alert("Please select the date when the hold should be reactivated.");
					return false;
				}
			}
			url = '/MyAccount/Holds?multiAction=freezeSelected&patronId=' + patronId + '&recordId=' + recordId + '&' + selectedTitles + '&suspendDate=' + suspendDate;
			queryParams = VuFind.getQuerystringParameters();
			if ($.inArray('section', queryParams)){
				url += '&section=' + queryParams['section'];
			}
			window.location = url;
			return false;
		},
		*/


		getSelectedTitles: function(promptForSelectAll){
			if (promptForSelectAll == undefined){
				promptForSelectAll = true;
			}
			var selectedTitles = $("input.titleSelect:checked ");
			if (selectedTitles.length == 0 && promptForSelectAll && confirm('You have not selected any items, process all items?')) {
				selectedTitles = $("input.titleSelect")
					.attr('checked', 'checked');
			}
			var queryString = selectedTitles.map(function() {
				return $(this).attr('name') + "=" + $(this).val();
			}).get().join("&");

			return queryString;
		},

		saveSearch: function(searchId){
			VuFind.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX",
						params = {method :'saveSearch', searchId :searchId};
				$.getJSON(url, params,
						function(data){
							if (data.result) {
								VuFind.showMessage("Success", data.message);
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				).fail(VuFind.ajaxFail);
			});
			return false;
		},

		showCreateListForm: function(id){
			VuFind.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX",
						params = {method:"getCreateListForm"};
				if (id !== undefined){
					params.groupedWorkId = id;
				}
				$.getJSON(url, params, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			});
			return false;
		},

		thawHold: function(patronId, recordId, holdId, caller){
			var popUpBoxTitle = $(caller).text() || "Thawing Hold";  // freezing terminology can be customized, so grab text from click button: caller
			VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			var url = '/MyAccount/AJAX',
					params = {
						'method' : 'thawHold'
						,patronId : patronId
						,recordId : recordId
						,holdId : holdId
					};
			$.getJSON(url, params, function(data){
				if (data.success) {
					VuFind.showMessage("Success", data.message, true, true);
				} else {
					VuFind.showMessage("Error", data.message);
				}
			}).fail(VuFind.ajaxFail);
		},

		toggleShowCovers: function(showCovers){
			this.showCovers = showCovers;
			var paramString = VuFind.replaceQueryParam('showCovers', this.showCovers ? 'on': 'off'); // set variable
			if (!Globals.opac && VuFind.hasLocalStorage()) { // store setting in browser if not an opac computer
				window.localStorage.setItem('showCovers', this.showCovers ? 'on' : 'off');
			}
			location.replace(location.pathname + paramString); // reloads page without adding entry to history
		},

		validateCookies: function(){
			if (navigator.cookieEnabled == false){
				$("#cookiesError").show();
			}
		},

		getMasqueradeForm: function () {
			VuFind.loadingMessage();
			var url = "/MyAccount/AJAX",
					params = {method:"getMasqueradeAsForm"};
			$.getJSON(url, params, function(data){
				VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
			}).fail(VuFind.ajaxFail);
			return false;
		},

		initiateMasquerade: function() {
			var url = "/MyAccount/AJAX",
					params = {
						method:"initiateMasquerade"
						,cardNumber:$('#cardNumber').val()
					};
			$('#masqueradeAsError').hide();
			$('#masqueradeLoading').show();
			$.getJSON(url, params, function(data){
				if (data.success) {
					location.href = '/MyAccount/Home';
				} else {
					$('#masqueradeLoading').hide();
					$('#masqueradeAsError').html(data.error).show();
				}
			}).fail(VuFind.ajaxFail);
			return false;
		},

		endMasquerade: function () {
			var url = "/MyAccount/AJAX",
					params = {method:"endMasquerade"};
			$.getJSON(url, params).done(function(){
					location.href = '/MyAccount/Home';
			}).fail(VuFind.ajaxFail);
			return false;
		}

	};
}(VuFind.Account || {}));