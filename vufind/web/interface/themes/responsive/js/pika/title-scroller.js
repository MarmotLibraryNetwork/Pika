/**
 * Create a title scroller object for display
 * 
 * @param scrollerId - the id of the scroller which will hold the titles
 * @param scrollerShortName
 * @param container - a container to display if any titles are found
 * @param autoScroll - whether or not the selected title should change automatically
 * @param style - The style of the scroller:  vertical, horizontal, single or text-list
 * @return
 */
function TitleScroller(scrollerId, scrollerShortName, container,
		autoScroll, style, tabSelect) {
	this.scrollerTitles = [];
	this.currentScrollerIndex = 0;
	this.numScrollerTitles = 0;
	this.scrollerId = scrollerId;
	this.scrollerShortName = scrollerShortName;
	this.container = container;
	this.scrollInterval = 0;
	this.swipeInterval = 5;
	this.autoScroll = (typeof autoScroll == "undefined") ? false : autoScroll;
	this.style = (typeof style == "undefined") ? 'horizontal' : style;
	this.tabSelect = (typeof tabSelect == "undefined") ? false: tabSelect;
}

TitleScroller.prototype.loadTitlesFrom = function(jsonUrl) {
	jsonUrl = decodeURIComponent(jsonUrl);
	var scroller = this,
			scrollerBody = $('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody");
	scrollerBody.hide();
	$("#titleScrollerSelectedTitle" + this.scrollerShortName+",#titleScrollerSelectedAuthor" + this.scrollerShortName).html("");
	$(".scrollerLoadingContainer").show();
	$.getJSON(jsonUrl, function(data) {
		scroller.loadTitlesFromJsonData(data);
	}).fail(function(){
		scrollerBody.html("Unable to load titles. Please try again later.").show();
		$(".scrollerLoadingContainer").hide();
	});
};

TitleScroller.prototype.loadTitlesFromJsonData = function(data) {
	var scroller = this,
			scrollerBody = $('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody");
	try {
		if (data.error) throw {description:data.error}; // throw exceptions for server error messages.
		if (data.titles.length == 0){
			scrollerBody.html("No titles were found for this list. Please try again later.");
			$('#' + this.scrollerId + " .scrollerBodyContainer .scrollerLoadingContainer").hide();
			scrollerBody.show();
		}else{
			scroller.scrollerTitles = [];
			var i = 0;
			// TODO: try direct assignment instead of loop. don't see the need to loop, other than resetting key. plb
			$.each(data.titles, function(key, val) {
				scroller.scrollerTitles[i++] = val;
			});
			if (scroller.container && data.titles.length > 0) {
				$("#" + scroller.container).fadeIn();
			}
			scroller.numScrollerTitles = data.titles.length;
			if (this.style == 'horizontal' || this.style == 'vertical'){
				// vertical or horizontal widgets should start in the middle of the data. plb 11-24-2014
				scroller.currentScrollerIndex = data.currentIndex;
			}else{
				scroller.currentScrollerIndex = 0;
			}
			//console.log('current index is : '+scroller.currentScrollerIndex);
			TitleScroller.prototype.updateScroller.call(scroller);
		}
	} catch (err) {
		//alert("error loading titles from data " + err.description);
		if (scrollerBody != null){
			scrollerBody.html("<div class='alert alert-warning'>Error loading titles from data : '" + err.description + "' Please try again later.</div>").show();
			$(".scrollerLoadingContainer").hide();
		}
		//else{
		//	//alert("Could not find scroller body for " + this.scrollerId);
		//}
	}
};

TitleScroller.prototype.loadIssuesFromAjax = function(jsonUrl){
	var scroller = this,
			scrollerBody = $('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody");
	jsonUrl = decodeURIComponent(jsonUrl);
	scrollerBody.hide();
	$("#titleScrollerSelectedTitle" + this.scrollerShortName + ",#titleScrollerSelectedAuthor" + this.scrollerShortName).html("");
	$(".scrollerLoadingContainer").show();
	$.getJSON(jsonUrl, function (data){
		if (data.error) throw {description: data.error};
		if (data.length == 0){
			ScrollerBody.html("No issues were found for this magazine. Please try again later");
			$('#' + this.scrollerId + " .scrollerBodyContainer .scrollerLoadingContainer").hide();
			scrollerBody.show();
		}else{
			scroller.scrollerTitles = [];
			var i = 0;
			$.each(data, function (key, val){
				scroller.scrollerTitles[i++] = val;
			});
			if (scroller.container && data.length > 0){
				$("#" + scroller.container).fadeIn();
			}
			scroller.numScrollerTitles = data.length;
			if (this.style == 'horizontal' || this.style == 'vertical'){
				// vertical or horizontal widgets should start in the middle of the data. plb 11-24-2014
				scroller.currentScrollerIndex = data.currentIndex;
			}else{
				scroller.currentScrollerIndex = 0;
			}
			//console.log('current index is : '+scroller.currentScrollerIndex);
			TitleScroller.prototype.updateScroller.call(scroller);

		}

	})
			.fail(function(er){

			})
}

TitleScroller.prototype.updateScroller = function() {
	var scrollerBody = $('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody");
	try {
		var scrollerBodyContents = "",
				curScroller = this;
		if (this.style == 'horizontal'){
			for ( var i in this.scrollerTitles) {
				scrollerBodyContents += this.scrollerTitles[i]['formattedTitle'];
			}
			scrollerBody.html(scrollerBodyContents)
					.width(this.scrollerTitles.length * 300) // use a large enough interval to accomodate medium covers sizes
					.waitForImages(function() {
						TitleScroller.prototype.finishLoadingScroller.call(curScroller);
					});
		}else if (this.style == 'vertical'){
			for ( var j in this.scrollerTitles) {
				scrollerBodyContents += this.scrollerTitles[j]['formattedTitle'];
			}
			scrollerBody.html(scrollerBodyContents)
					.height(this.scrollerTitles.length * 131)
					.waitForImages(function() {
						//console.log(scrollerBody);
						TitleScroller.prototype.finishLoadingScroller.call(curScroller);
					});
		}else if (this.style == 'text-list'){
			for ( var i in this.scrollerTitles) {
				scrollerBodyContents += this.scrollerTitles[i]['formattedTextOnlyTitle'];
			}
			scrollerBody.html(scrollerBodyContents)
					.height(this.scrollerTitles.length * 40); //TODO re-calibrate

			TitleScroller.prototype.finishLoadingScroller.call(curScroller);
		}else{
			this.currentScrollerIndex = 0;
			scrollerBody.html(this.scrollerTitles[this.currentScrollerIndex]['formattedTitle']);
			TitleScroller.prototype.finishLoadingScroller.call(this);
		}
		
	} catch (err) {
		//alert("error in updateScroller for scroller " + this.scrollerId + " " + err.description);
		scrollerBody.html("<div class='alert alert-warning'>Error loading titles from data: '" + err + "' Please try again later.</div>").show();
		$(".scrollerLoadingContainer").hide();
	}

};

TitleScroller.prototype.finishLoadingScroller = function() {
	$(".scrollerLoadingContainer").hide();
	//var scrollerBody = $('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody");
	//scrollerBody.show();
	$('#' + this.scrollerId + " .scrollerBodyContainer .scrollerBody").show();
	TitleScroller.prototype.activateCurrentTitle.call(this);
	var curScroller = this;

	// Whether we are hovering over an individual title or not.
	$('.scrollerTitle').on('mouseover', null, {scroller: curScroller}, function() {
		curScroller.hovered = true;
		//console.log('over');
	}).on('mouseout', null, {scroller: curScroller}, function() {
		curScroller.hovered = false;
		//console.log('out');
	});

	// Set initial state.
	curScroller.hovered = false;
	curScroller.interval = (curScroller.interval == 5000 || typeof curScroller.interval == 'undefined') ? 5000 : curScroller.interval;
	curScroller.updateInterval = (curScroller.updateInterval == false || typeof curScroller.updateInterval == 'undefined') ? false : curScroller.updateInterval;


	if (this.autoScroll && this.scrollInterval == 0){
		curScroller.scrolling = true;
		this.scrollInterval = setInterval(function() {
			// Only proceed if not hovering.
			if (!curScroller.hovered && curScroller.scrolling === true) {
				curScroller.scrollToRight();
			}
		}, 5000);
	}


	if(this.tabSelect){
		$('button.scrollerTitle').on("focus", function(){
			let scrollerTitleId = "scrollerTitle" + curScroller.scrollerShortName;
			let scrollerIndex = $(this).attr('id').replace(scrollerTitleId,"");
			let index = scrollerIndex.replace(".scrollerTitle","");
			curScroller.scrollToIndex(index);
		});

	}
};

TitleScroller.prototype.playPauseControl = function (sender, scroller){

	let scrolling = scroller.scrolling;
	let controlWrapper = $('#' + scroller.scrollerId).parent('.titleScrollerWrapper').children('.sliderControls');
		if(scrolling == true){
			scroller.scrolling = false;
			controlWrapper.children('button.pause').addClass('play glyphicon-play');
			controlWrapper.children('button.play').removeClass('pause glyphicon-pause');
			controlWrapper.children('button.play').children('span').html("Resume");
			controlWrapper.children('button.play').attr('aria-label',"Resume");
		}else{
			scroller.scrolling = true;
			controlWrapper.children('button.play').addClass('pause glyphicon-pause');
			controlWrapper.children('button.pause').removeClass('play glyphicon-play');
			controlWrapper.children('button.pause').children('span').html("Pause");
			controlWrapper.children('button.pause').attr('aria-label',"Pause");
		}
}

TitleScroller.prototype.fasterControl = function (scroller){
	let interval = scroller.interval;
	let controlWrapper = $('#' + scroller.scrollerId).parent('.titleScrollerWrapper').children('.sliderControls');
	controlWrapper.children('button.slowDown').prop('disabled', false);
	controlWrapper.children('button.speedUp').attr('aria-label',"Speed Up");
	let newInterval = interval - 500;
			if (newInterval < 1000){
				$(controlWrapper).children('button.speedUp').prop('disabled', true);
				interval = 500;
			}else{
				interval = newInterval;
			}
			scroller.interval = interval;
			clearInterval(scroller.scrollInterval);
			clearInterval(this.scrollInterval);
			scroller.scrollInterval = setInterval(function() {
		// Only proceed if not hovering.
		if (!scroller.hovered && scroller.scrolling === true) {
			scroller.scrollToRight();
		}
	}, interval);
			let intervalHuman = interval/1000;
	controlWrapper.children('button.speedUp').attr('aria-label',"Speed: " + intervalHuman);
}
TitleScroller.prototype.slowerControl = function (scroller){
	let interval = scroller.interval;
	let controlWrapper = $('#' + scroller.scrollerId).parent('.titleScrollerWrapper').children('.sliderControls');
	controlWrapper.children('button.speedUp').prop('disabled', false);
	controlWrapper.children('button.slowDown').attr('aria-label',"Slow Down");
	let newInterval = interval + 500;
	if (newInterval > 10000){
		$(controlWrapper).children('button.slowDown').prop('disabled', true);
		interval = 10500;
	}else{
		interval = newInterval;
	}
	scroller.interval = interval;
	clearInterval(scroller.scrollInterval);
	clearInterval(this.scrollInterval);
	scroller.scrollInterval = setInterval(function() {
		// Only proceed if not hovering.
		if (!scroller.hovered && scroller.scrolling === true) {
			scroller.scrollToRight();
		}
	}, interval);
	let intervalHuman = interval/1000;
	controlWrapper.children('button.slowDown').attr('aria-label',"Speed: " + intervalHuman);
}

TitleScroller.prototype.setVisibleTabIndex = function (scroller){
	var scrollerId = scroller.scrollerId,
	 width = $('#' + scrollerId).width(),
	 coverWidth = $('#scrollerTitle' + scroller.scrollerShortName + scroller.currentScrollerIndex).width(),
	 displayedItems = Math.floor(width / (coverWidth + 30)),
	 currentIndex = scroller.currentScrollerIndex;
	let indexArray = [];
	$('#' + scrollerId + ' button.scrollerTitle').attr('tabindex', '-1');
	if (currentIndex + displayedItems > scroller.numScrollerTitles){
		let overflowTitles = (currentIndex + displayedItems) - scroller.numScrollerTitles;
		for (let n = currentIndex; n < currentIndex + displayedItems - overflowTitles; n++){
			indexArray.push(n);
		}
		/*for (let i = 0; i < overflowTitles; i++){
			indexArray.push(i);
		}*/

	}else{
		for (let n = currentIndex; n < currentIndex + displayedItems; n++){
			indexArray.push(n);
		}
	}
	indexArray.forEach(function (i){
		$('#scrollerTitle' + scroller.scrollerShortName + i).attr('tabindex', '0');
	});
};

TitleScroller.prototype.scrollToRight = function (){
	this.currentScrollerIndex++;
	if (this.currentScrollerIndex > this.numScrollerTitles - 1)
		this.currentScrollerIndex = 0;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.scrollToLeft = function() {
	this.currentScrollerIndex--;
	if (this.currentScrollerIndex < 0)
		this.currentScrollerIndex = this.numScrollerTitles - 1;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.swipeToRight = function(customSwipeInterval) {
	customSwipeInterval  = (typeof customSwipeInterval === 'undefined') ? this.swipeInterval : customSwipeInterval;
	this.currentScrollerIndex -= customSwipeInterval; // swipes progress the opposite of scroll buttons
	if (this.currentScrollerIndex < 0)
		this.currentScrollerIndex = this.numScrollerTitles - 1;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.swipeToLeft = function (customSwipeInterval) {
	customSwipeInterval  = (typeof customSwipeInterval === 'undefined') ? this.swipeInterval : customSwipeInterval;
	this.currentScrollerIndex += customSwipeInterval; // swipes progress the opposite of scroll buttons
	if (this.currentScrollerIndex > this.numScrollerTitles - 1)
		this.currentScrollerIndex = 0;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.swipeUp = function(customSwipeInterval) {
	customSwipeInterval = (typeof customSwipeInterval === 'undefined') ? this.swipeInterval : customSwipeInterval;
	this.currentScrollerIndex -= customSwipeInterval;
	if (this.currentScrollerIndex < 0)
		this.currentScrollerIndex = this.numScrollerTitles - 1;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.swipeDown = function(customSwipeInterval) {
	customSwipeInterval = (typeof customSwipeInterval === 'undefined') ? this.swipeInterval : customSwipeInterval;
	this.currentScrollerIndex += customSwipeInterval;
	if (this.currentScrollerIndex > this.numScrollerTitles - 1)
		this.currentScrollerIndex = 0;
	TitleScroller.prototype.activateCurrentTitle.call(this);
};

TitleScroller.prototype.scrollToIndex = function (index){
	var previousIndex = (typeof this.currentScrollerIndex == "undefined") ? 0 : this.currentScrollerIndex;
	if (index > previousIndex){
		this.currentScrollerIndex++;
		if (this.currentScrollerIndex > this.numScrollerTitles - 1)
			this.currentScrollerIndex = 0;
	}else if (index < previousIndex){
		this.currentScrollerIndex--;
		if (this.currentScrollerIndex < 0)
			this.currentScrollerIndex = this.numScrollerTitles - 1;
	} else {
		this.currentScrollerIndex = this.currentScrollerIndex;
	}
	TitleScroller.prototype.activateCurrentTitle(false, this);
};

TitleScroller.prototype.activateCurrentTitle = function(scroll, curScroller) {
	var scroller = (typeof curScroller == "undefined") ? this : curScroller;
	if (scroller.numScrollerTitles === 0) {
		return;
	}
	var scrollerTitles = scroller.scrollerTitles,
			scrollerShortName = scroller.scrollerShortName,
			currentScrollerIndex = scroller.currentScrollerIndex,
			scrollerBody = $('#' + scroller.scrollerId + " .scrollerBodyContainer .scrollerBody"),
			scrollerTitleId = "#scrollerTitle" + scroller.scrollerShortName + currentScrollerIndex;

	$("#tooltip").hide();  //Make sure to clear the current tooltip if any

	// Update the actual display
	if (scroller.style == 'horizontal'){
		$("#titleScrollerSelectedTitle" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['title']);
		$("#titleScrollerSelectedAuthor" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['author']);

		if ($(scrollerTitleId).length != 0){
			var widthItemsLeft = $(scrollerTitleId).position().left,
					widthCurrent = $(scrollerTitleId).width(),
					containerWidth = $('#' + scroller.scrollerId + " .scrollerBodyContainer").width();

			if (scroller.tabSelect){
				var leftPosition = -(widthItemsLeft);
				var doScroll = (typeof scroll == "undefined") ? true : scroll;
				if (doScroll){
					scrollerBody.animate({left: leftPosition + "px"}, 200, function (){
						for (var i in scrollerTitles){
							var scrollerTitleId2 = "#scrollerTitle" + scrollerShortName + i;
							$(scrollerTitleId2).removeClass('selected');
						}
						$(scrollerTitleId).addClass('selected');
					});
					TitleScroller.prototype.setVisibleTabIndex(scroller);
				}else{
					for (var i in scrollerTitles){
						var scrollerTitleId2 = "#scrollerTitle" + scrollerShortName + i;
						$(scrollerTitleId2).removeClass('selected');
					}
					$(scrollerTitleId).addClass('selected');
				}

			}else{
				// center the book in the container
				var leftPosition = -((widthItemsLeft + widthCurrent / 2) - (containerWidth / 2));

				scrollerBody.animate({
					left: leftPosition + "px"
				}, 400, function (){
					for (var i in scrollerTitles){
						var scrollerTitleId2 = "#scrollerTitle" + scrollerShortName + i;
						$(scrollerTitleId2).removeClass('selected');
					}
					$(scrollerTitleId).addClass('selected');
				});
			}
		}
	}else if (scroller.style == 'vertical'){
		$("#titleScrollerSelectedTitle" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['title']);
		$("#titleScrollerSelectedAuthor" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['author']);

		// Scroll Upwards/Downwards
		if ($(scrollerTitleId).length != 0) {
			//Move top of the current title to the top of the scroller.
			var relativeTopOfElement = $(scrollerTitleId).position().top,
					// center the book in the container
					topPosition = 25 - relativeTopOfElement;
			scrollerBody.animate( {
				top : topPosition + "px"
			}, 400, function() {
				for ( var i in scrollerTitles) {
					var scrollerTitleId2 = "#scrollerTitle" + scrollerShortName + i;
					$(scrollerTitleId2).removeClass('selected');
				}
				$(scrollerTitleId).addClass('selected');
			});
		}
	}else if (scroller.style == 'text-list'){
		// No Action Needed
	}else{
		$("#titleScrollerSelectedTitle" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['title']);
		$("#titleScrollerSelectedAuthor" + scrollerShortName).html(scrollerTitles[currentScrollerIndex]['author']);

		scrollerBody.left = "0px";
		scrollerBody.html(scroller.scrollerTitles[currentScrollerIndex]['formattedTitle']);
	}

};

/*
 * waitForImages 1.1.2
 * -----------------
 * Provides a callback when all images have loaded in your given selector.
 * http://www.alexanderdickson.com/
 *
 *
 * Copyright (c) 2011 Alex Dickson
 * Licensed under the MIT licenses.
 * See website for more info.
 *
 */

(function($) {
	$.fn.waitForImages = function(finishedCallback, eachCallback) {

		eachCallback = eachCallback || function() {};

		if ( ! $.isFunction(finishedCallback) || ! $.isFunction(eachCallback)) {
			throw {
				name: 'invalid_callback',
				message: 'An invalid callback was supplied.'
			};
		}

		var objs = $(this),
				allImgs = objs.find('img'),
				allImgsLength = allImgs.length,
				allImgsLoaded = 0;

		if (allImgsLength == 0) {
			finishedCallback.call(this);
		}else{
			//Don't wait more than 10 seconds for all images to load.
			setTimeout (function() {finishedCallback.call(this); }, 10000);
		}

		return objs.each(function() {
			var obj = $(this),
					imgs = obj.find('img');

			if (imgs.length == 0) {
				return;
			}

			imgs.each(function() {
				var image = new Image,
						imgElement = this;

				image.onload = function() {
					allImgsLoaded++;
					eachCallback.call(imgElement, allImgsLoaded, allImgsLength);
					if (allImgsLoaded == allImgsLength) {
						finishedCallback.call(obj[0]);
					}
					return false;
				};

				//Also handle errors and aborts
				image.onabort = function() {
					allImgsLoaded++;
					eachCallback.call(imgElement, allImgsLoaded, allImgsLength);
					if (allImgsLoaded == allImgsLength) {
						finishedCallback.call(obj[0]);
					}
					return false;
				};

				image.onerror = function() {
					allImgsLoaded++;
					eachCallback.call(imgElement, allImgsLoaded, allImgsLength);
					if (allImgsLoaded == allImgsLength) {
						finishedCallback.call(obj[0]);
					}
					return false;
				};

				image.src = this.src;
			});
		});
	};
})(jQuery);
