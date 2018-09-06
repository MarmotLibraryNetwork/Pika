VuFind.ResultsList = (function(){
	return {
		statusList: [],
		seriesList: [],

		addIdToSeriesList: function(isbn){
			this.seriesList[this.seriesList.length] = isbn;
		},

		addIdToStatusList: function(id, type, useUnscopedHoldingsSummary) {
			if (type == undefined){
				type = 'VuFind';
			}
			var idVal = [];
			idVal['id'] = id;
			idVal['useUnscopedHoldingsSummary'] = useUnscopedHoldingsSummary;
			idVal['type'] = type;
			this.statusList[this.statusList.length] = idVal;
		},

		initializeDescriptions: function(){
			$(".descriptionTrigger").each(function(){
				var descElement = $(this),
						descriptionContentClass = descElement.data("content_class");
				options = {
					html: true,
					trigger: 'hover',
					title: 'Description',
					content: VuFind.ResultsList.loadDescription(descriptionContentClass)
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

		moreFacetPopup: function(title, name){
			VuFind.showMessage(title, $("#moreFacetPopup_" + name).html());
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
}(VuFind.ResultsList || {}));
