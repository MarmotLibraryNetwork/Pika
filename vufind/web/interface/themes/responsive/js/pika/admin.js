Pika.Admin = (function(){
	return {
		copyLibraryHooplaSettings: function (id){
			return this.basicAjaxHandler('copyHooplaSettingsFromLibrary', id);
		},
		copyLocationHooplaSettings: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyHooplaSettings");
		},
		clearLocationHooplaSettings: function (id){
			return this.basicAjaxHandler('clearLocationHooplaSettings', id);
		},

		clearLibraryHooplaSettings: function (id){
			return this.basicAjaxHandler('clearLibraryHooplaSettings', id);
		},
		copyLocationHours: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyHours");
		},
		copyBrowseCategories: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyBC");
		},
		copyFacetsSettings: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyFacets");
		},
		copyLocationIncludedRecords: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyIncluded");
		},
		copyFullRecordDisplay: function (id){
			return this.buttonAjaxHandler('displayCopyFromPrompt', id, "copyFullRecord");
		},
		cloneLocationFromSelection: function (){
			return this.buttonAjaxHandler('displayClonePrompt', null, "cloneLocation");
		},
		cloneLibraryFromSelection: function (){
			return this.buttonAjaxHandler('libraryClonePrompt', null, "cloneLibrary");
		},

		basicAjaxHandler: function (ajaxMethod, id, from){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				if (from !== undefined){
					url = url + "&fromId=" + from;
				}
				$.getJSON(url, function (data){
					Pika.showMessage(data.title, data.body, 1, 1);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		buttonAjaxHandler: function (ajaxMethod, id, command){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				if (command !== undefined){
					url = url + "&command=" + command;
				}
				$.getJSON(url, function (data){
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},
		cloneAjaxHandler: function (ajaxMethod, from, name, code){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&from=" + from + "&name=" + name + "&code=" + code;

				$.getJSON(url, function (data){
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},
		cloneLibraryHandler: function (ajaxMethod, from, displayName, subdomain, abName, facetLabelInput){
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&from=" + from + "&displayName=" + displayName + "&subdomain=" + subdomain + "&abName=" + abName + "&facetLabel=" + facetLabelInput.value;
				$.getJSON(url, function (data){
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		copyHooplaSettings: function (id, copyFromId){
			return this.basicAjaxHandler('copyHooplaSettingsFromLocation', id, copyFromId);
		},
		copyHours: function (id, copyFromId){
			return this.basicAjaxHandler('copyHourSettingsFromLocation', id, copyFromId);
		},
		copyBC: function (id, copyFromId){
			return this.basicAjaxHandler('copyBrowseCategoriesFromLocation', id, copyFromId);
		},
		copyFacets: function (id, copyFromId){
			return this.basicAjaxHandler('copyFacetSettingsFromLocation', id, copyFromId);
		},
		copyIncluded: function (id, copyFromId){
			return this.basicAjaxHandler('copyIncludedRecordsFromLocation', id, copyFromId);
		},
		copyFullRecord: function (id, copyFromId){
			return this.basicAjaxHandler('copyFullRecordDisplayFromLocation', id, copyFromId);
		},
		resetFacetsToDefault: function (id){
			return this.basicAjaxHandler('resetFacetsToDefault', id);
		},
		resetMoreDetailsToDefault: function (id){
			return this.basicAjaxHandler('resetMoreDetailsToDefault', id);
		},
		cloneLocation: function (copyFromId, name, code){
			return this.cloneAjaxHandler('cloneLocation', copyFromId, name, code);
		},
		cloneLibrary: function (copyFromId, displayName, subdomain, abName){
			return this.cloneLibraryHandler('cloneLibrary', copyFromId, displayName, subdomain, abName, facetLabelInput);
		},
		loadPtypes: function (){
			Pika.Account.ajaxLogin(function (){
				Pika.confirm("Loading Patron Types from Sierra will remove any Patron Types currently saved in Pika. Do you wish to continue?", function (){
					Pika.loadingMessage();
					$.getJSON("/Admin/AJAX?method=loadPtypes", function (data){
						Pika.showMessage('Success', 'Patron Types loaded.')
					}).fail(Pika.ajaxFail);
				});
				return false;
			});
		}
	};
}(Pika.Admin || {}));