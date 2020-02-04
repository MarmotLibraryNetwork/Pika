VuFind.Ratings = (function(){
	$(function(){
		VuFind.Ratings.initializeRaters();
	});
	return{
		initializeRaters: function(){
			$(".rater").each(function(){
				var ratingElement = $(this),
						userRating = ratingElement.data("user_rating"),
						id = ratingElement.data("id"),
						options = {
							id: id,
							rating: parseFloat(userRating > 0 ? userRating : ratingElement.data("average_rating")),
							//url: Globals.path +"AJAX" // only works for grouped works
							//url: location.protocol+'\\'+location.host+ "/GroupedWork/AJAX" // full path
							//url: "/GroupedWork/AJAX" // full path // works on our servers but not locally. plb 12-29-2015
							url: "/GroupedWork/"+ encodeURIComponent( id ) + "/AJAX" // full path
						};
				ratingElement.rater(options);
			});
		},

		doRatingReview: function (id){
			$.getJSON("/GroupedWork/"+id+"/AJAX?method=getPromptforReviewForm", function(data){
				if (data.prompt) VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons); // only ask if user hasn't set the setting already
				if (data.error)  VuFind.showMessage('Error', data.message);
			}).fail(VuFind.ajaxFail)
			// Version 3
			//VuFind.Account.ajaxLightbox("/GroupedWork/"+id+"/AJAX?method=getPromptforReviewForm", true);
			// Version 2
			//VuFind.showMessageWithButtons('Add a Review',
			//		'Would you like to add a review explaining your rating to help other users?',
			//		'<button class="btn btn-primary" onclick="VuFind.GroupedWork.showReviewForm(this, \''+id+'\')">Add a Review</button>'
			//);
			// Version 1
			//if (confirm('Would you like to add a review explaining your rating to help other users?')){
			//	VuFind.GroupedWork.showReviewForm(id);
			//}
		},

		doNoRatingReviews : function (){
			$.getJSON("/GroupedWork/AJAX?method=setNoMoreReviews", function(data){
				if (data.success) VuFind.showMessage('Success', 'You will no longer be asked to give a review.', true)
				else VuFind.showMessage('Error', 'Failed to save your setting.')
			}).fail(VuFind.ajaxFail);
		}
	};
}(VuFind.Ratings));

/*
*  Jquery Ratings Plugin, Adapted for Pika
 *
* */
//copyright 2008 Jarrett Vance
//http://jvance.com
$.fn.rater = function(options) {
	var opts = $.extend( {}, $.fn.rater.defaults, options);
	return this.each(function() {
		var $this = $(this),
				$on = $this.find('.ui-rater-starsOn'),
				$off = $this.find('.ui-rater-starsOff');

		if (opts.size == undefined) opts.size = $off.height();
		if (opts.rating == undefined) {
			opts.rating = $on.width() / $off.width();
		}else{
			$on.width($off.width() * (opts.rating / opts.ratings.length));
		}
		if (opts.id == undefined) opts.id = $this.attr('id');
		var initialRating = opts.rating;

		if (!$this.hasClass('ui-rater-bindings-done')) {
			$this.addClass('ui-rater-bindings-done');
			$off.mousemove(function(e) {
				var left = e.clientX - $off.offset().left,
						width = $off.width() - ($off.width() - left);
				width = Math.min(Math.ceil(width / (opts.size / opts.step)) * opts.size / opts.step, opts.size * opts.ratings.length);
				$on.width(width);
				var r = Math.round($on.width() / $off.width() * (opts.ratings.length * opts.step)) / opts.step;
				//$this.attr('title', 'Click to Rate "' + (opts.ratings[r - 1] == undefined ? r : opts.ratings[r - 1]) + '"');
				// TODO ratings label's are customized now.
				$this.attr('title', 'Click to Rate "' +  r  + ' stars"');
			}).hover(
					function(e) { // Hover In
						$on.addClass('ui-rater-starsHover');
					},
					function(e) { // Hover out
						$on.removeClass('ui-rater-starsHover');
						$on.width(initialRating * opts.size); // restore to original rating if none was selected.
					}
			).click(function(e) {
						var r = Math.round($on.width() / $off.width() * (opts.ratings.length * opts.step)) / opts.step;
						$.fn.rater.rate($this, opts, r);
					}).css('cursor', 'pointer'); $on.css('cursor', 'pointer');
		}
	});
};


$.fn.rater.defaults = {
	url : location.href,
	ratings: ['Hated It', "Didn't Like It", 'Liked It', 'Really Liked It', 'Loved It'],
	step : 1
};

$.fn.rater.rate = function($this, opts, rating) {
	VuFind.Account.ajaxLogin(function (){
		var $on = $this.find('.ui-rater-starsOn'),
				$off = $this.find('.ui-rater-starsOff');
		$off.fadeTo(600, 0.4, function() {
			$.getJSON(opts.url, {method: 'RateTitle', id: opts.id, rating: rating}, function(data) {
				if (data.error) {
					VuFind.showMessage('Error', data.error);
					$off.fadeTo(500, 1).mouseleave(); // Reset rater in light of failure
				}
				if (data.rating) { // success
					opts.rating = data.rating;
					//$on.css('cursor', 'default');
					$off
						// detach rater.
						//	.unbind('click').unbind('mousemove').unbind('mouseenter').unbind('mouseleave')
							//.css('cursor', 'default')

						// wrap-up
							.fadeTo(600, 0.1, function() {
								$on.removeClass('ui-rater-starsHover').width(opts.rating * opts.size).addClass('userRated');
								$off.fadeTo(500, 1);
								$this.attr('title', 'Your rating: ' + rating.toFixed(1));
								if ($this.data('show_review') == true){
									VuFind.Ratings.doRatingReview(opts.id);
								}
							});
				}
			}).fail(function(){
				VuFind.ajaxFail();
				$off.fadeTo(500, 1).mouseleave(); // Reset rater in light of failure
			});

		});
	}, null, true);
};