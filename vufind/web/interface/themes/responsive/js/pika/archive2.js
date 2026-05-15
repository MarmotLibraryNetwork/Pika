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

	};
})();