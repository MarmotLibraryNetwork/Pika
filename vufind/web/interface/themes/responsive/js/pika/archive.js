/**
 * Created by mark on 12/10/2015.
 */
Pika.Archive = (function(){
	var date = new Date();
	date.setTime(date.getTime() + (1 /*days*/ * 24 * 60 * 60 * 1000));
	expires = "; expires=" + date.toGMTString();
	document.cookie = encodeURIComponent('exhibitNavigation') + "=" + encodeURIComponent(0) + expires + "; path=/";
	document.cookie = encodeURIComponent('collectionPid') + "=" + encodeURIComponent('') + expires + "; path=/";
	// document.cookie = encodeURIComponent('exhibitInAExhibitParentPid') + "=" + encodeURIComponent('') + expires + "; path=/";

	return {
		archive_map: null,
		archive_info_window: null,
		curPage: 1,
		markers: [],
		geomarkers: [],
		sort: 'title',
		openSeaDragonViewer: null,
		pageDetails: [],
		multiPage: false,
		allowPDFView: true,
		activeBookViewer: 'jp2',
		activeBookPage: null,
		activeBookPid: null,

		// Archive Collection Display Mode (different from search)
		displayMode: 'list', // default display Mode for collections
		displayModeClasses: { // browse mode to css class correspondence
			covers: 'home-page-browse-thumbnails',
			list: ''
		},

		getPreferredDisplayMode: function(){
			if (!Globals.opac && Pika.hasLocalStorage()){
				temp = window.localStorage.getItem('archiveCollectionDisplayMode');
				if (Pika.Archive.displayModeClasses.hasOwnProperty(temp)) {
					Pika.Archive.displayMode = temp; // if stored value is empty or a bad value, fall back on default setting ("null" is returned from local storage when not set)
				}
			}
		},

		toggleDisplayMode : function(selectedMode){
			var mode = this.displayModeClasses.hasOwnProperty(selectedMode) ? selectedMode : this.displayMode; // check that selected mode is a valid option
			this.displayMode = mode; // set the mode officially
			this.curPage = 1; // reset js page counting
			if (!Globals.opac && Pika.hasLocalStorage() ) { // store setting in browser if not an opac computer
				window.localStorage.setItem('archiveCollectionDisplayMode', this.displayMode);
			}
			if (mode == 'list') $('#hideSearchCoversSwitch').show(); else $('#hideSearchCoversSwitch').hide();
			this.ajaxReloadCallback()
		},

		ajaxReloadCallback: function () {
			// Placeholder for the function that will be call when the display mode is toggled.
		},

		toggleShowCovers: function(showCovers){
			Pika.Account.showCovers = showCovers;
			if (!Globals.opac && Pika.hasLocalStorage()) { // store setting in browser if not an opac computer
				window.localStorage.setItem('showCovers', this.showCovers ? 'on' : 'off');
			}
			this.ajaxReloadCallback()
		},


		openSeadragonViewerSettings: function(){
			return {
				"id": "pika-openseadragon",
				"prefixUrl": Globals.encodedRepositoryUrl + "\/sites\/all\/libraries\/openseadragon\/images\/",
				"debugMode": false,
				"djatokaServerBaseURL": Globals.encodedRepositoryUrl + "\/AJAX\/DjatokaResolver",
				"tileSize": 256,
				"tileOverlap": 0,
				"animationTime": 1.5,
				"blendTime": 0.1,
				"alwaysBlend": false,
				"autoHideControls": 1,
				"immediateRender": true,
				"wrapHorizontal": false,
				"wrapVertical": false,
				"wrapOverlays": false,
				"panHorizontal": 1,
				"panVertical": 1,
				"minZoomImageRatio": 0.35,
				"maxZoomPixelRatio": 2,
				"visibilityRatio": 0.5,
				"springStiffness": 5,
				"imageLoaderLimit": 5,
				"clickTimeThreshold": 300,
				"clickDistThreshold": 5,
				"zoomPerClick": 2,
				"zoomPerScroll": 1.2,
				"zoomPerSecond": 2,
				"showNavigator": 1,
				"defaultZoomLevel": 0,
				"homeFillsViewer": false
			}
		},

		changeActiveBookViewer: function(viewerName, pagePid){
			this.activeBookViewer = viewerName;
			// $('#view-toggle').children(".btn .active").removeClass('active');
			if (viewerName == 'pdf' && this.allowPDFView){
				$('#view-toggle-pdf').prop('checked', true);
						// .parent('.btn').addClass('active');
				$("#view-pdf").show();
				$("#view-image").hide();
				$("#view-transcription").hide();
				$("#view-audio").hide();
				$("#view-video").hide();
			}else if (viewerName == 'image' || (viewerName == 'pdf' && !this.allowPDFView)){
				$('#view-toggle-image').prop('checked', true);
						// .parent('.btn').addClass('active');
				$("#view-image").show();
				$("#view-pdf").hide();
				$("#view-transcription").hide();
				$("#view-audio").hide();
				$("#view-video").hide();
				this.activeBookViewer = 'image';
			}else if (viewerName == 'transcription'){
				$('#view-toggle-transcription').prop('checked', true);
					// .parent('.btn').addClass('active');
				$("#view-transcription").show();
				$("#view-pdf").hide();
				$("#view-image").hide();
				$("#view-audio").hide();
				$("#view-video").hide();
			}else if (viewerName == 'audio'){
				$('#view-toggle-transcription').prop('checked', true);
				// .parent('.btn').addClass('active');
				$("#view-audio").show();
				$("#view-pdf").hide();
				$("#view-image").hide();
				$("#view-transcription").hide();
				$("#view-video").hide();
			}else if (viewerName == 'video'){
				$('#view-toggle-transcription').prop('checked', true);
				// .parent('.btn').addClass('active');
				$("#view-video").show();
				$("#view-pdf").hide();
				$("#view-image").hide();
				$("#view-transcription").hide();
				$("#view-audio").hide();

			}
			return this.loadPage(pagePid);
		},

		clearCache:function(id){
			var url = "/Archive/AJAX?id=" + encodeURI(id) + "&method=clearCache";
			Pika.loadingMessage();
			$.getJSON(url, function(data){
				if (data.success) {
					Pika.showMessage("Cache Cleared Successfully", data.message, 2000); // auto-close after 2 seconds.
				} else {
					Pika.showMessage("Error", data.message);
				}
			}).fail(Pika.ajaxFail);
			return false;
		},

		// initializeOpenSeadragon: function(viewer){
		//
		// },

		getMoreExhibitResults: function(exhibitPid, reloadHeader){
			this.curPage = this.curPage +1;
			if (typeof reloadHeader == 'undefined') {
				reloadHeader = 0;
			}
			if (reloadHeader) {
				$("#exhibit-results-loading").show();
				this.curPage = 1;
			}
			var url = "/Archive/AJAX?method=getRelatedObjectsForExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort + '&archiveCollectionView=' + this.displayMode + '&showCovers=' + Pika.Account.showCovers;
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").hide().html(data.relatedObjects).fadeIn('slow');
					}else{
						$("#nextInsertPoint").hide().replaceWith(data.relatedObjects).fadeIn('slow');
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		getMoreMapResults: function(exhibitPid, placePid, showTimeline){
			this.curPage = this.curPage +1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid + "&page=" + this.curPage + "&sort=" + this.sort + "&showTimeline=" + showTimeline;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter="+$(this).val();
			});
			url = url + "&reloadHeader=0";

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		getMoreTimelineResults: function(exhibitPid){
			this.curPage = this.curPage +1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter="+$(this).val();
			});
			url = url + "&reloadHeader=0";

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		getMoreScrollerResults: function(pid){
			this.curPage = this.curPage +1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid + "&page=" + this.curPage + "&sort=" + this.sort;

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
			return false;
		},

		handleMapClick: function(markerIndex, exhibitPid, placePid, label, redirect, showTimeline){
			$("#exhibit-results-loading").show();
			this.archive_info_window.setContent(label);
			if (markerIndex >= 0){
				this.archive_info_window.open(this.archive_map, this.markers[markerIndex]);
			}

			if (redirect != "undefined" && redirect === true){
				var newUrl = Pika.buildUrl(document.location.origin + document.location.pathname, 'placePid', placePid);
				newUrl = Pika.buildUrl(newUrl, 'style', 'map');
				document.location.href = newUrl;
			}
			if (showTimeline == "undefined"){
				showTimeline = true;
			}
			$.getJSON("/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid + "&showTimeline=" + showTimeline, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			var stateObj = {
				marker: markerIndex,
				exhibitPid: exhibitPid,
				placePid: placePid,
				label: label,
				showTimeline: showTimeline,
				page: "MapExhibit"
			};
			var newUrl = Pika.buildUrl(document.location.origin + document.location.pathname, 'placePid', placePid);
			var currentParameters = Pika.getQuerystringParameters();
			if (currentParameters["style"] != undefined){
				newUrl = Pika.buildUrl(newUrl, 'style', currentParameters["style"]);
			}
			//Push the new url, but only if we aren't going back where we just were.
			if (document.location.href != newUrl){
				history.pushState(stateObj, label, newUrl);
			}
			return false;
		},

		handleTimelineClick: function(exhibitPid){
			$("#exhibit-results-loading").show();

			$.getJSON("/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			return false;
		},

		handleCollectionScrollerClick: function(pid){
			$("#exhibit-results-loading").show();

			$.getJSON("/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			return false;
		},

		handleBookClick: function(bookPid, pagePid, bookViewer) {
			// Load specified page & viewer
			//Loading message
			//Load Page  set-up
			Pika.Archive.activeBookPid = bookPid;
			Pika.Archive.changeActiveBookViewer(bookViewer, pagePid);

			// store in browser history
			var stateObj = {
				bookPid: bookPid,
				pagePid: pagePid,
				viewer: bookViewer,
				page: 'Book'
			},
					newUrl = Pika.buildUrl(document.location.origin + document.location.pathname, 'bookPid', bookPid),
					newUrl = Pika.buildUrl(newUrl, 'pagePid', pagePid),
					newUrl = Pika.buildUrl(newUrl, 'viewer', bookViewer);
			//Push the new url, but only if we aren't going back where we just were.
			if (document.location.href != newUrl){
				history.pushState(stateObj, '', newUrl);
			}
			return false;

		},

		reloadMapResults: function(exhibitPid, placePid, reloadHeader,showTimeline){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid + "&page=" + this.curPage + "&sort=" + this.sort + '&archiveCollectionView=' + this.displayMode + '&showCovers=' + Pika.Account.showCovers + '&showTimeline=' + showTimeline;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter="+$(this).val();
			});
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		reloadTimelineResults: function(exhibitPid, reloadHeader){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort + '&archiveCollectionView=' + this.displayMode + '&showCovers=' + Pika.Account.showCovers;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter="+$(this).val();
			});
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		reloadScrollerResults: function(pid, reloadHeader){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = "/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid + "&page=" + this.curPage + "&sort=" + this.sort + '&archiveCollectionView=' + this.displayMode + '&showCovers=' + Pika.Account.showCovers;
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		loadExploreMore: function(pid){
			$.getJSON("/Archive/AJAX?id=" + encodeURI(pid) + "&method=getExploreMoreContent", function(data){
				if (data.success){
					$("#explore-more-body").html(data.exploreMore);
					Pika.initCarousels("#explore-more-body .panel-collapse.in .jcarousel"); // Only initialize browse categories in open accordions
				}
			}).fail(Pika.ajaxFail);
		},

		loadMetadata: function(pid, secondaryId){
			var url = "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getMetadata";
			if (secondaryId !== undefined){
				url += "&secondaryId=" + secondaryId;
			}
			var metadataTarget = $('#archive-metadata').html("Please wait while we load information about this object...");
			$.getJSON(url, function(data) {
				if (data.success) {
					metadataTarget.html(data.metadata);
				}
			}).fail(
					function(){metadataTarget.html("Could not load metadata.")}
			);
		},

		/**
		 * Load a new page into the active viewer
		 *
		 * @param pid
		 */
		loadPage: function(pid){
			if (pid == null){
				return false;
			}
			var pageChanged = false;
			if (this.activeBookPage != pid){
				pageChanged = true;
				this.curPage = this.pageDetails[pid]['index'];
			}
			this.activeBookPage = pid;
			// console.log('Page: '+ this.activeBookPage, 'Active Viewer : '+ this.activeBookViewer);
			if (this.pageDetails[pid]['transcript'] == ''){
				$('#view-toggle-transcription').parent().hide();
				if (this.activeBookViewer == 'transcription') {
					this.changeActiveBookViewer('image', pid);
					return false;
				}
			}else{
				$('#view-toggle-transcription').parent().show();
			}
			if (this.pageDetails[pid]['pdf'] == ''){
				$('#view-toggle-pdf').parent().hide();
			}else{
				$('#view-toggle-pdf').parent().show();
			}
			if (this.pageDetails[pid]['jp2'] == ''){
				$('#view-toggle-image').parent().hide();
			}else{
				$('#view-toggle-image').parent().show();
			}
			if (this.pageDetails[pid]['audio'] == ''){
				$('#view-toggle-audio').parent().hide();
			}else{
				$('#view-toggle-audio').parent().show();
			}
			if (this.pageDetails[pid]['video'] == ''){
				$('#view-toggle-video').parent().hide();
			}else{
				$('#view-toggle-video').parent().show();
			}

			if (this.activeBookViewer == 'pdf') {
				// console.log('PDF View called');
				$('#view-pdf').html(
						$('<object />').attr({
							type: 'application/pdf',
							data: this.pageDetails[pid]['pdf'],
							class: 'book-pdf' // Class that styles/sizes the PDF page
						})
				);
			}else if(this.activeBookViewer == 'transcription') {
				// console.log('Transcript Viewer called');
				var transcriptIdentifier = this.pageDetails[pid]['transcript'];
				var url = "/Archive/AJAX?transcriptId=" + encodeURI(transcriptIdentifier) + "&method=getTranscript";
				var transcriptionTarget = $('#view-transcription');
				transcriptionTarget.html("Loading Transcript, please wait.");
				$.getJSON(url, function(data) {
					if (data.success) {
						transcriptionTarget.html(data.transcript);
					}
				}).fail(
					function(){transcriptionTarget.html("Could not load Transcript.")}
				);

				// var islandoraURL = this.pageDetails[pid]['transcript'];
				// var reverseProxy = islandoraURL.replace(/([^\/]*)(?=\/islandora\/)/, location.host);
				// // reverseProxy = reverseProxy.replace('https', 'http'); // TODO: remove, for local instance only (no https)
				// // console.log('Fetching: '+reverseProxy);
				//
				// $('#view-transcription').load(reverseProxy);
			}else if (this.activeBookViewer == 'image'){
				var tile = new OpenSeadragon.DjatokaTileSource(
						Globals.url + "/AJAX/DjatokaResolver",
						this.pageDetails[pid]['jp2'],
						Pika.Archive.openSeadragonViewerSettings()
				);
				if (!$('#pika-openseadragon').hasClass('processed')) {
					$('#pika-openseadragon').addClass('processed');
					settings = Pika.Archive.openSeadragonViewerSettings();
					settings.tileSources = new Array();
					settings.tileSources.push(tile);
					Pika.Archive.openSeaDragonViewer = new OpenSeadragon(settings);
				}else{
					//Pika.Archive.openSeadragonViewerSettings.tileSources = new Array();
					//Pika.Archive.openSeaDragonViewer.close();
					Pika.Archive.openSeaDragonViewer.open(tile);
				}
				//Pika.Archive.openSeaDragonViewer.viewport.fitVertically(true);
			}else if(this.activeBookViewer == 'audio') {
				$('#view-audio').show();
				$('#audio-player-src').attr('src', this.pageDetails[pid]['audio']);
				var audioPlayer = document.getElementById("audio-player");
				audioPlayer.load();
				//audioPlayer.play();
			}else if(this.activeBookViewer == 'video') {
				$('#view-video').show();
				$('#video-player-src').attr('src', this.pageDetails[pid]['video']);
				var videoPlayer = document.getElementById("video-player");
				videoPlayer.load();
			}
			if (pageChanged && this.multiPage){
				var numSectionsShown = 0;
				if (this.pageDetails[pid]['transcript'] == ''){
					$('#view-toggle-transcription').parent().hide();
				}else{
					$('#view-toggle-transcription').parent().show();
					numSectionsShown++;
				}
				if (this.pageDetails[pid]['pdf'] == ''){
					$('#view-toggle-pdf').parent().hide();
				}else{
					$('#view-toggle-pdf').parent().show();
					imageOnlyShown = false;
					numSectionsShown++;
				}
				if (this.pageDetails[pid]['jp2'] == ''){
					$('#view-toggle-image').parent().hide();
				}else{
					$('#view-toggle-image').parent().show();
					numSectionsShown++;
				}
				if (this.pageDetails[pid]['audio'] == ''){
					$('#view-toggle-audio').parent().hide();
				}else{
					$('#view-toggle-audio').parent().show();
					numSectionsShown++;
				}
				if (this.pageDetails[pid]['video'] == ''){
					$('#view-toggle-video').parent().hide();
				}else{
					$('#view-toggle-video').parent().show();
					numSectionsShown++;
				}
				if (numSectionsShown <= 1){
					$('#view-toggle').hide();
				}else{
					$('#view-toggle').show();
				}

				this.loadMetadata(this.activeBookPid, pid);
				//$("#downloadPageAsPDF").href = "/Archive/" + pid + "/DownloadPDF";
				url = "/Archive/AJAX?method=getAdditionalRelatedObjects&id=" + pid;
				var additionalRelatedObjectsTarget = $("#additional-related-objects");
				additionalRelatedObjectsTarget.html("");
				$.getJSON(url, function(data) {
					if (data.success) {
						additionalRelatedObjectsTarget.html(data.additionalObjects);
					}
				});

				var pageScroller = $("#book-sections .jcarousel");
				if (pageScroller){
					pageScroller.jcarousel('scroll', this.curPage - 1, true);
					$('#book-sections li').removeClass('active');
					$('#book-sections .jcarousel li:eq(' + (this.curPage - 1) + ')').addClass('active');
				}
			}
			//alert("Changing display to pid " + pid + " active viewer is " + this.activeBookViewer)
			return false;
		},

		nextRandomObject: function(pid){
			var url = "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getNextRandomObject";
			$.getJSON(url, function(data){
				$('#randomImagePlaceholder').html(data.image);
			}).fail(Pika.ajaxFail);
			return false;
		},

		setForExhibitInAExhibitNavigation : function (exhibitInAExhibitParentPid) {
			var date = new Date();
			date.setTime(date.getTime() + (1 /*days*/ * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
			document.cookie = encodeURIComponent('exhibitInAExhibitParentPid') + "=" + encodeURIComponent(exhibitInAExhibitParentPid) + expires + "; path=/";
		},

		setForExhibitNavigation : function (recordIndex, page, collectionPid) {
			var date = new Date();
			date.setTime(date.getTime() + (1 /*days*/ * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
			if (typeof recordIndex != 'undefined') {
				document.cookie = encodeURIComponent('recordIndex') + "=" + encodeURIComponent(recordIndex) + expires + "; path=/";
			}
			if (typeof page != 'undefined') {
				document.cookie = encodeURIComponent('page') + "=" + encodeURIComponent(page) + expires + "; path=/";
			}
			if (typeof collectionPid != 'undefined') {
				document.cookie = encodeURIComponent('collectionPid') + "=" + encodeURIComponent(collectionPid) + expires + "; path=/";
			}
			document.cookie = encodeURIComponent('exhibitNavigation') + "=" + encodeURIComponent(1) + expires + "; path=/";
		},

		showBrowseEntityFilterPopup: function(exhibitPid, facetName, title){
			var url = "/Archive/AJAX?id=" + encodeURI(exhibitPid) + "&method=getEntityFacetValuesForExhibit&facetName=" + encodeURI(facetName);
			Pika.loadingMessage();
			$.getJSON(url, function(data){
				Pika.showMessage(title, data.modalBody);
			}).fail(Pika.ajaxFail);
			return false;
		},

		showBrowseFilterPopup: function(exhibitPid, facetName, title){
			var url = "/Archive/AJAX?id=" + encodeURI(exhibitPid) + "&method=getFacetValuesForExhibit&facetName=" + encodeURI(facetName);
			Pika.loadingMessage();
			$.getJSON(url, function(data){
				Pika.showMessage(title, data.modalBody);
			}).fail(Pika.ajaxFail);
			return false;
		},

		showObjectInPopup: function(pid, recordIndex, page){
			var url = "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getObjectInfo";
					// (typeof collectionSearchId == 'undefined' ? '' : '&collectionSearchId=' + encodeURI(collectionSearchId)) +
					// (typeof recordIndex == 'undefined' ? '' : '&recordIndex=' + encodeURI(recordIndex));
			Pika.loadingMessage();
			this.setForExhibitNavigation(recordIndex, page);

			$.getJSON(url, function(data){
				Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
			}).fail(Pika.ajaxFail);
			return false;
		},

		showSaveToListForm: function (trigger, id){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Archive/" + id + "/AJAX?method=getSaveToListForm";
				$.getJSON(url, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
				}, $(trigger));
			return false;
		},

		saveToList: function(id){
			if (Globals.loggedIn){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = "/Archive/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method':'saveToList'
							,notes:notes
							,listId:listId
						};
				$.getJSON(url, params,
						function(data) {
							if (data.success) {
								Pika.showMessage("Added Successfully", data.message, 2000); // auto-close after 2 seconds.
							} else {
								Pika.showMessage("Error", data.message);
							}
						}
				).fail(Pika.ajaxFail);
			}
			return false;
		},

	}

}(Pika.Archive || {}));