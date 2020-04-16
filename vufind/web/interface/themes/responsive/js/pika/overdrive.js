Pika.OverDrive = (function(){
	return {
		cancelOverDriveHold: function(patronId, overdriveId){
			Pika.confirm("Are you sure you want to cancel this hold?", function () {
				var ajaxUrl = "/OverDrive/AJAX?method=CancelOverDriveHold&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function (data) {
					if (data.success) {
						Pika.showMessage("Hold Cancelled", data.message, true);
						//remove the row from the holds list
						$("#overDriveHold_" + overdriveId).hide();
					} else {
						Pika.showMessage("Error Cancelling Hold", data.message, false);
					}
				}).fail(function () {
					Pika.showMessage("Error Cancelling Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
				})
			});
			return false;
		},

		checkOutOverDriveTitle: function(overDriveId){
			Pika.Account.ajaxLogin(function(){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveCheckoutPrompts";
				$.getJSON(url, function(data){
					if (data.promptNeeded){
						Pika.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						Pika.OverDrive.doOverDriveCheckout(data.patronId, overDriveId);
					}
				}).fail(function(){
					Pika.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processOverDriveCheckoutPrompts: function(){
			var overdriveCheckoutPromptsForm = $("#overdriveCheckoutPromptsForm"),
					patronId = $("#patronId").val(),
					overdriveId = overdriveCheckoutPromptsForm.find("input[name=overdriveId]").val();
			Pika.OverDrive.doOverDriveCheckout(patronId, overdriveId);
		},

		doOverDriveCheckout: function(patronId, overdriveId){
			Pika.Account.ajaxLogin(function(){
				var ajaxUrl = "/OverDrive/AJAX?method=CheckoutOverDriveItem&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function(data){
					if (data.success) {
						Pika.showMessageWithButtons("Title Checked Out Successfully", data.message, data.buttons);
					} else if (data.noCopies) {
						Pika.confirm(data.message, function(){
							Pika.OverDrive.placeOverDriveHold(overdriveId, null);
						});
					} else {
						Pika.showMessage("Error Checking Out Title", data.message, false);
					}
				}).fail(function(){
					Pika.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		doOverDriveHold: function(patronId, overDriveId, overdriveEmail, promptForOverdriveEmail){
			var url = "/OverDrive/AJAX",
					params = {
						'method': 'PlaceOverDriveHold',
						patronId: patronId,
						overDriveId: overDriveId,
						overdriveEmail: overdriveEmail,
						promptForOverdriveEmail: promptForOverdriveEmail
					};
			$.getJSON(url, params, function(data){
					if (data.availableForCheckout){
						Pika.OverDrive.checkOutOverDriveTitle(overdriveId);
					}else{
						Pika.showMessage("Placed Hold", data.message, true);
					}
				}).fail(function(){
					Pika.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
		},

		followOverDriveDownloadLink: function(patronId, overDriveId, formatId){
			var ajaxUrl = "/OverDrive/AJAX?method=GetDownloadLink&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + formatId;
			$.getJSON(ajaxUrl, function(data){
					if (data.success){
						//Reload the page
						var win = window.open(data.downloadUrl, '_blank');
						win.focus();
						//window.location.href = data.downloadUrl ;
					}else{
						Pika.showMessage("Error Getting Download Link", data.message, false);
					}
				}).fail(function(){
				Pika.showMessage("Error Getting Download Link", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
		},

		forceUpdateFromAPI:function(overDriveId){
			var url = '/OverDrive/' + overDriveId + '/AJAX?method=forceUpdateFromAPI';
			$.getJSON(url, function (data){
					Pika.showMessage("Success", data.message, true, true);
				}
			);
			return false;
		},

		placeOverDriveHold: function(overDriveId){
			Pika.Account.ajaxLogin(function(){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveHoldPrompts";
				$.getJSON(url, function (data) {
					if (data.promptNeeded){
						Pika.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						Pika.OverDrive.doOverDriveHold(data.patronId, overDriveId, data.overdriveEmail, data.promptForOverdriveEmail);
					}
				}).fail(function(){
					Pika.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
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
			Pika.OverDrive.doOverDriveHold(patronId, overdriveId, overdriveEmail, promptForOverdriveEmail);
		},

		returnOverDriveTitle: function (patronId, overDriveId, transactionId){
			Pika.confirm('Are you sure you want to return this title?', function () {
				Pika.showMessage("Returning Title", "Returning your title in OverDrive.  This may take a minute.");
				var ajaxUrl = "/OverDrive/AJAX?method=ReturnOverDriveItem&patronId=" + patronId + "&overDriveId=" + overDriveId + "&transactionId=" + transactionId;
				$.getJSON(ajaxUrl, function(data){
					Pika.showMessage("Title Returned", data.message, data.success, data.success);
				}).fail(function(){
					Pika.showMessage("Error Returning Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
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
				Pika.confirm("Are you sure you want to download the " + selectedFormatText + " format? You cannot change format after downloading.", function () {
					var ajaxUrl = "/OverDrive/AJAX?method=SelectOverDriveDownloadFormat&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + selectedFormatId;
					$.getJSON(ajaxUrl, function(data){
							if (data.success){
								//Reload the page
								window.location.href = data.downloadUrl;
							}else{
								Pika.showMessage("Error Selecting Format", data.message);
							}
						}).fail(function(){
							Pika.showMessage("Error Selecting Format", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
						});
					});
				}
			return false;
		},

		submitHelpForm: function(){
			$.post('/OverDrive/AJAX?method=submitSupportForm', $("#eContentSupport").serialize(),
					function(data){
						Pika.showMessage(data.title, data.message);
					},
					'json').fail(Pika.ajaxFail);
			return false;
		}

	}
}(Pika.OverDrive || {}));