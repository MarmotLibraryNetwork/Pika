/**
 * Created by mark on 1/14/14.
 */
Pika.Account = (function(){

	return {
		ajaxCallback: null,
		closeModalOnAjaxSuccess: false,
		showCovers: null,

		addAccountLink: function(){
			var url = "/MyAccount/AJAX?method=getAddAccountLinkForm";
			Pika.Account.ajaxLightbox(url, true);
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
						groupedWorkId: groupedWorkId
					};
			$.getJSON(url, params,function (data) {
					if (data.success) {
						if (typeof data.modalButtons !== "undefined"){
							Pika.showMessageWithButtons("Added Successfully", data.message, data.modalButtons);
						} else{
							Pika.showMessage("Added Successfully", data.message, true, true);
						}
					} else {
						Pika.showMessage("Error", data.message);
					}
			}).fail(Pika.ajaxFail);
			return false;
		},

		addListMultiple: function(ids){
			var form = $("#addListForm"),
					isPublic = form.find("#public").prop("checked"),
					title = form.find("input[name=title]").val(),
					desc = $("#listDesc").val(),
					url = "/MyAccount/AJAX",
					params = {
						'method': "addListMultiple",
						title: title,
						public: isPublic,
						desc: desc,
						ids: ids
					};
			$.getJSON(url, params, function(data){
				if (data.success) {
					if (typeof data.modalButtons !== "undefined"){
						Pika.showMessageWithButtons("Added Successfully", data.message, data.modalButtons);
					} else{
						Pika.showMessage("Added Successfully", data.message, true, true);
					}
				} else {
					Pika.showMessage("Error", data.message);
				}
			}).fail(Pika.ajaxFail);
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
				} else if (Pika.Account.ajaxCallback != null && typeof(Pika.Account.ajaxCallback) === "function") {
					Pika.Account.ajaxCallback();
					Pika.Account.ajaxCallback = null;
				}
			} else {
				var multistep = false,
						loginLink = false;
				if (ajaxCallback !== undefined && typeof(ajaxCallback) === "function") {
					multistep = true;
				}
				Pika.Account.ajaxCallback = ajaxCallback;
				Pika.Account.closeModalOnAjaxSuccess = closeModalOnAjaxSuccess;
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
				$('.modal-buttons').html(''); // Hide any pre-existing buttons
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
				// $("#lists-placeholder").html(data.lists);
				$(".checkouts-placeholder").html(data.checkouts);
				$(".holds-placeholder").html(data.holds);
				if (Globals.activeModule === "MyAccount" && Globals.activeAction === "Home"){
					$('#account-summary-holds').html(data.accountSummaryHolds);
				}
				$(".readingHistory-placeholder").html(data.readingHistory);
				$(".materialsRequests-placeholder").html(data.materialsRequests);
				//$(".bookings-placeholder").html(data.bookings);
				$(".availableHoldsNoticePlaceHolder").html(data.availableHoldsNotice);
				$(".expirationFinesNotice-placeholder").html(data.expirationFinesNotice);
				$(".fineBadge-placeholder").html(data.fines);
				$("#tagsMenu-placeholder").replaceWith(data.tagsMenu);
			}).fail(function (){
				$(".checkouts-placeholder,.checkouts-placeholder,.readingHistory-placeholder,.materialsRequests-placeholder,.bookings-placeholder").html();
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
			if (Pika.hasLocalStorage()){
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
				if (!Globals.opac && Pika.hasLocalStorage()){
					var showCovers = window.localStorage.getItem('showCovers') || false;
					if (showCovers && showCovers.length > 0) { // if there is a set value, pass it back with the login info
						params.showCovers = showCovers
					}
				}
				loginErrorElem.hide();
				loadingElem.show();
				//Pika.loadingMessage();
				$.post(url, params, function(response){
							loadingElem.hide();
							if (response.result.success === true){
								if (response.result.forcePinUpdate === true){
									Pika.showMessageWithButtons(response.result.title, response.result.modalBody, response.result.modalButtons)
								}else{
									// Hide "log in" options and show "log out" options:
									$('.loginOptions, #loginOptions').hide();
									$('.logoutOptions, #logoutOptions').show();

									// Show username on page in case page doesn't reload
									var name = $.trim(response.result.name);
									name = 'Logged In As ' + name + '.';
									$('#side-bar #myAccountNameLink').html(name);

									if (Pika.Account.closeModalOnAjaxSuccess){
										Pika.closeLightbox();
									}

									Globals.loggedIn = true;
									if (ajaxCallback !== undefined && typeof (ajaxCallback) === "function"){
										ajaxCallback();
									}else if (Pika.Account.ajaxCallback !== undefined && typeof (Pika.Account.ajaxCallback) === "function"){
										Pika.Account.ajaxCallback();
										Pika.Account.ajaxCallback = null;
									}
								}
							}else{
								loginErrorElem.text(response.result.message).show();
							}
						}, 'json'
				).fail(function(){
					loginErrorElem.text("There was an error processing your login, please try again.").show();
				})
			}
			return false;
		},

		updatePin: function(){
			var oldPin = $('#pin').val(),
					newPin = $('#pin1').val(),
					confirmNewPin = $('#pin2').val(),
					url = '/MyAccount/AJAX?method=updatePin';
			$('#errorMsg,#successMsg').hide();
			$.post(url, {'pin': oldPin, 'pin1': newPin, 'pin2': confirmNewPin}, function (result){
				if (result.success){
					$('#errorMsg').hide();
					$('#successMsg').text(result.message).show();
					if (Pika.Account.ajaxCallback != null && typeof (Pika.Account.ajaxCallback) === "function"){
						Pika.Account.ajaxCallback();
						Pika.Account.ajaxCallback = null;
					}
				} else {
					$('#errorMsg').text(result.message).show();
				}
				$('#pinFormSubmitButton').attr("disabled", false); // Re-enable button if needed.
			});
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
						Pika.showMessage("Account to Manage", response.message ? response.message : "Successfully linked the account.", true, true);
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
			Pika.confirm("Are you sure you want to stop managing this account?", function () {
				var url = "/MyAccount/AJAX?method=removeAccountLink&idToRemove=" + idToRemove;
				$.getJSON(url, function(data){
					if (data.result == true){
						Pika.showMessage('Linked Account Removed', data.message, true, true);
					}else{
						Pika.showMessage('Unable to Remove Account Link', data.message);
					}
				});
			});
			return false;
		},

		removeViewer: function(idToRemove){
			Pika.confirm("Are you sure you want to remove the viewing account?", function () {
				var url = "/MyAccount/AJAX?method=removeViewingAccount&idToRemove=" + idToRemove;
				$.getJSON(url, function(data){
					if (data.result == true){
						Pika.showMessage('Linked Account Removed', data.message, true, true);
					}else{
						Pika.showMessage('Unable to Remove Account Link', data.message);
					}
				});
			});
			return false;
		},

		removeTag: function(tag){
			Pika.confirm("Are you sure you want to remove the tag \"" + tag + "\" from all titles?",function () {
				var url = "/MyAccount/AJAX",
						params = {method:'removeTag', tag: tag};
				$.getJSON(url, params, function(data){
					if (data.result == true){
						Pika.showMessage('Tag Deleted', data.message, true, true);
					}else{
						Pika.showMessage('Tag Not Deleted', data.message);
					}
				});
			});
			return false;
		},

		renewTitle: function(patronId, recordId, renewIndicator) {
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=renewItem&patronId=" + patronId + "&recordId=" + recordId + "&renewIndicator="+renewIndicator, function(data){
					Pika.showMessage(data.title, data.modalBody, data.success, data.success); // autoclose when successful
				}).fail(Pika.ajaxFail)
			});
			return false;
		},

		renewAll: function() {
			Pika.Account.ajaxLogin(function (){
				Pika.confirm('Renew All Items?', function () {
					Pika.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=renewAll", function (data) {
						Pika.showMessage(data.title, data.modalBody, data.success);
						// autoclose when all successful
						if (data.success || data.renewed > 0) {
							// Refresh page on close when a item has been successfully renewed, otherwise stay
							$("#modalDialog").on('hidden.bs.modal', function (e) {
								location.reload(true);
							});
						}
					}).fail(Pika.ajaxFail);
				})
			}, null, true);
				//auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			return false;
		},

		renewSelectedTitles: function () {
			Pika.Account.ajaxLogin(function (){
				var selectedTitles = Pika.getSelectedTitles();
				if (selectedTitles) {
					Pika.confirm('Renew selected Items?', function(){
						Pika.loadingMessage();
						$.getJSON("/MyAccount/AJAX?method=renewSelectedItems&" + selectedTitles, function (data) {
							var reload = data.success || data.renewed > 0;
							Pika.showMessage(data.title, data.modalBody, data.success, reload);
						}).fail(Pika.ajaxFail);
					})
				}
			}, null, true);
				 //auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			return false
		},

		ajaxLightbox: function (urlToDisplay, requireLogin, trigger) {
			if (requireLogin === undefined) {
				requireLogin = false;
			}
			if (requireLogin && !Globals.loggedIn) {
				Pika.Account.ajaxLogin(function (){
					Pika.Account.ajaxLightbox(urlToDisplay, requireLogin, trigger);
				}, trigger);
			} else {
				Pika.loadingMessage();
				$.getJSON(urlToDisplay, function(data){
					if (data.success){
						data = data.result;
					}
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
			}
			return false;
		},

		confirmCancelHold: function(patronId, recordId, holdIdToCancel) {
			Pika.loadingMessage();
			$.getJSON("/MyAccount/AJAX?method=confirmCancelHold&patronId=" + patronId + "&recordId=" + recordId + "&cancelId="+holdIdToCancel, function(data){
				Pika.showMessageWithButtons(data.title, data.body, data.buttons);
			}).fail(Pika.ajaxFail);

			return false
		},

		cancelHold: function(patronId, recordId, holdIdToCancel){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=cancelHold&patronId=" + patronId + "&recordId=" + recordId + "&cancelId="+holdIdToCancel, function(data){
					Pika.showMessage(data.title, data.body, data.success, data.success); // autoclose when successful
				}).fail(Pika.ajaxFail)
			});
			return false
		},


		cancelSelectedHolds: function() {
			if (Globals.loggedIn) {
				var selectedTitles = Pika.Account.getSelectedTitles(false)
								.replace(/waiting|available/g, ''),// strip out of name for now.
						numHolds = $("input.titleSelect:checked").length;
				// if numHolds equals 0, quit because user has canceled in getSelectedTitles()
				if (numHolds > 0 && confirm('Cancel ' + numHolds + ' selected hold' + (numHolds > 1 ? 's' : '') + '?')) {
					Pika.loadingMessage();
					$.getJSON("/MyAccount/AJAX?method=cancelHolds&"+selectedTitles, function(data){
						Pika.showMessage(data.title, data.modalBody, data.success,true); // autoclose when successful
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
						Pika.ajaxFail();
					});
				}
			} else {
				this.ajaxLogin(null, function () {
					Pika.Account.cancelSelectedHolds();
				}, false);
		}
		return false;
	},

		// cancelBooking: function(patronId, cancelId){
		// 	Pika.confirm("Are you sure you want to cancel this scheduled item?", function(){
		// 		Pika.Account.ajaxLogin(function (){
		// 			Pika.loadingMessage();
		// 			var c = {};
		// 			c[patronId] = cancelId;
		// 			$.getJSON("/MyAccount/AJAX", {method:"cancelBooking", cancelId:c}, function(data){
		// 				Pika.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
		// 				if (data.success) {
		// 					// remove canceled item from page
		// 					var escapedId = cancelId.replace(/:/g, "\\:"); // needed for jquery selector to work correctly
		// 					// first backslash for javascript escaping, second for css escaping (within jquery)
		// 					$('div.result').has('#selected'+escapedId).remove();
		// 				}
		// 			}).fail(Pika.ajaxFail)
		// 		});
		// 	});
		// 	return false
		// },

		// cancelSelectedBookings: function(){
		// 	Pika.Account.ajaxLogin(function (){
		// 		var selectedTitles = Pika.Account.getSelectedTitles(),
		// 				numBookings = $("input.titleSelect:checked").length;
		// 		// if numBookings equals 0, quit because user has canceled in getSelectedTitles()
		// 		if (numBookings > 0){
		// 			Pika.confirm('Cancel ' + numBookings + ' selected scheduled item' + (numBookings > 1 ? 's' : '') + '?',function () {
		// 				Pika.loadingMessage();
		// 				$.getJSON("/MyAccount/AJAX?method=cancelBooking&" + selectedTitles, function (data){
		// 					Pika.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
		// 					if (data.success){
		// 						// remove canceled items from page
		// 						$("input.titleSelect:checked").closest('div.result').remove();
		// 					}else if (data.failed){ // remove items that didn't fail
		// 						var searchArray = data.failed.map(function (ele){
		// 							return ele.toString()
		// 						});
		// 						// convert any number values to string, this is needed bcs inArray() below does strict comparisons
		// 						// & id will be a string. (sometimes the id values are of type number )
		// 						$("input.titleSelect:checked").each(function (){
		// 							var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
		// 							if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
		// 								$(this).closest('div.result').remove();
		// 						});
		// 					}
		// 				}).fail(Pika.ajaxFail);
		// 			});
		// 		}
		// 	});
		// 	return false;
		// },

		// cancelAllBookings: function(){
		// 	Pika.confirm('Cancel all of your scheduled items?',function () {
		// 		Pika.Account.ajaxLogin(function (){
		// 			Pika.loadingMessage();
		// 			$.getJSON("/MyAccount/AJAX?method=cancelBooking&cancelAll=1", function(data){
		// 				Pika.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
		// 				if (data.success) {
		// 					// remove canceled items from page
		// 					$("input.titleSelect").closest('div.result').remove();
		// 				} else if (data.failed) { // remove items that didn't fail
		// 					var searchArray = data.failed.map(function(ele){return ele.toString()});
		// 					// convert any number values to string, this is needed bcs inArray() below does strict comparisons
		// 					// & id will be a string. (sometimes the id values are of type number )
		// 					$("input.titleSelect").each(function(){
		// 						var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
		// 						if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
		// 							$(this).closest('div.result').remove();
		// 					});
		// 				}
		// 			}).fail(Pika.ajaxFail);
		// 		});
		// 	});
		// 	return false;
		// },

		changeAccountSort: function (newSort, sortParameterName){
			if (typeof sortParameterName === 'undefined') {
				sortParameterName = 'accountSort'
			}
			var paramString = Pika.replaceQueryParam(sortParameterName, newSort);
			location.replace(location.pathname + paramString)
		},

		changeHoldPickupLocation: function (patronId, recordId, holdId){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				$.getJSON("/MyAccount/AJAX?method=getChangeHoldLocationForm&patronId=" + patronId + "&recordId=" + recordId + "&holdId=" + holdId, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				});
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
							Pika.showMessage("Success", data.message, true, true);
						} else {
							Pika.showMessage("Error", data.message);
						}
					}
			).fail(Pika.ajaxFail);
		},

		freezeHold: function(patronId, recordId, holdId, promptForReactivationDate, caller){
			Pika.loadingMessage();
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
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				}).fail(Pika.ajaxFail);

			}else{
				var popUpBoxTitle = $(caller).text() || "Freezing Hold"; // freezing terminology can be customized, so grab text from click button: caller
				Pika.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
				params['method'] = 'freezeHold'; //set method for this ajax call
				$.getJSON(url, params, function(data){
					if (data.success) {
						Pika.showMessage("Success", data.message, true, true);
					} else {
						Pika.showMessage("Error", data.message);
					}
				}).fail(Pika.ajaxFail);
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
			Pika.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			$.getJSON(url, params, function(data){
				if (data.success) {
					Pika.showMessage("Success", data.message, true, true);
				} else {
					Pika.showMessage("Error", data.message);
				}
			}).fail(Pika.ajaxFail);
		},
		getFreezeHoldsForm: function(){
			this.ajaxLogin(function(){
				var selectedTitles = Pika.Account.getSelectedTitles(false).replace(/waiting|available/g, '');
				if (selectedTitles.length == 0){
					return false;
				}
				var params = {
					'method' : 'getFreezeHoldsForm'
				}
				$.getJSON('/MyAccount/AJAX?' + selectedTitles, params, function(data){
					if (data.success){
						Pika.showMessageWithButtons(data.title, data.body, data.buttons);
					}else{
						Pika.showMessage("error", data.message);
					}
				}).fail(Pika.ajaxFail);
			});
			return false;
		},
		freezeSelectedHolds: function (selectedTitles){

			var suspendDate = '',
					suspendDateTop = $('#suspendDate'),
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

			var params = {
				'method': 'freezeHolds',
				'suspendDate': suspendDate,
				'selectedTitles': selectedTitles
			}
			$.getJSON('/MyAccount/AJAX', params, function(data){
				Pika.showMessage(data.title, data.modalBody, data.success, true);
			}).fail(Pika.ajaxFail);
			return false;
		},
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

		saveSearch: function(searchId, autoClose, refreshAfterClose){
			Pika.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX",
						params = {method :'saveSearch', searchId :searchId};
				$.getJSON(url, params,
						function(data){
							if (data.result) {
								Pika.showMessage("Success", data.message, autoClose, refreshAfterClose);
							} else {
								Pika.showMessage("Error", data.message);
							}
						}
				).fail(Pika.ajaxFail);
			});
			return false;
		},

		deleteSearch: function(searchId, autoClose, refreshAfterClose){
			Pika.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX",
						params = {method :'deleteSavedSearch', searchId :searchId};
				$.getJSON(url, params,
						function(data){
							if (data.result) {
								Pika.showMessage("Success", data.message, autoClose, refreshAfterClose);
							} else {
								Pika.showMessage("Error", data.message);
							}
						}
				).fail(Pika.ajaxFail);
			});
			return false;
		},

		showCreateListForm: function(id, defaultTitle){
			Pika.Account.ajaxLogin(function (){
				var url = "/MyAccount/AJAX",
						params = {method:"getCreateListForm", defaultTitle:defaultTitle};
				if (id !== undefined){
					params.groupedWorkId = id;
				}
				$.getJSON(url, params, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		showCreateListMultipleForm: function(ids){
			Pika.Account.ajaxLogin(function(){
				var url="/MyAccount/AJAX",
						params = {method:"getCreateListMultipleForm", ids:ids};
				$.getJSON(url, params, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);

				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		thawHold: function(patronId, recordId, holdId, caller){
			var popUpBoxTitle = $(caller).text() || "Thawing Hold";  // freezing terminology can be customized, so grab text from click button: caller
			Pika.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			var url = '/MyAccount/AJAX',
					params = {
						'method' : 'thawHold'
						,patronId : patronId
						,recordId : recordId
						,holdId : holdId
					};
			$.getJSON(url, params, function(data){
				if (data.success) {
					Pika.showMessage("Success", data.message, true, true);
				} else {
					Pika.showMessage("Error", data.message);
				}
			}).fail(Pika.ajaxFail);
		},

		toggleShowCovers: function(showCovers){
			this.showCovers = showCovers;
			var paramString = Pika.replaceQueryParam('showCovers', this.showCovers ? 'on': 'off'); // set variable
			if (!Globals.opac && Pika.hasLocalStorage()) { // store setting in browser if not an opac computer
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
			Pika.loadingMessage();
			var url = "/MyAccount/AJAX",
					params = {method:"getMasqueradeAsForm"};
			$.getJSON(url, params, function(data){
				Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
			}).fail(Pika.ajaxFail);
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
			}).fail(Pika.ajaxFail);
			return false;
		},

		endMasquerade: function () {
			var url = "/MyAccount/AJAX",
					params = {method:"endMasquerade"};
			$.getJSON(url, params).done(function(){
					location.href = '/MyAccount/Home';
			}).fail(Pika.ajaxFail);
			return false;
		}

	};
}(Pika.Account || {}));

/**
 * checkSelectedOption - Checks for the initially selected option in a select element.
 *
 * This function addresses the limitation where the onclick event on select boxes always returns the currently visible option.
 * It works by checking if the initially visible option (the one with the 'selected' attribute) is currently selected.
 * This is particularly useful in scenarios like in Pika where the initial option is pre-selected.
 *
 * Note: This function only works if the initial visible option has the 'selected' attribute.
 *
 * @param {HTMLSelectElement} selectElement - The select DOM element to check.
 * @returns {string|null} - Returns the value of the selected option, or null if the option is already selected.
 */
function checkSelectedOption(selectElement) {
	var selectedOption = selectElement.options[selectElement.selectedIndex];
	var selectedValue = selectedOption.value;

	if (selectedOption.hasAttribute('selected')) {
		return null;
	}
	return selectedValue;
}
