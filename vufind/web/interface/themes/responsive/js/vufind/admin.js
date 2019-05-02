VuFind.Admin = (function(){
	return {
		showHooplaExportNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getHooplaExportNotes&id=" + id, true);
			return false;
		},
		showSierraExportNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getSierraExportNotes&id=" + id, true);
			return false;
		},
		showRecordGroupingNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getRecordGroupingNotes&id=" + id, true);
			return false;
		},
		showReindexNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getReindexNotes&id=" + id, true);
			return false;
		},
		toggleReindexProcessInfo: function (id){
			$("#reindexEntry" + id).toggleClass("expanded collapsed");
			$("#processInfo" + id).toggle();
		},
		showReindexProcessNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getReindexProcessNotes&id=" + id, true);
			return false;
		},

		showCronNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getCronNotes&id=" + id, true);
			return false;
		},
		showCronProcessNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getCronProcessNotes&id=" + id, true);
			return false;
		},
		toggleCronProcessInfo: function (id){
			$("#cronEntry" + id).toggleClass("expanded collapsed");
			$("#processInfo" + id).toggle();
		},

		showOverDriveExtractNotes: function (id){
			VuFind.Account.ajaxLightbox("/Admin/AJAX?method=getOverDriveExtractNotes&id=" + id, true);
			return false;
		},

		copyLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('copyHooplaSettingsFromLibrary', id);
		},

		clearLocationHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLocationHooplaSettings', id);
		},

		clearLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLibraryHooplaSettings', id);
		},

		basicAjaxHandler: function (ajaxMethod, id) {
			if (Globals.loggedIn) {
				VuFind.loadingMessage();
				var url = Globals.path + "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				$.getJSON(url, function (data) {
					VuFind.showMessage(data.title, data.body, 1, 1);
				}).fail(VuFind.ajaxFail);
			} else {
				VuFind.Account.ajaxLogin(null, function () {
					VuFind.Admin.basicAjaxHandler(ajaxMethod, id);
				}, false);
			}
			return false;
		},

		// markProfileForReindexing: function (id){
		// 	if (Globals.loggedIn) {
		// 		VuFind.loadingMessage();
		// 		var url = Globals.path + "/Admin/AJAX",
		// 				params = { 'method' : 'markProfileForReindexing', id: id};
		// 		$.getJSON(url, params, function (data) {
		// 			if (data.success) {
		// 				VuFind.showMessage("Success", data.message, true);
		// 			} else {
		// 				VuFind.showMessage("Error", data.message);
		// 			}
		// 		}).fail(VuFind.ajaxFail);
		// 	} else {
		// 		this.ajaxLogin(null, this.markProfileForReindexing, true);
		// 	}
		// 	return false;
		// },
		//
		// markProfileForRegrouping: function (id){
		// 	if (Globals.loggedIn) {
		// 		VuFind.loadingMessage();
		// 		var url = Globals.path + "/Admin/AJAX",
		// 				params = { 'method' : 'markProfileForRegrouping', id: id};
		// 		$.getJSON(url, params, function (data) {
		// 			if (data.success) {
		// 				VuFind.showMessage("Success", data.message, true);
		// 			} else {
		// 				VuFind.showMessage("Error", data.message);
		// 			}
		// 		}).fail(VuFind.ajaxFail);
		// 	} else {
		// 		this.ajaxLogin(null, this.markProfileForRegrouping, true);
		// 	}
		// 	return false;
		// },
	};
}(VuFind.Admin || {}));