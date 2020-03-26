Pika.Admin = (function(){
	return {
		copyLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('copyHooplaSettingsFromLibrary', id);
		},

		clearLocationHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLocationHooplaSettings', id);
		},

		clearLibraryHooplaSettings: function (id) {
			return this.basicAjaxHandler('clearLibraryHooplaSettings', id);
		},

		// markProfileForReindexing: function (id){
		// 	return this.basicAjaxHandler('markProfileForReindexing', id);
		// },
		//
		// markProfileForRegrouping: function (id){
		// 	return this.basicAjaxHandler('markProfileForRegrouping', id);
		// },

		basicAjaxHandler: function (ajaxMethod, id) {
			Pika.Account.ajaxLogin(function (){
				Pika.loadingMessage();
				var url = "/Admin/AJAX?method=" + ajaxMethod + "&id=" + id;
				$.getJSON(url, function (data) {
					Pika.showMessage(data.title, data.body, 1, 1);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

	};
}(Pika.Admin || {}));