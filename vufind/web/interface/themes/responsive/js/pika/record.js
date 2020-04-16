Pika.Record = (function(){
	return {
		showPlaceHold: function(module, id){
			Pika.Account.ajaxLogin(function (){
				var source,
						volume = null;
				if (id.indexOf(":") > 0){
					var idParts = id.split(":");
					source = idParts[0];
					id = idParts[1];
					if (idParts.length > 2){
						volume = idParts[2];
					}
				}else{
					source = 'ils';
				}
				var url = "/" + module + "/" + id + "/AJAX?method=getPlaceHoldForm&recordSource=" + source;
				if (volume != null){
					url += "&volume=" + volume;
				}
				//Pika.showMessage('Loading...', 'Loading, please wait.');
				$.getJSON(url, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		// showPlaceHold: function(module, id, promptForAlternateEdition){
		// 	if (Globals.loggedIn){
		// 		if (typeof promptForAlternateEdition == 'undefined') {
		// 			promptForAlternateEdition = true;
		// 		}
		// 		var source;
		// 		var volume = null;
		// 		if (id.indexOf(":") > 0){
		// 			var idParts = id.split(":");
		// 			source = idParts[0];
		// 			id = idParts[1];
		// 			if (idParts.length > 2){
		// 				volume = idParts[2];
		// 			}
		// 		}else{
		// 			source = 'ils';
		// 		}
		//
		// 		var isPrimaryEditionCheckedOout = $('#relatedRecordPopup__Book>table>tbody>tr').length > 1 && $('#relatedRecordPopup__Book>table>tbody>tr:first-child .related-manifestation-shelf-status').hasClass('checked_out');
		// 		if (promptForAlternateEdition && isPrimaryEditionCheckedOout) {
		// 			Pika.showMessageWithButtons('Place Hold on Alternate Edition?',
		// 					'<div class="alert alert-info">This edition is currently checked out. Are you interested in requesting a different edition that may be available faster?</div>',
		// 					'<a href="#" class="btn btn-primary" onclick="return Pika.Record.showPlaceHoldEditions(\''+ module + '\', \'' + id + '\');">Yes, show more editions</a>' +
		// 					'<a href="#" class="btn btn-primary" onclick="return Pika.Record.showPlaceHold(\''+ module + '\', \'' + id + '\', false);">No, place a hold on this edition</a>'
		// 			);
		// 			return false;
		// 		}
		//
		// 		var url = "/" + module + "/" + id + "/AJAX?method=getPlaceHoldForm&recordSource=" + source;
		// 		if (volume != null){
		// 			url += "&volume=" + volume;
		// 		}
		// 		//Pika.showMessage('Loading...', 'Loading, please wait.');
		// 		$.getJSON(url, function(data){
		// 			Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
		// 		}).fail(Pika.ajaxFail);
		// 	}else{
		// 		Pika.Account.ajaxLogin(function(){
		// 			Pika.Record.showPlaceHold(module, id);
		// 		});
		// 	}
		// 	return false;
		// },
		//
		showPlaceHoldEditions: function (module, id) {
			Pika.Account.ajaxLogin(function (){
				var source,
						volume = null;
				if (id.indexOf(":") > 0){
					var idParts = id.split(":");
					source = idParts[0];
					id = idParts[1];
					if (idParts.length > 2){
						volume = idParts[2];
					}
				}else{
					source = 'ils';
				}

				var url = "/" + module + "/" + id + "/AJAX?method=getPlaceHoldEditionsForm&recordSource=" + source;
				if (volume != null){
					url += "&volume=" + volume;
				}
				$.getJSON(url, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		showBookMaterial: function(module, id){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				//var source; // source not used for booking at this time
				if (id.indexOf(":") > 0){
					var idParts = id.split(":", 2);
					//source = idParts[0];
					id = idParts[1];
				}
				$.getJSON("/" + module + "/" + id + "/AJAX?method=getBookMaterialForm", function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail)
			});
			return false;
		},

		submitBookMaterialForm: function() {
			var params = $('#bookMaterialForm').serialize() + '&method=bookMaterial',
					module = $('#module').val();
			Pika.showMessage('Scheduling', 'Processing, please wait.');
			$.getJSON("/" + module + "/AJAX", params, function (data) {
				if (data.modalBody) Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				// For errors that can be fixed by the user, the form will be re-displayed
				if (data.success) Pika.showMessageWithButtons('Success', data.message, data.buttons);
				else if (data.message) Pika.showMessage('Error', data.message);
			}).fail(Pika.ajaxFail);
		},

		submitHoldForm: function(){
			var id = $('#id').val()
					,autoLogOut = $('#autologout').prop('checked')
					,selectedItem = $('#selectedItem')
					,module = $('#module').val()
					,volume = $('#volume')
					,params = {
						'method': 'placeHold'
						,campus: $('#campus').val()
						,selectedUser: $('#user').val()
						,canceldate: $('#canceldate').val()
						,recordSource: $('#recordSource').val()
						,account: $('#account').val()
					};
			if (autoLogOut){
				params['autologout'] = true;
			}
			if (selectedItem.length > 0){
				params['selectedItem'] = selectedItem.val();
			}
			if (volume.length > 0){
				params['volume'] = volume.val();
			}
			if (params['campus'] == 'undefined'){
				alert("Please select a location to pick up your hold when it is ready.");
				return false;
			}
			Pika.showMessageWithButtons($("#myModalLabel").html(), 'Loading, please wait.', $('.modal-buttons').html()); // Can't use standard Pika.loadingMessage() bcs the buttons need to stay for follow-up Item-level hold prompts
			$.getJSON("/" + module +  "/" + id + "/AJAX", params, function(data){
				if (data.success){
					if (data.needsItemLevelHold){
						$('.modal-body').html(data.message);
					}else{
						// Pika.showMessage('Hold Placed Successfully', data.message, false, autoLogOut);
						Pika.showMessageWithButtons('Hold Placed Successfully', data.message, data.buttons);
					}
				}else{
					Pika.showMessage('Hold Failed', data.message, false, autoLogOut);
				}
			}).fail(Pika.ajaxFail);
		},

		reloadCover: function(module, id){
			var url = '/' +module + '/' + id + '/AJAX?method=reloadCover';
			$.getJSON(url, function (data){
						Pika.showMessage("Success", data.message, true, true);
					}
			).fail(Pika.ajaxFail);
			return false;
		},

		forceReExtract: function (module, id) {
			var url = '/' + module + '/' + id + '/AJAX?method=forceReExtract';
			$.getJSON(url, function (data) {
				Pika.showMessage(data.success ? "Success" : "Error", data.message, data.success, data.success);
					}
			).fail(Pika.ajaxFail);
			return false;
		},

		moreContributors: function(){
			document.getElementById('showAdditionalContributorsLink').style.display="none";
			document.getElementById('additionalContributors').style.display="block";
		},

		lessContributors: function(){
			document.getElementById('showAdditionalContributorsLink').style.display="block";
			document.getElementById('additionalContributors').style.display="none";
		}

	};
}(Pika.Record || {}));