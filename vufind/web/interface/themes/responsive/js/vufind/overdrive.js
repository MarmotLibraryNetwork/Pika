VuFind.OverDrive = (function(){
	return {
		cancelOverDriveHold: function(patronId, overdriveId){
			VuFind.confirm("Are you sure you want to cancel this hold?", function () {
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=CancelOverDriveHold&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function (data) {
					if (data.success) {
						VuFind.showMessage("Hold Cancelled", data.message, true);
						//remove the row from the holds list
						$("#overDriveHold_" + overdriveId).hide();
					} else {
						VuFind.showMessage("Error Cancelling Hold", data.message, false);
					}
				}).fail(function () {
					VuFind.showMessage("Error Cancelling Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
				})
			});
			return false;
		},

		checkOutOverDriveTitle: function(overDriveId){
			VuFind.Account.ajaxLogin(function(){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = Globals.path + "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveCheckoutPrompts";
				$.getJSON(url, function(data){
					if (data.promptNeeded){
						VuFind.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						VuFind.OverDrive.doOverDriveCheckout(data.patronId, overDriveId);
					}
				}).fail(function(){
					VuFind.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processOverDriveCheckoutPrompts: function(){
			var overdriveCheckoutPromptsForm = $("#overdriveCheckoutPromptsForm"),
					patronId = $("#patronId").val(),
					overdriveId = overdriveCheckoutPromptsForm.find("input[name=overdriveId]").val();
			VuFind.OverDrive.doOverDriveCheckout(patronId, overdriveId);
		},

		doOverDriveCheckout: function(patronId, overdriveId){
			VuFind.Account.ajaxLogin(function(){
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=CheckoutOverDriveItem&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function(data){
					if (data.success) {
						VuFind.showMessageWithButtons("Title Checked Out Successfully", data.message, data.buttons);
					} else if (data.noCopies) {
						VuFind.confirm(data.message, function(){
							VuFind.OverDrive.placeOverDriveHold(overdriveId, null);
						});
					} else {
						VuFind.showMessage("Error Checking Out Title", data.message, false);
					}
				}).fail(function(){
					VuFind.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		doOverDriveHold: function(patronId, overDriveId, overdriveEmail, promptForOverdriveEmail){
			var url = Globals.path + "/OverDrive/AJAX",
					params = {
						'method': 'PlaceOverDriveHold',
						patronId: patronId,
						overDriveId: overDriveId,
						overdriveEmail: overdriveEmail,
						promptForOverdriveEmail: promptForOverdriveEmail
					};
			$.getJSON(url, params, function(data){
					if (data.availableForCheckout){
						VuFind.OverDrive.checkOutOverDriveTitle(overdriveId);
					}else{
						VuFind.showMessage("Placed Hold", data.message, true);
					}
				}).fail(function(){
					VuFind.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
		},

		followOverDriveDownloadLink: function(patronId, overDriveId, formatId){
			var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=GetDownloadLink&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + formatId;
			$.getJSON(ajaxUrl, function(data){
					if (data.success){
						//Reload the page
						var win = window.open(data.downloadUrl, '_blank');
						win.focus();
						//window.location.href = data.downloadUrl ;
					}else{
						VuFind.showMessage("Error Getting Download Link", data.message, false);
					}
				}).fail(function(){
				VuFind.showMessage("Error Getting Download Link", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
		},

		forceUpdateFromAPI:function(overDriveId){
			var url = Globals.path + '/OverDrive/' + overDriveId + '/AJAX?method=forceUpdateFromAPI';
			$.getJSON(url, function (data){
					VuFind.showMessage("Success", data.message, true, true);
				}
			);
			return false;
		},

		placeOverDriveHold: function(overDriveId){
			VuFind.Account.ajaxLogin(function(){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = Globals.path + "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveHoldPrompts";
				$.getJSON(url, function (data) {
					if (data.promptNeeded){
						VuFind.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						VuFind.OverDrive.doOverDriveHold(data.patronId, overDriveId, data.overdriveEmail, data.promptForOverdriveEmail);
					}
				}).fail(function(){
					VuFind.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processOverDriveHoldPrompts: function(){
				var overdriveHoldPromptsForm = $("#overdriveHoldPromptsForm"),
						overdriveEmail = overdriveHoldPromptsForm.find("input[name=overdriveEmail]").val(),
						overdriveId = overdriveHoldPromptsForm.find("input[name=overdriveId]").val(),
						patronId = $("#patronId").val(),
						promptForOverdriveEmail;
			if (overdriveHoldPromptsForm.find("input[name=promptForOverdriveEmail]").is(":checked")){
				promptForOverdriveEmail = 0;
			}else{
				promptForOverdriveEmail = 1;
			}
			VuFind.OverDrive.doOverDriveHold(patronId, overdriveId, overdriveEmail, promptForOverdriveEmail);
		},

		returnOverDriveTitle: function (patronId, overDriveId, transactionId){
			VuFind.confirm('Are you sure you want to return this title?', function () {
				VuFind.showMessage("Returning Title", "Returning your title in OverDrive.  This may take a minute.");
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=ReturnOverDriveItem&patronId=" + patronId + "&overDriveId=" + overDriveId + "&transactionId=" + transactionId;
				$.getJSON(ajaxUrl, function(data){
					VuFind.showMessage("Title Returned", data.message, data.success, data.success);
				}).fail(function(){
					VuFind.showMessage("Error Returning Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		selectOverDriveDownloadFormat: function(patronId, overDriveId){
			var selectedOption = $("#downloadFormat_" + overDriveId + " option:selected"),
					selectedFormatId = selectedOption.val(),
					selectedFormatText = selectedOption.text();
			if (selectedFormatId === -1){
				alert("Please select a format to download.");
			}else{
				VuFind.confirm("Are you sure you want to download the " + selectedFormatText + " format? You cannot change format after downloading.", function () {
					var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=SelectOverDriveDownloadFormat&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + selectedFormatId;
					$.getJSON(ajaxUrl, function(data){
							if (data.success){
								//Reload the page
								window.location.href = data.downloadUrl;
							}else{
								VuFind.showMessage("Error Selecting Format", data.message);
							}
						}).fail(function(){
							VuFind.showMessage("Error Selecting Format", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
						});
					});
				}
			return false;
		},

		submitHelpForm: function(){
			$.post(Globals.path + '/OverDrive/AJAX?method=submitSupportForm', $("#eContentSupport").serialize(),
					function(data){
						VuFind.showMessage(data.title, data.message);
					},
					'json').fail(VuFind.ajaxFail);
			return false;
		}

	}
}(VuFind.OverDrive || {}));