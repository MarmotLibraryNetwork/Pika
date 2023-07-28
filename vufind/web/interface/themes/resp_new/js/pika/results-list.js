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

		staticPosition: function(sticky, divId){
			var resultsNav = document.getElementById(divId);
			if (window.pageYOffset > sticky){
				resultsNav.classList.add("sticky");
			}else{
				resultsNav.classList.remove("sticky");
			}

		},

		moreFacetPopup: function(title, name){
			Pika.showMessage(title, $("#moreFacetPopup_" + name).html());
		},

		// toggleFacetVisibility: function(){
		// 	$facetsSection = $("#collapse-side-facets");
		// },
		//
		toggleRelatedManifestations: function(manifestationId){
			$('#relatedRecordPopup_' + manifestationId).toggleClass('hidden');
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
