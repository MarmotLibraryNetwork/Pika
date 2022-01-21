/**
 * Created by mark on 1/24/14.
 */
Pika.GroupedWork = (function(){
	return {
		hasTableOfContentsInRecord: false,

		clearUserRating: function (groupedWorkId){
			var url = '/GroupedWork/' + groupedWorkId + '/AJAX?method=clearUserRating';
			$.getJSON(url, function(data){
				if (data.result == true){
					$('.rate' + groupedWorkId).find('.ui-rater-starsOn').width(0);
					$('#myRating' + groupedWorkId).hide();
					Pika.showMessage('Success', data.message, true);
				}else{
					Pika.showMessage('Sorry', data.message);
				}
			});
			return false;
		},

		clearNotInterested: function (notInterestedId){
			var url = '/GroupedWork/' + notInterestedId + '/AJAX?method=clearNotInterested';
			$.getJSON(
					url, function(data){
						if (data.result == false){
							Pika.showMessage('Sorry', "There was an error updating the title.");
						}else{
							$("#notInterested" + notInterestedId).hide();
						}
					}
			);
		},

		deleteReview: function(id, reviewId){
			Pika.confirm("Are you sure you want to delete this review?", function(){
				var url = '/GroupedWork/' + id + '/AJAX?method=deleteUserReview';
				$.getJSON(url, function(data){
					if (data.result == true){
						$('#review_' + reviewId).hide();
						Pika.showMessage('Success', data.message, true);
					}else{
						Pika.showMessage('Sorry', data.message);
					}
				});
			});
			return false;
		},
        staticPosition: function(sticky)
        {
            var resultsNav = document.getElementById("results-nav-fixed");


        	if(window.pageYOffset > sticky)
			{
				resultsNav.classList.add("sticky")
			}else{
        	    resultsNav.classList.remove("sticky");
            }
            
        },
		getElementPosition: function(elem)
		{
			var location = 0;
			if(elem.offsetParent){
				do{
					location += elem.offsetTop;
					elem = elem.offsetParent;
				} while (elem);
			}
			return location >=0 ? location: 0;
		},
		getGoDeeperData: function (id, dataType){
			var placeholder;
			if (dataType == 'excerpt') {
				placeholder = $("#excerptPlaceholder");
			} else if (dataType == 'avSummary') {
				placeholder = $("#avSummaryPlaceholder");
			} else if (dataType == 'tableOfContents') {
				placeholder = $("#tableOfContentsPlaceholder");
			} else if (dataType == 'authornotes') {
				placeholder = $("#authornotesPlaceholder");
			}
			if (placeholder.hasClass("loaded")) return;
			placeholder.show();
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method': 'getGoDeeperData', dataType:dataType};
			$.getJSON(url, params, function(data) {
				placeholder.html(data.formattedData).addClass('loaded');
			});
		},

		getGoodReadsComments: function (isbn){
			$("#goodReadsPlaceHolder").replaceWith(
				"<iframe id='goodreads_iframe' class='goodReadsIFrame' src='https://www.goodreads.com/api/reviews_widget_iframe?did=DEVELOPER_ID&format=html&isbn=" + isbn + "&links=660&review_back=fff&stars=000&text=000' width='100%' height='400px' frameborder='0'></iframe>"
			);
		},

		reloadEnrichment: function (id){
			Pika.GroupedWork.loadEnrichmentInfo(id, true);
		},

		reloadNovelistData: function(id){
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method': 'reloadNovelistData'};
			$.getJSON(url, params, function(data){
				if(data.success){
					Pika.showMessage("Reloaded NoveList Data", data.message, true, true);
				}else{
					Pika.showMessage("Reloaded NoveList Data", "Could not reload NoveList data", true, true);
				}
			});
		},

		loadEnrichmentInfo: function (id, forceReload) {
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method':'getEnrichmentInfo'};
			if (forceReload !== undefined){
				params['reload'] = true;
			}
			$.getJSON(url, params, function(data) {
					try{
						var seriesData = data.seriesInfo;
						if (seriesData && seriesData.titles.length > 0) {
							seriesScroller = new TitleScroller('titleScrollerSeries', 'Series', 'seriesList');
							$('#seriesInfo').show();
							seriesScroller.loadTitlesFromJsonData(seriesData);
							$('#seriesPanel').show();
						}else{
							$('#seriesPanel').hide();
						}
						var similarTitleData = data.similarTitles;
						if (similarTitleData && similarTitleData.titles.length > 0) {
							morelikethisScroller = new TitleScroller('titleScrollerMoreLikeThis', 'MoreLikeThis', 'morelikethisList');
							$('#moreLikeThisInfo').show();
							morelikethisScroller.loadTitlesFromJsonData(similarTitleData);
						}
						var showGoDeeperData = data.showGoDeeper;
						if (showGoDeeperData) {
							//$('#goDeeperLink').show();
							var goDeeperOptions = data.goDeeperOptions;
							//add a tab before citation for each item
							for (var option in goDeeperOptions){
								if (option == 'excerpt') {
									$("#excerptPanel").show();
								} else if (option == 'avSummary') {
									$("#avSummaryPlaceholder,#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
								} else if (option == 'tableOfContents') {
									$("#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
								} else if (option == 'authorNotes') {
									$('#authornotesPlaceholder,#authornotesPanel').show();
								}
							}
						}
						if (Pika.GroupedWork.hasTableOfContentsInRecord){
							$("#tableofcontentstab_label,#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
						}
						var relatedContentData = data.relatedContent;
						if (relatedContentData && relatedContentData.length > 0) {
							$("#relatedContentPlaceholder").html(relatedContentData);
						}
						var similarTitlesNovelist = data.similarTitlesNovelist;
						if (similarTitlesNovelist && similarTitlesNovelist.length > 0){
							$("#novelisttitlesPlaceholder").html(similarTitlesNovelist);
							$("#novelisttab_label,#similarTitlesPanel").show();
						}

						var similarAuthorsNovelist = data.similarAuthorsNovelist;
						if (similarAuthorsNovelist && similarAuthorsNovelist.length > 0){
							$("#novelistauthorsPlaceholder").html(similarAuthorsNovelist);
							$("#novelisttab_label,#similarAuthorsPanel").show();
						}

						var similarSeriesNovelist = data.similarSeriesNovelist;
						if (similarSeriesNovelist && similarSeriesNovelist.length > 0){
							$("#novelistseriesPlaceholder").html(similarSeriesNovelist);
							$("#novelisttab_label,#similarSeriesPanel").show();
						}

						// Show Explore More Sidebar Section loaded above
						$('.ajax-carousel', '#explore-more-body').parents('.jcarousel-wrapper').show()
								.prev('.sectionHeader').show();
						// Initiate Any Explore More JCarousels
						Pika.initCarousels('.ajax-carousel');

						if (data.novelistPrimaryISBN){
							$('#novelistPrimaryISBN').html(data.novelistPrimaryISBN);
						}

					} catch (e) {
						alert("error loading enrichment: " + e);
					}
				}
			);
		},

		loadReviewInfo: function (id) {
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getReviewInfo";
			$.getJSON(url, function(data) {
				if (data.numSyndicatedReviews == 0){
					$("#syndicatedReviewsPanel").hide();
				}else{
					var syndicatedReviewsData = data.syndicatedReviewsHtml;
					if (syndicatedReviewsData && syndicatedReviewsData.length > 0) {
						$("#syndicatedReviewPlaceholder").html(syndicatedReviewsData);
					}
				}
				if (data.numLibrarianReviews == 0){
					$("#librarianReviewsPanel").hide();
				}else{
					var librarianReviewsData = data.librarianReviewsHtml;
					if (librarianReviewsData && librarianReviewsData.length > 0) {
						$("#librarianReviewPlaceholder").html(librarianReviewsData);
					}
				}

				if (data.numCustomerReviews == 0){
					$("#borrowerReviewsPanel").hide();
				}else{
					var customerReviewsData = data.customerReviewsHtml;
					if (customerReviewsData && customerReviewsData.length > 0) {
						$("#customerReviewPlaceholder").html(customerReviewsData);
					}
				}
			});
		},

		markNotInterested: function (recordId){
			Pika.Account.ajaxLogin(function (){
				var url = '/GroupedWork/' + recordId + '/AJAX?method=markNotInterested';
				$.getJSON(
						url, function(data){
							if (data.result == true){
								Pika.showMessage('Success', data.message);
							}else{
								Pika.showMessage('Sorry', data.message);
							}
						}
				);
				return false;
			});
		},

		removeTag:function(id, tag){
			Pika.confirm("Are you sure you want to remove the tag \"" + tag + "\" from this title?", function(){
				var url = '/GroupedWork/' + id + '/AJAX',
						params = {method:'removeTag', tag: tag};
				$.getJSON(url, params, function(data){
							if (data.result == true){
								Pika.showMessage('Success', data.message);
							}else{
								Pika.showMessage('Sorry', data.message);
							}
						}
				);
				return false;
			});
			return false;
		},

		saveReview: function(id){
			Pika.Account.ajaxLogin(function (){
				var comment = $('#comment' + id).val(),
						rating = $('#rating' + id).val(),
						url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params =  {
							method : 'saveReview'
							,comment : comment
							,rating : rating
						};
				$.getJSON(url, params,
					function(data) {
						if (data.success) {
							if (data.newReview){
								$("#customerReviewPlaceholder").append(data.reviewHtml);
							}else{
								$("#review_" + data.reviewId).replaceWith(data.reviewHtml);
							}
							Pika.closeLightbox();
						} else {
							Pika.showMessage("Error", data.message);
						}
					}
				).fail(Pika.ajaxFail);
			});
			return false;
		},

		saveTag: function(id){
			var tag = $("#tags_to_apply").val(),
					url = "/GroupedWork/" + id + "/AJAX",
					params = {
						method : 'saveTag',
						tag : tag
					};
			$("#saveToList-button").prop('disabled', true);
			$.getJSON(url, params,
					function(data) {
						if (data.success) {
							Pika.showMessage("Success", data.message, 1);
						} else {
							Pika.showMessage("Error adding tags", "There was an unexpected error adding tags to this title.<br>" + data.message);
						}
					}).fail(Pika.ajaxFail);
			return false;
		},

		saveToList: function(id){
			Pika.Account.ajaxLogin(function (){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method':'saveToList'
							,notes:notes
							,listId:listId
						};
				$.getJSON(url, params,
						function(data) {
							if (data.success) {
								Pika.showMessageWithButtons("Added Successfully", data.message, data.buttons);
							} else {
								Pika.showMessage("Error", data.message);
							}
						}
				)
			});
			return false;
		},
	createSeriesList: function(id)
	{
		var form = $("#addListForm"),
				isPublic = form.find("#public").prop("checked"),
				groupedWorkId = id,
				title = form.find("input[name=title]").val(),
				desc = $("#listDesc").val(),
				url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
				params = {
					'method': 'createSeriesList',
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
	saveSeriesToList: function(id)
		{
			Pika.Account.ajaxLogin(function (){
				var listId = $('#addToList-list').val(),
						url   = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method': 'saveSeriesToList'
							, listId: listId
						};
				$.getJSON(url, params,
						function(data){
								if(data.success){
									Pika.showMessageWithButtons("Added Successfully", data.message, data.buttons);
								}	else{
									Pika.showMessage("Error", data.message);
								}
						}
				)
			});
				return false;
		},

		sendEmail: function (id){
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {
						'method': 'sendEmail'
						, from: $('#from').val()
						, to: $('#to').val()
						, message: $('#message').val()
						, related_record: $('#related_record').val()
						, 'g-recaptcha-response': (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : false
					};
			$.getJSON(url, params,
					function (data){
						if (data.result){
							Pika.showMessage("Success", data.message);
						}else{
							Pika.showMessage("Error", data.message);
						}
					}
			).fail(Pika.ajaxFail);
			return false;
		},

		sendSeriesEmail: function(id){

			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {
						'method': 'sendSeriesEmail'
						, from: $('#from').val()
						, to: $('#to').val()
						, message: $('#message').val()
						, 'g-recaptcha-response': (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : false
					};
			$.getJSON(url, params,
					function (data){
				if (data.result){
					Pika.showMessage("Success", data.message);
				}else{
					Pika.showMessage("Error", data.message);
				}
			}
			).fail(Pika.ajaxFail);
			return false;
		},

		sendSMS: function (id){
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {
						'method': 'sendSMS'
						, provider: $('#provider').val()
						, sms_phone_number: $('#sms_phone_number').val()
						, related_record: $('#related_record').val()
						, 'g-recaptcha-response': (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : false
					};
			$.getJSON(url, params,
					function (data){
						if (data.result){
							Pika.showMessage("Success", data.message);
						}else{
							Pika.showMessage("Error", data.message);
						}
					}
			).fail(Pika.ajaxFail);
			return false;
		},

		showGroupedWorkInfo: function(id, browseCategoryId){
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getWorkInfo";
			if (browseCategoryId !== undefined){
				url += "&browseCategoryId=" + browseCategoryId;
			}
			Pika.loadingMessage();
			$.getJSON(url, function(data){
				Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
			}).fail(Pika.ajaxFail);
			return false;
		},

		forceRegrouping: function (id){
			return this.basicShowMessageReloadOnSuccess('forceRegrouping', id);
		},

		forceReindex: function (id){
			return this.basicShowMessageReloadOnSuccess('forceReindex', id);
		},

		reloadCover: function (id){
			return this.basicShowMessageReloadOnSuccess('reloadCover', id);
		},

		reloadIslandora: function(id){
			return this.basicShowMessageReloadOnSuccess('reloadIslandora', id);
		},

		basicShowMessageReloadOnSuccess: function(method, id){
			$.getJSON("/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=" + method, function (data){
						if (data.success) {
							Pika.showMessage("Success", data.message, true, true);
						} else {
							Pika.showMessage("Error", data.message);
						}
					}
			);
			return false;
		},

		showEmailForm: function(trigger, id){
			return Pika.Account.ajaxLightbox("/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getEmailForm", false, trigger);
			// return this.basicAjaxHandler('getEmailForm', id, trigger);
		},

		seriesEmailForm: function(trigger, id){
			return Pika.Account.ajaxLightbox("/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getSeriesEmailForm", false, trigger);
		},

		showReviewForm: function(trigger, id){
			return this.basicAjaxHandler('getReviewForm', id, trigger);
		},
		showCreateSeriesListForm: function(trigger, id){
			return this.basicAjaxHandler('getCreateSeriesForm', id, trigger);
		},
		showSaveToListForm: function (trigger, id){
			return this.basicAjaxHandler('getSaveToListForm', id, trigger);
		},
		showSaveMultipleToListForm: function (trigger, ids){
			return this.basicAjaxHandler('getSaveMultipleToListForm', ids, trigger)
		},
		showSaveSeriesToListForm: function (trigger, id){
			return this.basicAjaxHandler('getSaveSeriesToListForm', id, trigger);
		},

		showSmsForm: function(trigger, id){
			return this.basicAjaxHandler('getSMSForm', id, trigger);
		},

		showTagForm: function(trigger, id){
			return this.basicAjaxHandler('getAddTagForm', id, trigger);
		},

		addSelectedToList: function(){
			Pika.Account.ajaxLogin(function(){
				if ($(".checkbox-results").is(":checked")){
					var gwIds = [];
					$(".checkbox-results:checked").each(function (){
						gwIds.push(this.id.replace(/select_/g, ''));
					});
						return Pika.GroupedWork.showSaveMultipleToListForm(this, gwIds);
				}else{

				}
			});
			return false;
		},
		saveSelectedToList: function(ids){
			Pika.Account.ajaxLogin(function (){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = "/GroupedWork/AJAX",
						params = {
							'method':'saveSelectedToList'
							,notes:notes
							,listId:listId
							,ids: ids
						};
				$.getJSON(url, params,
						function(data) {
							if (data.success) {
								Pika.showMessageWithButtons("Added Successfully", data.message, data.buttons);
							} else {
								Pika.showMessage("Error", data.message);
							}
						}
				)
			});
			return false;
		},

		basicAjaxHandler: function(method, id, trigger){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				$.getJSON("/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=" + method, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				}).fail(Pika.ajaxFail);
			}, $(trigger));
			return false;
		},
		showBookbag: function(trigger){

			if($(".checkbox-results").is(":checked")){

				var checked = $('.checkbox-results:checked').length;
				$(".bookbag-container").show();
				if(!$(".bookbag").hasClass("bounce")){
					$(".bookbag").addClass("bounce");
				}

				$(".bookbag").html("<span class='itemCheckCount'>" + checked +"</span>");

				var gwIds = [];
				$(".checkbox-results:checked").each(function(){
					 gwIds.push(this.id.replace(/select_/g, ''));
				});
						params = {
							'method': 'getTitles',
							'ids' : gwIds
						};
					$.getJSON("/GroupedWork/AJAX", params, function(data){
						$("#cartList").empty();
						jQuery.each(data.titles,function(){
							var removeId = "remove_" + this.id;
							var append = '<li><div class="row"><div class="col-xs-1"><span class="remove" id="'+ removeId +'">x</span></div><div class="col-xs-10">' + this.title + '</div></div></li>';
						$("#cartList").append(append);
						});
					}).fail(Pika.ajaxFail);
			}else{
				$(".cart-container").addClass("cartIn");
				$(".bookbag-container").fadeOut();
				$(".bookbag").removeClass("open");
				$("#cartList").empty();
				$(".bookbag").removeClass("bounce");
			}
		},
		openBookbag: function(trigger){

			$(".cart-container").toggleClass("cartIn");
			$(".bookbag").toggleClass("open");
			
		},

	};
}(Pika.GroupedWork || {}));