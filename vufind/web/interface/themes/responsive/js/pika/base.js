var Pika = (function(){

	// This provides a check to interrupt AjaxFail Calls on page redirects;
	 window.onbeforeunload = function(){
		Globals.LeavingPage = true
	};

	$(function(){
		Pika.initializeModalDialogs();
		Pika.setupFieldSetToggles(); // appears to be only used for ManageRequests. pascal 12/29/2020
		Pika.setupCheckBoxSwitches();
		Pika.initCarousels();

		$("#modalDialog").modal({show:false});

		$('.panel')
				.on('show.bs.collapse', function () {
					$(this).addClass('active');
				})
				.on('hide.bs.collapse', function () {
					$(this).removeClass('active');
				});

		$(window).on("popstate", function () {
			// if the state is the page you expect, pull the name and load it.
			if (history.state && history.state.page === "MapExhibit") {
				Pika.Archive.handleMapClick(history.state.marker, history.state.exhibitPid, history.state.placePid, history.state.label, false, history.state.showTimeline);
			}
			else if (history.state && history.state.page === "Book") {
				Pika.Archive.handleBookClick(history.state.bookPid, history.state.pagePid, history.state.viewer);
			}
		});
	});
	/**
	 * Created by mark on 1/14/14.
	 */
	return {
		buildUrl: function(base, key, value) {
			var sep = (base.indexOf('?') > -1) ? '&' : '?';
			return base + sep + key + '=' + value;
		},

		changePageSize: function(){
			var url = window.location.href;
			if (url.match(/[&?]pagesize=\d+/)) {
				url = url.replace(/pagesize=\d+/, "pagesize=" + $("#pagesize").val());
			} else {
				if (url.indexOf("?", 0) > 0){
					url = url+ "&pagesize=" + $("#pagesize").val();
				}else{
					url = url+ "?pagesize=" + $("#pagesize").val();
				}
			}
			window.location.href = url;
		},

		closeLightbox: function(callback){
			var modalDialog = $("#modalDialog");
			if (modalDialog.is(":visible")){
				modalDialog.modal('hide');
				if (callback != undefined){
					var closeLightboxListener = modalDialog.on('hidden.bs.modal', function (e) {
						modalDialog.off('hidden.bs.modal');
						callback();
					});
				}
			}
		},

		initCarousels: function(carouselClass){
			carouselClass = carouselClass || '.jcarousel';
			var jcarousel = $(carouselClass),
					wrapper   = jcarousel.parents('.jcarousel-wrapper');
			// console.log('init Carousels called for ', jcarousel);

			jcarousel.on('jcarousel:reload jcarousel:create', function() {

				var Carousel       = $(this);
				var width          = Carousel.innerWidth();
				var liTags         = Carousel.find('li');
				if (liTags == null || liTags === undefined || liTags.length === 0){
					return;
				}
				var leftMargin     = +liTags.css('margin-left').replace('px', ''),
						rightMargin    = +liTags.css('margin-right').replace('px', ''),
						numCategories  = Carousel.jcarousel('items').length || 1,
						numItemsToShow = 1;

				// Adjust Browse Category Carousels
				if (jcarousel.is('#browse-category-carousel')){

					// set the number of categories to show; if there aren't enough categories, show all the categories instead
					if (width > 1000) {
						numItemsToShow = Math.min(5, numCategories);
					} else if (width > 700) {
						numItemsToShow = Math.min(4, numCategories);
					} else if (width > 500) {
						numItemsToShow = Math.min(3, numCategories);
					} else if (width > 400) {
						numItemsToShow = Math.min(2, numCategories);
					}

				}

				//// Explore More Related Titles Carousel
				//else if (jcarousel.is('.relatedTitlesContainer')) {
				//}

				//// Explore More Bar Carousel
				//else if (jcarousel.is('.exploreMoreItemsContainer')) {
				//}

				// Default Generic Carousel;
				else {
					if (width >= 800) {
						numItemsToShow = Math.min(5, numCategories);
					} else if (width >= 600) {
						numItemsToShow = Math.min(4, numCategories);
					} else if (width >= 400) {
						numItemsToShow = Math.min(3, numCategories);
					} else if (width >= 300) {
						numItemsToShow = Math.min(2, numCategories);
					}
				}

				// Set the width of each item in the carousel
				var calcWidth = (width - numItemsToShow*(leftMargin + rightMargin))/numItemsToShow;
				Carousel.jcarousel('items').css('width', Math.floor(calcWidth) + 'px');// Set Width

				if (numItemsToShow >= numCategories){
					$(this).offsetParent().children('.jcarousel-control-prev').hide();
					$(this).offsetParent().children('.jcarousel-control-next').hide();
				}

			})
			.jcarousel({
				wrap: 'circular'
			});

			// These Controls could possibly be replaced with data-api attributes
			$('.jcarousel-control-prev', wrapper)
					//.not('.ajax-carousel-control') // ajax carousels get initiated when content is loaded
					.jcarouselControl({
						target: '-=1'
					});

			$('.jcarousel-control-next', wrapper)
					//.not('.ajax-carousel-control') // ajax carousels get initiated when content is loaded
					.jcarouselControl({
						target: '+=1'
					});

			$('.jcarousel-pagination', wrapper)
					//.not('.ajax-carousel-control') // ajax carousels get initiated when content is loaded
					.on('jcarouselpagination:active', 'a', function() {
						$(this).addClass('active');
					})
					.on('jcarouselpagination:inactive', 'a', function() {
						$(this).removeClass('active');
					})
					.on('click', function(e) {
						e.preventDefault();
					})
					.jcarouselPagination({
						perPage: 1,
						item: function(page) {
							return '<a href="#' + page + '">' + page + '</a>';
						}
					});

			// If Browse Category js is set, initialize those functions
			if (typeof Pika.Browse.initializeBrowseCategory == 'function') {
				Pika.Browse.initializeBrowseCategory();
			}
		},

		initializeModalDialogs: function() {
			$(".modalDialogTrigger").each(function(){
				$(this).click(function(){
					var trigger = $(this),
							dialogTitle = trigger.attr("title") ? trigger.attr("title") : trigger.data("title"),
							dialogDestination = trigger.attr("href");
					$("#myModalLabel").text(dialogTitle);
					$(".modal-body").html('Loading.').load(dialogDestination);
					$(".extraModalButton").hide();
					$("#modalDialog").modal("show");
					return false;
				});
			});
		},

		getQuerystringParameters: function(){
			var vars = [],
					q = location.search.substr(1);
			if(q != undefined){
				q = q.split('&');
				for(var i = 0; i < q.length; i++){
					var hash = q[i].split('=');
					vars[hash[0]] = hash[1];
				}
			}
			return vars;
		},

		//// Quick Way to get a single URL parameter value (parameterName must be in the url query string)
		//getQueryParameterValue: function (parameterName) {
		//	return location.search.split(parameterName + '=')[1].split('&')[0]
		//},

		replaceQueryParam : function (param, newValue, search) {
			if (typeof search == 'undefined') search = location.search;
			var regex = new RegExp("([?;&])" + param + "[^&;]*[;&]?"),
					query = search.replace(regex, "$1").replace(/&$/, '');
			return newValue ? (query.length > 2 ? query + "&" : "?") + param + "=" + newValue : query;
		},

		getSelectedTitles: function(){
			var selectedTitles = $("input.titleSelect:checked ").map(function() {
				return $(this).attr('name') + "=" + $(this).val();
			}).get().join("&");
			if (selectedTitles.length == 0){
				var ret = confirm('You have not selected any items, process all items?');
				if (ret == true){
					var titleSelect = $("input.titleSelect");
					titleSelect.attr('checked', 'checked');
					selectedTitles = titleSelect.map(function() {
						return $(this).attr('name') + "=" + $(this).val();
					}).get().join("&");
				}
			}
			return selectedTitles;
		},

		pwdToText: function(fieldId){
			var elem = document.getElementById(fieldId);
			var input = document.createElement('input');
			input.id = elem.id;
			input.name = elem.name;
			input.value = elem.value;
			input.size = elem.size;
			input.onfocus = elem.onfocus;
			input.onblur = elem.onblur;
			input.className = elem.className;
			if (elem.type == 'text' ){
				input.type = 'password';
			} else {
				input.type = 'text';
			}

			elem.parentNode.replaceChild(input, elem);
			return input;
		},

		setupCheckBoxSwitches: function (){
			// Initiate any checkbox with a data attribute set to data-switch=""  as a switch
			// add html elements needed to style checkBoxes as switches
			$('input[type="checkbox"][data-switch]')
					.wrap( "<label class='switch'></label>" ) // label required to cause the box checking
					.after('<span class="slider"></span>');
		},

		setupFieldSetToggles: function (){
			// this appears to be obsolete as of 12/29/2020
			// $('legend.collapsible').each(function(){
			// 	$(this).siblings().hide()
			// 	.addClass("collapsed")
			// 	.click(function() {
			// 		$(this).toggleClass("expanded collapsed")
			// 		.siblings().slideToggle();
			// 		return false;
			// 	});
			// });

			// appears to be only used for ManageRequests. pascal 12/29/2020
			$('fieldset.fieldset-collapsible').each(function() {
				var collapsible = $(this);
				var legend = collapsible.find('legend:first');
				legend.addClass('fieldset-collapsible-label').on('click', null, {collapsible: collapsible}, function(event) {
					var collapsible = event.data.collapsible;
					collapsible.toggleClass('fieldset-collapsed')
				});
				// Init.
				if (!collapsible.hasClass('fieldset-init-open')){
					collapsible.addClass('fieldset-collapsed');
				}
			});
		},

		showMessage: function(title, body, autoClose, refreshAfterClose){
			// if autoclose is set as number greater than 1 autoClose will be the custom timeout interval in milliseconds, otherwise
			//     autoclose is treated as an on/off switch. Default timeout interval of 3 seconds.
			// if refreshAfterClose is set but not autoClose, the page will reload when the box is closed by the user.
			if (autoClose === undefined){
				autoClose = false;
			}
			if (refreshAfterClose === undefined){
				refreshAfterClose = false;
			}
			$("#myModalLabel").html(title);
			$(".modal-body").html(body);
			$('.modal-buttons').html('');
			var modalDialog = $("#modalDialog");
			modalDialog.modal('show');
			if (autoClose) {
				setTimeout(function(){
							if (refreshAfterClose) location.reload(true);
							else Pika.closeLightbox();
						}
						, autoClose > 1 ? autoClose : 3000);
			}else if (refreshAfterClose) {
				modalDialog.on('hide.bs.modal', function(){
					location.reload(true)
				})
			}
		},

		confirm: function(message, confirmFunction){
			var button = $('<button>', {
				id: 'confirm-button',
				class: 'btn btn-primary',
				text : 'Okay',
				click: function (){
					Pika.loadingMessage(); // prevent multiple time button clicking
					confirmFunction();
				}
			});
			$("#modalDialog").on('shown.bs.modal', function() {
				$("#confirm-button").focus();
			});
			this.showMessageWithButtons('Confirm?', message, button);
		},

		showMessageWithButtons: function(title, body, buttons){
			$("#myModalLabel").html(title);
			$(".modal-body").html(body);
			$('.modal-buttons').html(buttons);
			$("#modalDialog").modal('show');
		},

		// common loading message for lightbox while waiting for AJAX processes to complete.
		loadingMessage: function() {
			Pika.showMessage('Loading', 'Loading, please wait.')
		},

		// common message for when an AJAX call has failed.
		ajaxFail: function() {
			if (!Globals.LeavingPage) Pika.showMessage('Request Failed', 'There was an error with this AJAX Request.');
		},

		toggleHiddenElementWithButton: function(button){
			var hiddenElementName = $(button).data('hidden_element');
			var hiddenElement = $(hiddenElementName);
			hiddenElement.val($(button).hasClass('active') ? '1' : '0');
			return false;
		},

		showElementInPopup: function(title, elementId, buttonsElementId){
			// buttonsElementId is optional
			var modalDialog = $("#modalDialog");
			if (modalDialog.is(":visible")){
				Pika.closeLightbox(function(){Pika.showElementInPopup(title, elementId)});
			}else{
				$(".modal-title").html(title);
				var elementText = $(elementId).html(),
						elementButtons = buttonsElementId ? $(buttonsElementId).html() : '';
				$(".modal-body").html(elementText);
				$('.modal-buttons').html(elementButtons);

				modalDialog.modal('show');
				return false;
			}
		},

		showLocationHoursAndMap: function(){
			var selectedId = $("#selectLibrary").find(":selected").val();
			$(".locationInfo").hide();
			$("#locationAddress" + selectedId).show();
			return false;
		},

		toggleCheckboxes: function (checkboxSelector, toggleSelector){
			var toggle = $(toggleSelector);
			var value = toggle.prop('checked');
			$(checkboxSelector).prop('checked', value);
		},

		submitOnEnter: function(event, formToSubmit){
			if (event.keyCode == 13){
				$(formToSubmit).submit();
			}
		},

		hasLocalStorage: function () {
			// arguments.callee.haslocalStorage is the function's "static" variable for whether or not we have tested the
			// that the localStorage system is available to us.

			//console.log(typeof arguments.callee.haslocalStorage);
			if(typeof arguments.callee.haslocalStorage == "undefined") {
				if ("localStorage" in window) {
					try {
						window.localStorage.setItem('_tmptest', 'temp');
						arguments.callee.haslocalStorage = (window.localStorage.getItem('_tmptest') == 'temp');
						// if we get the same info back, we are good. Otherwise, we don't have localStorage.
						window.localStorage.removeItem('_tmptest');
					} catch(error) { // something failed, so we don't have localStorage available.
						arguments.callee.haslocalStorage = false;
					}
				} else arguments.callee.haslocalStorage = false;
			}
			return arguments.callee.haslocalStorage;
		}
	}

}(Pika || {}));

