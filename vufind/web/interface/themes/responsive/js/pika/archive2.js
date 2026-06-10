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

		collectionDisplayMode: 'grid',

		initCollectionDisplayMode: function() {
			if (!Globals.opac && Pika.hasLocalStorage()) {
				var stored = window.localStorage.getItem('archive2CollectionDisplayMode');
				if (stored === 'list' || stored === 'grid') {
					this.collectionDisplayMode = stored;
				}
			}
			this.applyCollectionDisplayMode();
		},

		toggleCollectionDisplayMode: function(mode) {
			this.collectionDisplayMode = (mode === 'list') ? 'list' : 'grid';
			if (!Globals.opac && Pika.hasLocalStorage()) {
				window.localStorage.setItem('archive2CollectionDisplayMode', this.collectionDisplayMode);
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
			nid: null,
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
		 * @param {number}  nid          Node ID of the collection
		 * @param {boolean} showTimeline Whether the decade date-filter buttons are shown
		 * @param {boolean} groupByYear  Whether items are grouped under year headings
		 */
		initCollectionTimeline: function(nid, showTimeline, groupByYear) {
			this.timeline.nid          = nid;
			this.timeline.showTimeline = showTimeline;
			this.timeline.groupByYear  = groupByYear;
			this.timeline.placeName    = '';
			this.timeline.dateFilter   = '';
			this.timeline.page         = 1;
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
			if (!state.nid) return;
			var objectsContainer = $('#timeline-objects');
			objectsContainer.css('opacity', 0.5);
			$.getJSON('/Archive2/AJAX', {
				method: 'getCollectionTimelineObjects',
				nid: state.nid,
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