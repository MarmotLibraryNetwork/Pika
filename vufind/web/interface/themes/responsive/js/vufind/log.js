VuFind.Log = (function(){
	return {
		showNotes: function (type, id){
			VuFind.Account.ajaxLightbox("/Log/AJAX?method=getNotes&type=" + type + "&id=" + id, true);
			return false;
		},

		toggleCronProcessInfo: function (id){
			$("#cronEntry" + id).toggleClass("expanded collapsed");
			$("#processInfo" + id).toggle();
		},
	};
}(VuFind.Log || {}));