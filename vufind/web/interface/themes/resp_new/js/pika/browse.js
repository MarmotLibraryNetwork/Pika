Pika.Browse = (function(){
	return {
		curPage: 1,
		curCategory: '',
		curSubCategory : '',
		browseMode: 'covers',
		browseModeClasses: { // browse mode to css class correspondence
			covers:'home-page-browse-thumbnails',
			grid:'home-page-browse-grid'
		},

		addToHomePage: function(searchId){
			Pika.Account.ajaxLightbox('/Browse/AJAX?method=getAddBrowseCategoryForm&searchId=' + searchId, true);
			return false;
		},

		initializeBrowseCategory: function(){
			// wrapper for setting events and connecting w/ Pika.initCarousels() in base.js

			var browseCategoryCarousel = $("#browse-category-carousel");

			// connect the browse catalog functions to the jcarousel controls
			browseCategoryCarousel.on('jcarousel:targetin', 'li', function(){
				var categoryId = $(this).data('category-id');
				Pika.Browse.changeBrowseCategory(categoryId);
			});

			if ($('#browse-category-picker .jcarousel-control-prev').css('display') != 'none') {
				// only enable if the carousel features are being used.
				// as of now, basalt & vail are not. plb 12-1-2014
				// TODO: when disabling the carousel feature is turned into an option, change this code to check that setting.

				// attach jcarousel navigation to clicking on a category
				browseCategoryCarousel.find('li').click(function(){
					$("#browse-category-carousel").jcarousel('scroll', $(this));
				});

				// Incorporate swiping gestures into the browse category selector. pascal 11-26-2014
				var scrollFactor = 15; // swipe size per item to scroll.
				browseCategoryCarousel.touchwipe({
					wipeLeft: function (dx) {
						var scrollInterval = Math.round(dx / scrollFactor); // vary scroll interval based on wipe length
						$("#browse-category-carousel").jcarousel('scroll', '+=' + scrollInterval);
					},
					wipeRight: function (dx) {
						var scrollInterval = Math.round(dx / scrollFactor); // vary scroll interval based on wipe length
						$("#browse-category-carousel").jcarousel('scroll', '-=' + scrollInterval);
					}
				});

				// Implement functions for libraries not using the carousel functionality
			} else {
				// bypass jcarousel navigation on a category click
				browseCategoryCarousel.find('li').click(function(){
					$(this).trigger('jcarousel:targetin');
				});
			}

		},

		toggleBrowseMode : function(selectedMode){
			var categoryTextId = this.curCategory || $('#browse-category-carousel .selected').data('category-id'),
					subCategoryTextId = this.curSubCategory || $('#browse-sub-category-menu .selected').data('sub-category-id');
			this.browseMode = this.browseModeClasses.hasOwnProperty(selectedMode) ? selectedMode : this.browseMode; // check that selected mode is a valid option
			if (!Globals.opac && Pika.hasLocalStorage() ) { // store setting in browser if not an opac computer
				window.localStorage.setItem('browseMode', this.browseMode);
			}
			if (subCategoryTextId) return this.changeBrowseSubCategory(subCategoryTextId);
			else return this.changeBrowseCategory(categoryTextId); // re-load the browse category
		},

		resetBrowseResults : function(){
			var classes = (function(){ // return list of all associated css classes (class list can be expanded without changing this code.)
						var str = '', object = Pika.Browse.browseModeClasses;
						for (property in object) { str += object[property]+' ' }
						return str;
					})(),
					selectedClass = this.browseModeClasses[this.browseMode];

			// hide current results while fetching new results
			$('#home-page-browse-results').children().fadeOut(function(){
				$('#home-page-browse-results').children().slice(1).remove(); // remove all but the first div, also removes the <hr>s between the thumbnail divs
				$('#home-page-browse-results div.row').removeClass(classes) // remove all browse mode classes
						.addClass(selectedClass); // add selected browse mode class
			});
		},

		changeBrowseCategory: function(categoryTextId){
			var url = '/Browse/AJAX',
					params = {
						method : 'getBrowseCategoryInfo'
						,textId : categoryTextId || Pika.Browse.curCategory
						,browseMode : this.browseMode
					},
					newLabel = $('#browse-category-'+categoryTextId+' div').first().text(), // get label from corresponding li div
			// the carousel clones these divs sometimes, so grab only the text from the first one.
					loadingID = categoryTextId || initial;

			if (Pika.Browse.initialSubCategory !== ''){
				//This is triggered when a sub category has been set by url.
				// This allows us to fetch the chosen sub category instead of default sub category
				params.subCategoryTextId = Pika.Browse.initialSubCategory;
				delete Pika.Browse.initialSubCategory; // unset specified category
			}

			// Set selected Carousel
			$('.browse-category').removeClass('selected');
			$('#browse-category-' + categoryTextId).addClass('selected');

			$('#selected-browse-search-link').attr('href', '#'); // clear the search results link so that

			// Set the new browse category labels (below the carousel)
			$('.selected-browse-label-search-text,.selected-browse-sub-category-label-search-text').fadeOut(function(){
				$('.selected-browse-label-search-text').html(newLabel).fadeIn()
			});

			// Hide current sub-categories while fetching new ones
			$('#browse-sub-category-menu').children().fadeOut(function(){
				$(this).remove() // delete sub-category buttons
			});

			// Hide current results while fetching new results
			this.resetBrowseResults();

			// Set a flag for the results we are currently loading
			//   so that if the user moves onto another category before we get results, we won't do anything
			this.loadingCategory = loadingID;
			$.getJSON(url, params, function(data){
				if (Pika.Browse.loadingCategory == loadingID) {
					if (data.success == false) {
						Pika.showMessage("Error loading browse information", "Sorry, we were not able to find titles for that category");
					} else {
						$('.selected-browse-label-search-text').html(data.label); // update label

						Pika.Browse.curPage = 1;
						Pika.Browse.curCategory = data.textId;
						Pika.Browse.curSubCategory = data.subCategoryTextId || '';
						$('#home-page-browse-results div.row') // should be the first div only
								.html(data.records).fadeIn('slow');

						$('#selected-browse-search-link').attr('href', data.searchUrl); // set the Label's link

						// Display Sub-Categories
						if (data.subcategories) {
							$('#browse-sub-category-menu').html(data.subcategories).fadeIn();
							if (data.subCategoryTextId) { // selected sub category
								// Set and Show sub-category label
								$('.selected-browse-sub-category-label-search-text')
										.html($('#browse-sub-category-' + data.subCategoryTextId).addClass('selected').text())
										.fadeIn()
							}
						}
					}
				}
			}).fail(function(){
				Pika.ajaxFail();
				$('#home-page-browse-results div').html('').show(); // should be first div
				//$('.home-page-browse-thumbnails').html('').show();
			}).done(function() {
				Pika.Browse.loadingCategory = null;  // done loading category, empty flag
			});
			return false;
		},

		changeBrowseSubCategory: function (subCategoryTextId) {
			var url = '/Browse/AJAX',
					params = {
						method : 'getBrowseSubCategoryInfo'
						,textId : Pika.Browse.curCategory
						,subCategoryTextId : subCategoryTextId
						,browseMode : this.browseMode
					};
			$('#browse-sub-category-menu button').removeClass('selected');
			newSubCategoryLabel = $('#browse-sub-category-'+subCategoryTextId)
					.addClass('selected') // Set selected button as active
					.text(); // get label from corresponding button

			// Set the new browse category label (below the carousel)
			$('.selected-browse-sub-category-label-search-text').fadeOut(function(){
				$(this).html(newSubCategoryLabel).fadeIn()
			});

			// Hide current results while fetching new results
			this.resetBrowseResults();

			$.getJSON(url, params, function(data){
				if (data.success == false){
					Pika.showMessage("Error loading browse information", "Sorry, we were not able to find titles for that category");
				}else{
					if (data.label) $('.selected-browse-label-search-text').html(data.label); // update label // needed when sub-category is specified via URL
					if (data.subCategoryLabel) $('.selected-browse-sub-category-label-search-text').html(data.subCategoryLabel);
					else $('.selected-browse-sub-category-label-search-text').fadeOut(); // Hide if no sub-category

					Pika.Browse.curPage = 1;
					if (data.textId) Pika.Browse.curCategory = data.textId;
					if (data.subCategoryTextId) Pika.Browse.curSubCategory = data.subCategoryTextId || '';

					$('#home-page-browse-results div.row')  // should be the first div only
							.html(data.records).fadeIn('slow');

					$('#selected-browse-search-link').attr('href', data.searchUrl); // update the search link
				}
			}).fail(function(){
				Pika.ajaxFail();
				$('#home-page-browse-results div.row').html('').show(); // should be first div
				$('.selected-browse-sub-category-label-search-text').fadeOut(); // hide sub-category Label
			});
			return false;
		},

		createBrowseCategory: function(){
			var url = "/Browse/AJAX",
					params = {
							method:'createBrowseCategory'
							,categoryName:$('#categoryName').val()
							,addAsSubCategoryOf:$('#addAsSubCategoryOfSelect').val()
							};
				var searchId = $("#searchId");
				if (searchId){
					params['searchId'] = searchId.val()
				}
				var listId = $("#listId");
				if (listId){
					params['listId'] = listId.val()
				}
				$.getJSON(url, params, function (data) {
					if (data.success == false) {
						Pika.showMessage("Unable to create category", data.message);
					} else {
						Pika.showMessageWithButtons("Successfully added", data.message, data.buttons, true);
					}
				}).fail(Pika.ajaxFail);
			return false;
		},

		getMoreResults: function(){
			//Increment the current page in case the button is clicked rapidly
			this.curPage += 1;
			var url = '/Browse/AJAX',
					params = {
						method : 'getMoreBrowseResults'
						,textId :  this.curSubCategory || this.curCategory
						  // if sub-category is currently selected fetch that, otherwise fetch the main category
						,pageToLoad : this.curPage
						,browseMode : this.browseMode
					},
					divClass = this.browseModeClasses[this.browseMode]; //|| this.browseModeClasses[Object.keys(this.browseModeClasses)[0]]; // if browseMode isn't set grab the first class
			$.getJSON(url, params, function(data){
				if (data.success == false){
					Pika.showMessage("Error loading browse information", "Sorry, we were not able to find titles for that category");
				}else{
					var newDiv = $('<div class="'+divClass+' row" />').hide().append(data.records);
					$('.'+divClass).filter(':last').after(newDiv).after('<hr>');
					newDiv.fadeIn('slow');
					if (data.lastPage){
						$('#more-browse-results').hide(); // hide the load more results TODO: implement server side
					}
				}
			}).fail(Pika.ajaxFail);
			return false;
		}

	}
}(Pika.Browse || {}));
