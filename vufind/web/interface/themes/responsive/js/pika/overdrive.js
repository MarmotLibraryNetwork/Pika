Pika.OverDrive = (function(){
	return {
		cancelOverDriveHold: function(patronId, overdriveId){
			Pika.confirm("Are you sure you want to cancel this hold?", function () {
				var ajaxUrl = "/OverDrive/AJAX?method=cancelOverDriveHold&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function (data) {
					if (data.success) {
						Pika.showMessage("Hold Cancelled", data.message, true);
						//remove the row from the holds list
						$("#overDriveHold_" + overdriveId).remove();
						Pika.Account.loadMenuData(); //Update menu counts
					} else {
						Pika.showMessage("Error Cancelling Hold", data.message, false);
					}
				}).fail(function () {
					Pika.showMessage("Error Cancelling Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
				})
			});
			return false;
		},

		thawOverDriveHold: function(patronId, overdriveId){
				var ajaxUrl = "/OverDrive/AJAX?method=thawOverDriveHold&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.getJSON(ajaxUrl, function (data) {
					if (data.success) {
						Pika.showMessage(data.title, data.message, true, true);
					} else {
						Pika.showMessage(data.title, data.message, false);
					}
				}).fail(function () {
					Pika.showMessage("Error Thawing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
				})
			return false;
		},

		checkOutOverDriveTitle: function(overDriveId, formatType, issueId){
			Pika.Account.ajaxLogin(function(){
				Pika.loadingMessage();
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=getOverDriveCheckoutPrompts"
				+ (formatType === undefined ? "" : "&formatType=" + formatType)
				+ (issueId === undefined ? "" : "&issueId=" + issueId);
				$.getJSON(url, function(data){
					if (data.promptNeeded){
						Pika.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						if (data.success) {
							Pika.showMessageWithButtons("Title Checked Out Successfully", data.message, data.buttons);
						} else if (data.noCopies){
							Pika.confirm(data.message, function (){
								Pika.OverDrive.placeOverDriveHold(overdriveId, null);
							});
						} else {
							Pika.showMessage("Error Checking Out Title", data.message);
						}
					}
				}).fail(function(e){
					console.log(e);
					Pika.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processOverDriveCheckoutPrompts: function(){
			var overdriveCheckoutPromptsForm = $("#overdriveCheckoutPromptsForm"),
					patronId = $("#patronId").val(),
					overdriveId = overdriveCheckoutPromptsForm.find("input[name=overdriveId]").val(),
					issueId = overdriveCheckoutPromptsForm.find("#issuesToCheckout").val(),

					lendingPeriod = $('#lendingPeriodSelect' + patronId).val(),
					formatType = $('#formatType').val(),
					useDefaultLendingPeriods = $('#useDefaultLendingPeriods' + patronId).is(":checked") === true; // only set if the element is found and is checked; otherwise set as false
			Pika.OverDrive.doOverDriveCheckout(patronId, overdriveId, lendingPeriod, useDefaultLendingPeriods, formatType, issueId);
		},

		doOverDriveCheckout: function(patronId, overDriveId, lendingPeriod, useDefaultLendingPeriods, formatType, issueId){
			Pika.Account.ajaxLogin(function(){
				var ajaxUrl = "/OverDrive/" + overDriveId + "/AJAX?method=checkoutOverDriveTitle&patronId=" + patronId
				+ ((lendingPeriod !== undefined) ? "&lendingPeriod=" + lendingPeriod : "")
				+ ((formatType !== undefined && formatType != "") ? "&formatType=" + formatType : "")
				+((issueId !== undefined && issueId !="") ? "&issueId=" + issueId: "")
				+ (useDefaultLendingPeriods !== undefined && useDefaultLendingPeriods === true ? "&useDefaultLendingPeriods" : "");
				$.getJSON(ajaxUrl, function(data){
					if (data.success) {
						Pika.showMessageWithButtons("Title Checked Out Successfully", data.message, data.buttons);
					} else if (data.noCopies) {
						Pika.confirm(data.message, function(){
							Pika.OverDrive.placeOverDriveHold(overDriveId, null);
						});
					} else {
						if (data.promptNeeded){
							Pika.showMessageWithButtons("Error Checking Out Title", data.message, data.buttons);
						} else{
							Pika.showMessage("Error Checking Out Title", data.message, false);
						}
					}
				}).fail(function(){
					Pika.showMessage("Error Checking Out Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		doOverDriveHold: function(patronId, overDriveId, overDriveEmail, rememberOverDriveEmail){
			var url = "/OverDrive/AJAX",
					params = {
						'method': 'placeOverDriveHold',
						patronId: patronId,
						overDriveId: overDriveId,
						overDriveEmail: overDriveEmail,
						rememberOverDriveEmail: rememberOverDriveEmail
					};
			$.getJSON(url, params, function(data){
					if (data.availableForCheckout){
						Pika.OverDrive.checkOutOverDriveTitle(overdriveId);
					}else{
						if (data.success){
							Pika.showMessageWithButtons("Placed Hold", data.message, data.buttons);
						} else {
							Pika.showMessage("Error Placing Hold", data.message);
						}
					}
				}).fail(function(){
					Pika.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
			return false;
		},

		followOverDriveDownloadLink: function(patronId, overDriveId){
			var ajaxUrl = "/OverDrive/AJAX?method=getDownloadLink&patronId=" + patronId + "&overDriveId=" + overDriveId;
			$.getJSON(ajaxUrl, function(data){
				 if (data.success){
						//Reload the page
						var win = window.open(data.downloadUrl, '_blank');
						win.focus();
					}else{
						Pika.showMessage("Error Getting Download Link", data.message, false);
					}
				}).fail(function(){
				Pika.showMessage("Error Getting Download Link", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
			return false;
		},

		forceUpdateFromAPI:function(overDriveId, pageReload){
			var url = '/OverDrive/' + overDriveId + '/AJAX?method=forceUpdateFromAPI';
			$.getJSON(url, function (data){
					if (typeof pageReload === 'undefined') pageReload = true;
					Pika.showMessage("Success", data.message, true, pageReload);
				}
			);
			return false;
		},

		placeOverDriveHold: function(overDriveId){
			Pika.Account.ajaxLogin(function(){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=getOverDriveHoldPrompts";
				$.getJSON(url, function (data) {
					if (data.promptNeeded){
						Pika.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					} else {
						Pika.OverDrive.doOverDriveHold(data.patronId, overDriveId, data.overDriveEmail, false);
					}
				}).fail(function(){
					Pika.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processOverDriveHoldPrompts: function(){
				var overDriveHoldPromptsForm = $("#overDriveHoldPromptsForm"),
						overDriveEmail = overDriveHoldPromptsForm.find("input[name=overDriveEmail]").val(),
						overdriveId = overDriveHoldPromptsForm.find("input[name=overdriveId]").val(),
						patronId = $("#patronId").val(),
						rememberOverDriveEmail = overDriveHoldPromptsForm.find("input[name=rememberOverDriveEmail]").is(":checked") ? 1 : 0;
			Pika.OverDrive.doOverDriveHold(patronId, overdriveId, overDriveEmail, rememberOverDriveEmail);
		},

		updateOverDriveHold: function(patronId, overDriveId, thawDate){
			Pika.Account.ajaxLogin(function(){
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=getOverDriveUpdateHoldPrompts&patronId=" + patronId;
				if (thawDate !== undefined){
					url += '&thawDate=' + thawDate;
				}
				$.getJSON(url, function (data) {
					Pika.showMessageWithButtons(data.title, data.message, data.buttons);
				}).fail(function(){
					Pika.showMessage("Error", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		freezeOverDriveHold: function(patronId, overDriveId, thawDate){
			Pika.Account.ajaxLogin(function(){
				var url = "/OverDrive/" + overDriveId + "/AJAX?method=getOverDriveFreezeHoldPrompts&patronId=" + patronId;
				if (thawDate !== undefined){
					url += '&thawDate=' + thawDate;
				}
				$.getJSON(url, function (data) {
					Pika.showMessageWithButtons(data.title, data.message, data.buttons);
				}).fail(function(){
					Pika.showMessage("Error", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		processFreezeOverDriveHoldPrompts: function(){
			var overDriveHoldPromptsForm = $("#overdriveFreezeHoldPromptsForm"),
					overDriveEmail = overDriveHoldPromptsForm.find("input[name=overDriveEmail]").val(),
					overDriveId = overDriveHoldPromptsForm.find("input[name=overDriveId]").val(),
					patronId = $("#patronId").val(),
					thawDate = $("#thawDate").val(),
					rememberOverDriveEmail = overDriveHoldPromptsForm.find("input[name=rememberOverDriveEmail]").is(":checked") ? 1 : 0,
					url = "/OverDrive/AJAX",
					params = {
						'method': 'freezeOverDriveHold',
						patronId: patronId,
						overDriveId: overDriveId,
						overDriveEmail: overDriveEmail,
						rememberOverDriveEmail: rememberOverDriveEmail,
						thawDate: thawDate
					};
			$.getJSON(url, params, function (data){
				Pika.showMessage(data.title, data.message, data.success, data.success);
			}).fail(function (){
				Pika.showMessage("Error", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
			});
			return false;
		},

		returnOverDriveTitle: function (patronId, overDriveId){
			Pika.confirm('Are you sure you want to return this title?', function (){
				Pika.showMessage("Returning Title", "Returning your title in OverDrive.");
				var ajaxUrl = "/OverDrive/AJAX?method=returnOverDriveItem&patronId=" + patronId + "&overDriveId=" + overDriveId;
				$.getJSON(ajaxUrl, function (data){
					Pika.showMessage("Title Returned", data.message, data.success);
					if (data.success){
						$('#overdrive_'+overDriveId).remove(); //hide checkout entry
						Pika.Account.loadMenuData(); //Update menu counts
					}
				}).fail(function (){
					Pika.showMessage("Error Returning Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});
			});
			return false;
		},

		selectOverDriveDownloadFormat: function(patronId, overDriveId){
			var selectedOption = $("#downloadFormat_" + overDriveId + " option:selected"),
					selectedFormatType = selectedOption.val(),
					selectedFormatText = selectedOption.text();
			if (selectedFormatType === "-1"){
				alert("Please select a format to download.");
			}else{
				Pika.confirm("Are you sure you want to download the " + selectedFormatText + " format? You cannot change format after downloading.", function () {
					var ajaxUrl = "/OverDrive/AJAX?method=selectOverDriveDownloadFormat&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatType=" + selectedFormatType;
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
		checkoutOverdriveMagazineByIssueID: function (overDriveId){

				Pika.loadingMessage();
				var url = "/OverDrive/AJAX?method=getOverDriveIssueCheckoutPrompt&overdriveId=" + overDriveId;
				$.getJSON(url, function(data){
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);

				}).fail(function(){
					Pika.showMessage("Error Loading Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
				});

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