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

Pika.ResultsList = (function(){
	return {
		statusList: [],
		seriesList: [],

		addIdToSeriesList: function(isbn){
			this.seriesList[this.seriesList.length] = isbn;
		},

		initializeDescriptions: function(){
			$(".descriptionTrigger").each(function(){
				var descElement = $(this),
						descriptionContentClass = descElement.data("content_class");
				options = {
					html: true,
					trigger: 'hover',
					title: 'Description',
					content: Pika.ResultsList.loadDescription(descriptionContentClass)
				};
				descElement.popover(options);
			});
		},

		lessFacets: function(name){
			$("#more" + name + ",#narrowGroupHidden_" + name).toggle();
		},

		moreFacets: function(name){
			$("#more" + name + ",#narrowGroupHidden_" + name).toggle();
			},

		loadDescription: function(descriptionContentClass){
			var contentHolder = $(descriptionContentClass);
			return contentHolder[0].innerHTML;
		},

		// USE Pika.GroupedWork.staticPosition() instead
		// staticPosition: function(sticky, divId){
		// 	var resultsNav = document.getElementById(divId);
		// 	if (window.pageYOffset > sticky){
		// 		resultsNav.classList.add("sticky");
		// 	}else{
		// 		resultsNav.classList.remove("sticky");
		// 	}
		//
		// },

		moreFacetPopup: function(title, name){
			Pika.showMessage(title, $("#moreFacetPopup_" + name).html());
		},

		// toggleFacetVisibility: function(){
		// 	$facetsSection = $("#collapse-side-facets");
		// },
		//
		toggleRelatedManifestations: function(manifestationId){
			$('#relatedRecordPopup_' + manifestationId).toggleClass('d-none');
			var manifestationToggle = $('#manifestation-toggle-' + manifestationId);
			manifestationToggle.toggleClass('collapsed');
			if (manifestationToggle.hasClass('collapsed')){
				manifestationToggle.html('+');
			}else{
				manifestationToggle.html('-');
			}
			return false;

		}

	};
}(Pika.ResultsList || {}));