jQuery.validator.addMethod("multiemail", function (value, element) {
	if (this.optional(element)) {
		return true;
	}
	var emails = value.split(/[,;]/),
			valid = true;
	for (var i = 0, limit = emails.length; i < limit; i++) {
		value = emails[i];
		valid = valid && jQuery.validator.methods.email.call(this, value, element);
	}
	return valid;
}, "Invalid email format: please use a comma to separate multiple email addresses.");

/**
 *  Modified from above code, for Pika self registration form.
 *
 * Return true, if the value is a valid date, also making this formal check mm-dd-yyyy.
 *
 * @example jQuery.validator.methods.date("01-01-1900")
 * @result true
 *
 * @example jQuery.validator.methods.date("01-13-1990")
 * @result false
 *
 * @example jQuery.validator.methods.date("01.01.1900")
 * @result false
 *
 * @example <input name="pippo" class="{datePika:true}" />
 * @desc Declares an optional input element whose value must be a valid date.
 *
 * @name jQuery.validator.methods.datePika
 * @type Boolean
 * @cat Plugins/Validate/Methods
 */
jQuery.validator.addMethod(
		"datePika",
		function(value, element) {
			var check = false;
			var re = /^\d{1,2}(-)\d{1,2}(-)\d{4}$/;
			if( re.test(value)){
				var adata = value.split('-');
				var mm = parseInt(adata[0],10);
				var dd = parseInt(adata[1],10);
				var aaaa = parseInt(adata[2],10);
				var xdata = new Date(aaaa,mm-1,dd);
				if ( ( xdata.getFullYear() == aaaa ) && ( xdata.getMonth () == mm - 1 ) && ( xdata.getDate() == dd ) )
					check = true;
				else
					check = false;
			} else
				check = false;
			return this.optional(element) || check;
		},
		"Please enter a correct date"
);

jQuery.validator.addMethod("alphaNumeric", function(value, element) {
	return this.optional(element) || /^[a-z0-9]+$/i.test(value);
}, "Please enter only letters and digits.");
/* Added to just the /nwln/MyAccount/profile-notification-preferences.tpl template for now

jQuery.validator.addMethod("simplePhoneFormat", function(value, element) {
	return this.optional(element) || /^\d{3}-\d{3}-\d{4}$/.test(value);
}, "Format: xxx-xxx-xxxx");
*/

