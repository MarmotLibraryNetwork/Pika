/**
 * Created by mark on 5/19/14.
 */
Pika.MaterialsRequest = (function(){
	return {
		getWorldCatIdentifiers: function(){
			var title = $("#title").val(),
					author = $("#author").val(),
					format = $("#format").val();
			if (title == '' && author == ''){
				alert("Please enter a title and author before checking for an ISBN and OCLC Number");
			}else{
				var requestUrl = "/MaterialsRequest/AJAX",
						params = {
							'method': 'GetWorldCatIdentifiers',
							title: title,
							author: author,
							format: format
						};
				$.getJSON(requestUrl, params, function (data) {
					if (data.success) {
						//Display the results of the suggestions
						$("#suggestedIdentifiers").html(data.formattedSuggestions).slideDown();
					}else{
						alert(data.error);
					}
				});
			}
			return false;
		},

		cancelMaterialsRequest: function(id){
			Pika.confirm("Are you sure you want to cancel this request?", function(){
				var url = "/MaterialsRequest/AJAX",
						params = {
							'method': 'CancelRequest',
							id: id
						};
				$.getJSON(url, params, function(data){
							if (data.success){
								Pika.showMessage('Cancel Material Request', 'Your request was cancelled successfully.', data.success, data.success);
							}else{
								Pika.showMessage('Cancel Material Request', data.error, data.success, data.success);
							}
						}
				);
			});
			return false;
		},

		showMaterialsRequestDetails: function(id, staffView){
			return Pika.Account.ajaxLightbox("/MaterialsRequest/AJAX?method=MaterialsRequestDetails&id=" +id + "&staffView=" +staffView, true);
		},

		updateMaterialsRequest: function(id){
			return Pika.Account.ajaxLightbox("/MaterialsRequest/AJAX?method=UpdateMaterialsRequest&id=" +id, true);
		},

		exportSelectedRequests: function(){
			var selectedRequests = this.getSelectedRequests(true);
			if (selectedRequests.length == 0){
				return false;
			}
			$("#updateRequests").submit();
			return true;
		},

		updateSelectedRequests: function(){
			var newStatus = $("#newStatus").val();
			if (newStatus == "unselected"){
				alert("Please select a status to update the requests to.");
				return false;
			}
			var selectedRequests = this.getSelectedRequests(false);
			if (selectedRequests.length != 0){
				$("#updateRequests").submit();
			}
			return false;
		},

		assignSelectedRequests: function(){
			var newAssignee = $("#newAssignee").val();
			if (newAssignee == "unselected"){
				alert("Please select a user to assign the requests to.");
				return false;
			}
			var selectedRequests = this.getSelectedRequests(false);
			if (selectedRequests.length != 0){
				$("#updateRequests").submit();
			}
			return false;
		},

		getSelectedRequests: function(promptToSelectAll){
			if ( $("input.select:checked").length == 0){
				if (promptToSelectAll){
					if (confirm('You have not selected any requests, select all requests?')) {
						// $("input.select").attr('checked', 'checked');
						$("input.select").prop('checked', 'checked');
					}
				}else{
					alert("Please select one or more requests to update");
				}
			}
			var selectedRequests = $("input.select:checked").map(function(){
				return $(this).attr('name') + "=" + $(this).val();
			}).get().join("&");

			return selectedRequests;
		},

		setIsbnAndOclcNumber: function(title, author, isbn, oclcNumber){
			$("#title").val(title);
			$("#author").val(author);
			$("#isbn").val(isbn);
			$("#oclcNumber").val(oclcNumber);
			$("#suggestedIdentifiers").slideUp();
		},

		setFieldVisibility: function(){
			$(".formatSpecificField").hide();
			//Get the selected format
			var selectedFormat = $("#format").find("option:selected").val(),
					hasSpecialFields = typeof Pika.MaterialsRequest.specialFields != 'undefined';

			$(".specialFormatField").hide(); // hide all the special fields
			$(".specialFormatHideField").show(); // show all the special format hide fields
			this.updateHoldOptions();
			if (hasSpecialFields){
				if (Pika.MaterialsRequest.specialFields[selectedFormat]) {
					Pika.MaterialsRequest.specialFields[selectedFormat].forEach(function (specifiedOption) {
						switch (specifiedOption) {
							case 'Abridged/Unabridged':
								$(".abridgedField").show();
								$(".abridgedHideField").hide();
								break;
							case 'Article Field':
								$(".articleField").show();
								$(".articleHideField").hide();
								break;
							case 'Eaudio format':
								$(".eaudioField").show();
								$(".eaudioHideField").hide();
								break;
							case 'Ebook format':
								$(".ebookField").show();
								$(".ebookHideField").hide();
								break;
							case 'Season':
								$(".seasonField").show();
								$(".seasonHideField").hide();
								break;
						}
					})
				}
			}


			//Update labels as needed
			if (Pika.MaterialsRequest.authorLabels){
				if (Pika.MaterialsRequest.authorLabels[selectedFormat]) {
					$("#authorFieldLabel").html(Pika.MaterialsRequest.authorLabels[selectedFormat] + ': ');
				}
			}

			if ((hasSpecialFields && Pika.MaterialsRequest.specialFields[selectedFormat] && Pika.MaterialsRequest.specialFields[selectedFormat].indexOf('Article Field') > -1)){
				$("#magazineTitle,#acceptCopyrightYes").addClass('required').attr('aria-required', true);
				$("#acceptCopyrightYes").addClass('required').attr('aria-required', true);
				$("#copyright").show();
				$("#supplementalDetails").hide();
				$("#titleLabel").html("Article Title <span class='required-input'>*</span>");
			}else{
				$("#magazineTitle,#acceptCopyrightYes").removeClass('required').attr('aria-required', false);
				$("#copyright").hide();
				$("#supplementalDetails").show();
				$("#titleLabel").html("Title <span class='required-input'>*</span>");
			}

		},

		updateHoldOptions: function(){
			var placeHold = $("input[name=placeHoldWhenAvailable]:checked").val() == 1 || $("input[name=illItem]:checked").val() == 1;
			// comparison needed to change placeHold to a boolean
			if (placeHold){
				$("#pickupLocationField").show();
				if ($("#pickupLocation").find("option:selected").val() == 'bookmobile'){
					$("#bookmobileStopField").show();
				}else{
					$("#bookmobileStopField").hide();
				}
			}else{
				$("#bookmobileStopField,#pickupLocationField").hide();
			}
		}

		// no uses for this found. plb 12-29-2017
		// printRequestBody: function(){
		// 	$("#request_details_body").printElement();
		// }
	};
}(Pika.MaterialsRequest || {}));