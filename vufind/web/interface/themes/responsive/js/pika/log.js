Pika.Log = (function(){
	return {
		showNotes: function (type, id){
			Pika.Account.ajaxLightbox("/Log/AJAX?method=getNotes&type=" + type + "&id=" + id, true);
			return false;
		},

		toggleCronProcessInfo: function (id){
			$("#cronEntry" + id).toggleClass("expanded collapsed");
			$("#processInfo" + id).toggle();
		},
	};
}(Pika.Log || {}));