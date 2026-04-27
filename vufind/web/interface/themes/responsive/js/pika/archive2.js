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
					}
				}
			).fail(Pika.ajaxFail);
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

	};
})();