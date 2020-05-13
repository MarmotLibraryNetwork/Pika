Pika.Admin = (function(){
	return {
		copyLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('copyHooplaSettingsFromLibrary', id);
		},
		copyLocationHooplaSettings: function (id) {

				return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyHooplaSettings");
		},
		clearLocationHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLocationHooplaSettings', id);
		},

		clearLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLibraryHooplaSettings', id);
		},
		copyLocationHours: function(id)
		{
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyHours");
		},
		copyBrowseCategories: function(id)
		{
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyBC");
		},
		copyFacetsSettings: function(id)
		{
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyFacets");
		},
		copyLocationIncludedRecords: function(id)
		{
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyIncluded");
		},
		copyFullRecordDisplay: function(id)
		{
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyFullRecord");
		},
		// markProfileForReindexing: function (id){
		// 	return this.basicAjaxHandler('markProfileForReindexing', id);
		// },
		//
		// markProfileForRegrouping: function (id){
		// 	return this.basicAjaxHandler('markProfileForRegrouping', id);
		// },

		basicAjaxHandler: function (ajaxMethod, id, from) {
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				if (from !== undefined)
				{
					url = url + "&fromId=" + from;
				}
				$.getJSON(url, function (data) {
					Pika.showMessage(data.title, data.body, 1, 1);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		buttonAjaxHandler: function(ajaxMethod, id, command) {
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				if (command !== undefined)
				{
					url = url + "&command=" + command;
				}
				$.getJSON(url, function (data) {
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		copyHooplaSettings: function(id, copyFromId){
			return this.basicAjaxHandler('copyHooplaSettingsFromLocation', id, copyFromId);
		},
		copyHours: function(id, copyFromId)
		{
			return this.basicAjaxHandler('copyHourSettingsFromLocation', id, copyFromId);
		},
		copyBC: function(id, copyFromId)
		{
			return this.basicAjaxHandler('copyBrowseCategoriesFromLocation', id, copyFromId);
		},
		copyFacets: function(id, copyFromId)
		{
			return this.basicAjaxHandler('copyFacetSettingsFromLocation', id, copyFromId);
		},
		copyIncluded: function(id, copyFromId)
		{
			return this.basicAjaxHandler('copyIncludedRecordsFromLocation', id, copyFromId);
		},
		copyFullRecord: function(id, copyFromId)
		{
			return this.basicAjaxHandler('copyFullRecordDisplayFromLocation', id, copyFromId);
		},
		resetFacetsToDefault: function(id)
		{
			return this.basicAjaxHandler('resetFacetsToDefault', id);
		},
		resetMoreDetailsToDefault: function(id)
		{
			return this.basicAjaxHandler('resetMoreDetailsToDefault', id);
		},
	};
}(Pika.Admin || {}));