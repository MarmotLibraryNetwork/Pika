Pika.Wikipedia = (function(){
	return{
		getWikipediaArticle: function(articleName){
			var url = "/Author/AJAX?method=getWikipediaData&articleName=" + articleName;
			$.getJSON(url, function(data){
				if (data.success)
					$("#wikipedia_placeholder").html(data.formatted_article).fadeIn();
			});
		}
	};
}(Pika.Wikipedia));