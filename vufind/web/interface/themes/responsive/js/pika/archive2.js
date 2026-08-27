/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Client-side logic for Islandora2 / Archive2 pages.
 * AJAX calls go to /Archive2/AJAX?method=<methodName>
 */
Pika.Archive2 = (function(){

	return {

		collectionDisplayMode: 'covers',

		/**
		 * The page of archive search results currently at the bottom of the covers grid.
		 *
		 * Archive2/list.tpl seeds this with the page the server rendered rather than leaving it
		 * at 1: covers view has no pager, but a patron can still arrive on a later page from a
		 * bookmark or from the "back to results" link on an object or term page, and counting
		 * from 1 there would re-request a batch that is already on screen.
		 */
		curPage: 1,

		/** Whether a batch of covers results is in flight; see getMoreResults(). */
		loadingMoreResults: false,

		/**
		 * Ids of "random image" components (see nextRandomImage()) with a reload
		 * request in flight. Keyed rather than a single flag since a page can carry
		 * more than one randomImage component, each reloading independently.
		 */
		loadingRandomImage: {},

		/**
		 * Append the next batch of archive search results to the covers grid.
		 *
		 * Mirrors Pika.Searches.getMoreResults() for the catalog: the current query string is
		 * reused with the page advanced, so every search term, facet, and sort the patron set
		 * still applies to the batch that comes back.
		 */
		getMoreResults: function(){
			// A batch takes long enough to fetch that the button can be clicked again before the
			// first one lands; without this guard both requests ask for the same page and the
			// same tiles get appended twice.
			if (this.loadingMoreResults){
				return false;
			}
			var url      = '/Archive2/AJAX',
					params   = Pika.replaceQueryParam('page', this.curPage + 1) + '&method=getMoreSearchResults',
					status   = $('#more-results-status'),
					loading  = $('#more-results-loading'),
					button   = $('#more-browse-results'),
					divClass = 'home-page-browse-thumbnails'; // the wrapper Archive/covers-list.tpl builds
			params = Pika.replaceQueryParam('view', 'covers', params); // the button only exists in covers view

			this.loadingMoreResults = true;
			button.prop('disabled', true);
			loading.removeClass('hidden');
			status.text('Loading more results.');

			$.getJSON(url + params, function(data){
				if (data.success === false){
					status.text('');
					Pika.showMessage("Error loading search information", "Sorry, we were not able to retrieve additional results.");
				}else{
					var newDiv = $(data.records).hide();
					$('.' + divClass).filter(':last').after(newDiv);
					newDiv.fadeIn('slow');
					if (data.lastPage){
						$('#more-browse-results').hide();
						status.text('Loaded the last of the results.');
					}else{
						Pika.Archive2.curPage++;
						status.text('More results loaded.');
					}
				}
			}).fail(function(){
				status.text('');
				Pika.ajaxFail.apply(this, arguments);
			}).always(function(){
				Pika.Archive2.loadingMoreResults = false;
				loading.addClass('hidden');
				button.prop('disabled', false); // harmless on the last batch, where the button is hidden
			});
			return false;
		},

		/**
		 * Syncs the in-memory display mode with what the server already rendered
		 * (the toggle button PHP marked "active", per the archive2CollectionDisplayMode
		 * cookie / library defaultArchiveCollectionBrowseMode setting), so later AJAX
		 * grid reloads (e.g. loadTimelineObjects) keep applying the right one.
		 */
		initCollectionDisplayMode: function() {
			this.collectionDisplayMode = $('#collectionModeList').hasClass('active') ? 'list' : 'covers';
			this.applyCollectionDisplayMode();
		},

		toggleCollectionDisplayMode: function(mode) {
			this.collectionDisplayMode = (mode === 'list') ? 'list' : 'covers';
			// Don't persist a preference on shared OPAC terminals, same as other per-patron settings.
			if (!Globals.opac) {
				var date = new Date();
				date.setTime(date.getTime() + 365 * 24 * 60 * 60 * 1000);
				document.cookie = encodeURIComponent('archive2CollectionDisplayMode') + '=' + encodeURIComponent(this.collectionDisplayMode)
					+ '; expires=' + date.toGMTString() + '; path=/';
			}
			this.applyCollectionDisplayMode();
		},

		applyCollectionDisplayMode: function() {
			var isList = this.collectionDisplayMode === 'list';
			$('#collection-display-container').toggleClass('collection-list', isList).toggleClass('collection-grid', !isList);
			$('.displayMode').removeClass('active');
			$('#collectionMode' + (isList ? 'List' : 'Grid')).addClass('active');
		},

		/**
		 * Load the Explore More sidebar for an Islandora2 object page.
		 * Injects the rendered HTML into #explore-more-body and initialises carousels.
		 *
		 * @param {number} nid  Islandora2 node ID
		 */
		loadExploreMore: function(nid) {
			$.getJSON('/Archive2/AJAX?method=getExploreMoreContent&id=' + encodeURIComponent(nid),
				function(data) {
					if (data.success) {
						$('#explore-more-body').html(data.exploreMore);
						Pika.initCarousels('#explore-more-body .panel-collapse.in .jcarousel');
					} else {
						$('#explore-more-body').html(''); // remove the "loading" display on failure
					}
				}
			).fail(function (){
				$('#explore-more-body').html(''); // remove the "loading" display on failure
			});
		},

		/**
		 * Load the Related Objects accordion panel for an Archive2 Person page.
		 * Injects rendered tile HTML into #personRelatedObjectsContent, or hides
		 * the panel when no related objects exist.
		 *
		 * @param {string} personName  Display name of the Person taxonomy term
		 */
		loadRelatedObjects: function(personName) {
			$.getJSON(
				'/Archive2/AJAX?method=getRelatedObjectsForPerson&name=' + encodeURIComponent(personName),
				function(data) {
					if (data.success && data.hasResults) {
						$('#personRelatedObjectsContent').html(data.html);
					} else {
						$('#personRelatedObjectsPanel').hide();
					}
				}
			).fail(Pika.ajaxFail);
		},

		loadRelatedObjectsForOrganization: function(orgName) {
			$.getJSON(
				'/Archive2/AJAX?method=getRelatedObjectsForOrganization&name=' + encodeURIComponent(orgName),
				function(data) {
					if (data.success && data.hasResults) {
						$('#orgRelatedObjectsContent').html(data.html);
					} else {
						$('#orgRelatedObjectsPanel').hide();
					}
				}
			).fail(Pika.ajaxFail);
		},

		loadRelatedObjectsForEvent: function(eventName) {
			$.getJSON(
				'/Archive2/AJAX?method=getRelatedObjectsForEvent&name=' + encodeURIComponent(eventName),
				function(data) {
					if (data.success && data.hasResults) {
						$('#eventRelatedObjectsContent').html(data.html);
					} else {
						$('#eventRelatedObjectsPanel').hide();
					}
				}
			).fail(Pika.ajaxFail);
		},

		/**
		 * Load the Explore More sidebar for an Archive2 taxonomy term page
		 * (Person, Place, Event, Organization). Injects rendered HTML into
		 * #explore-more-body and initialises carousels.
		 *
		 * @param {number} tid  Taxonomy term ID
		 */
		loadTaxonomyExploreMore: function(tid) {
			$.getJSON(
				'/Archive2/AJAX?method=getTaxonomyExploreMoreContent&tid=' + encodeURIComponent(tid),
				function(data) {
					if (data.success) {
						$('#explore-more-body').html(data.exploreMore);
						Pika.initCarousels('#explore-more-body .panel-collapse.in .jcarousel');
					}
				}
			).fail(Pika.ajaxFail);
		},

		loadRelatedObjectsForPlace: function(placeName) {
			$.getJSON(
				'/Archive2/AJAX?method=getRelatedObjectsForPlace&name=' + encodeURIComponent(placeName),
				function(data) {
					if (data.success && data.hasResults) {
						$('#placeRelatedObjectsContent').html(data.html);
					} else {
						$('#placeRelatedObjectsPanel').hide();
					}
				}
			).fail(Pika.ajaxFail);
		},
		/**
		 * State for the filterable collection child-object grid used by the
		 * timeline and map collection displays. The initial grid is rendered
		 * server-side; filter/place/page changes reload it via AJAX.
		 */
		timeline: {
			collectionNids: '',
			showTimeline: false,
			groupByYear: false,
			placeName: '',
			dateFilter: '',
			page: 1
		},

		/**
		 * Record the timeline state for the current collection page.
		 * Called from timeline_component.tpl on page load.
		 *
		 * @param {string}  collectionNids Comma-separated collection node id(s) the grid covers
		 * @param {boolean} showTimeline   Whether the decade date-filter buttons are shown
		 * @param {boolean} groupByYear    Whether items are grouped under year headings
		 */
		initCollectionTimeline: function(collectionNids, showTimeline, groupByYear) {
			this.timeline.collectionNids = String(collectionNids);
			this.timeline.showTimeline   = showTimeline;
			this.timeline.groupByYear    = groupByYear;
			this.timeline.placeName      = '';
			this.timeline.dateFilter     = '';
			this.timeline.page           = 1;
		},

		/**
		 * Reload the child-object grid (and optionally the date-filter buttons)
		 * for the current timeline state.
		 *
		 * @param {boolean} includeFilters  Rebuild the date-filter buttons too
		 *                                  (used when the selected place changes)
		 */
		loadTimelineObjects: function(includeFilters) {
			var state = this.timeline;
			if (!state.collectionNids) return;
			var objectsContainer = $('#timeline-objects');
			objectsContainer.css('opacity', 0.5);
			$.getJSON('/Archive2/AJAX', {
				method: 'getCollectionTimelineObjects',
				nids: state.collectionNids,
				placeName: state.placeName,
				dateFilter: state.dateFilter,
				page: state.page,
				showTimeline: state.showTimeline ? 1 : 0,
				groupByYear: state.groupByYear ? 1 : 0,
				includeFilters: includeFilters ? 1 : 0
			}, function(data) {
				objectsContainer.css('opacity', 1);
				if (data.success) {
					objectsContainer.html(data.html);
					if (data.filtersHtml !== undefined) {
						$('#timeline-date-filters').html(data.filtersHtml);
					}
					Pika.Archive2.applyCollectionDisplayMode();
				} else if (data.message) {
					Pika.showMessage('Error', data.message);
				}
			}).fail(function() {
				objectsContainer.css('opacity', 1);
				Pika.ajaxFail();
			});
		},

		/**
		 * Reload a custom collection's "random image" component with a newly
		 * picked random image, in place. Called by the reload button in
		 * random_image_component.tpl.
		 *
		 * @param {string} id         Component instance id (matches the placeholder's
		 *                            "randomImagePlaceholder_" suffix)
		 * @param {string} sourceNids Comma-separated collection node ids to pick from
		 */
		nextRandomImage: function(id, sourceNids) {
			// Without this guard, clicking again before the first response lands fires a
			// second overlapping request; whichever happens to land last wins, which isn't
			// necessarily the one requested last. Mirrors the guard in getMoreResults().
			if (this.loadingRandomImage[id]) {
				return false;
			}
			var placeholder = $('#randomImagePlaceholder_' + id),
					button      = $('#randomImageReload_' + id);

			this.loadingRandomImage[id] = true;
			button.prop('disabled', true).addClass('loading');

			$.getJSON('/Archive2/AJAX', {
				method: 'getRandomImageComponent',
				nids: sourceNids
			}, function(data) {
				if (data.success) {
					placeholder.html(data.html);
				} else if (data.message) {
					Pika.showMessage('Error', data.message);
				}
			}).fail(Pika.ajaxFail).always(function() {
				Pika.Archive2.loadingRandomImage[id] = false;
				button.prop('disabled', false).removeClass('loading');
			});
			return false;
		},

		/**
		 * Apply a decade date filter ('' or 'all' clears it).
		 * Called by the buttons in timeline_date_filters.tpl.
		 *
		 * @param {string} value   Decade start year (e.g. '1920'), 'unknown', or ''
		 * @param {Element} button The clicked button, for active-state styling
		 */
		setTimelineDateFilter: function(value, button) {
			this.timeline.dateFilter = (value === 'all') ? '' : value;
			this.timeline.page = 1;
			if (button) {
				$(button).addClass('active').siblings().removeClass('active');
			}
			this.loadTimelineObjects(false);
		},

		/**
		 * Restrict the grid to objects related to a place (map marker click).
		 * Resets the date filter since its counts are place-specific.
		 *
		 * @param {string} placeName  Display name of the Place taxonomy term
		 */
		setTimelinePlace: function(placeName) {
			if (this.timeline.placeName === placeName) return;
			this.timeline.placeName  = placeName;
			this.timeline.dateFilter = '';
			this.timeline.page       = 1;
			this.loadTimelineObjects(true);
		},

		/** Clear the place restriction and show the whole collection again. */
		clearTimelinePlace: function() {
			this.timeline.placeName  = '';
			this.timeline.dateFilter = '';
			this.timeline.page       = 1;
			this.loadTimelineObjects(true);
			return false;
		},

		/**
		 * Go to a page of the current (possibly filtered) grid.
		 *
		 * @param {number} page  1-indexed page number
		 */
		gotoTimelinePage: function(page) {
			this.timeline.page = Math.max(1, page);
			this.loadTimelineObjects(false);
			var objectsTop = $('#timeline-objects').offset();
			if (objectsTop) {
				$('html, body').animate({scrollTop: objectsTop.top - 80}, 200);
			}
			return false;
		},

		showSaveToListForm: function (trigger, id){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Archive2/AJAX";
				var params = {
					'id':id,
					'method':'showSaveToListForm'
				}
				$.getJSON(url, params, function(data){
					Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(Pika.ajaxFail);
			}, $(trigger));
			return false;
		},
		saveToList: function(id){
			if (Globals.loggedIn){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = "/Archive2/AJAX";
						params = {
							'method':'saveToList'
							,'id':id
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
	};
})();