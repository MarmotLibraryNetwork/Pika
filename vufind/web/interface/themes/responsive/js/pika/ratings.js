Pika.Ratings = (function(){
	$(function(){
		Pika.Ratings.initializeRaters();
		initStarRatings();
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
				if (data.prompt) Pika.showMessageWithButtons(data.title, data.modalBody, data.modalButtons); // only ask if user hasn't set the setting already
				if (data.error)  Pika.showMessage('Error', data.message);
			}).fail(Pika.ajaxFail)
			// Version 3
			//Pika.Account.ajaxLightbox("/GroupedWork/"+id+"/AJAX?method=getPromptforReviewForm", true);
			// Version 2
			//Pika.showMessageWithButtons('Add a Review',
			//		'Would you like to add a review explaining your rating to help other users?',
			//		'<button class="btn btn-primary" onclick="Pika.GroupedWork.showReviewForm(this, \''+id+'\')">Add a Review</button>'
			//);
			// Version 1
			//if (confirm('Would you like to add a review explaining your rating to help other users?')){
			//	Pika.GroupedWork.showReviewForm(id);
			//}
		},

		doNoRatingReviews : function (){
			$.getJSON("/GroupedWork/AJAX?method=setNoMoreReviews", function(data){
				if (data.success) Pika.showMessage('Success', 'You will no longer be asked to give a review.', true)
				else Pika.showMessage('Error', 'Failed to save your setting.')
			}).fail(Pika.ajaxFail);
		}
	};
}(Pika.Ratings));

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
	Pika.Account.ajaxLogin(function (){
		var $on = $this.find('.ui-rater-starsOn'),
				$off = $this.find('.ui-rater-starsOff');
		$off.fadeTo(600, 0.4, function() {
			$.getJSON(opts.url, {method: 'RateTitle', id: opts.id, rating: rating}, function(data) {
				if (data.error) {
					Pika.showMessage('Error', data.error);
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
									Pika.Ratings.doRatingReview(opts.id);
								}
							});
				}
			}).fail(function(){
				Pika.ajaxFail();
				$off.fadeTo(500, 1).mouseleave(); // Reset rater in light of failure
			});

		});
	}, null, true);
};

/*
Accessible star ratings 5-2024
This is vanilla js
by chris froese
*/
function initStarRatings() {

	document.querySelectorAll('.star_rating').forEach(function(form) {
		var radios = form.querySelectorAll('input[type=radio]');
		var output = form.querySelector('output');
		var star0 = form.querySelector('input.star0');
		var first_star = form.querySelector('input[value="1"]');
		// Make sure we have a number for comparison
		var do_review = parseInt(form.closest("div.title-rating").getAttribute('data-show_review'));

		// The X only means anything once there is a rating to remove. While it is hidden by css it is
		// also disabled, and that is what keeps it out of the tab order and out of the radio group's
		// arrow-key cycle. The template renders the starting state; this keeps it in step afterwards.
		var set_remove_rating_available = function(available){
			if (!star0){
				return;
			}
			if (!available){
				// Focus would be dropped on the floor if it were still sitting on the X being disabled.
				if (document.activeElement === star0 && first_star){
					first_star.focus();
				}
				star0.checked = true;
			}
			star0.disabled = !available;
		};

		var rating_title_for = function(el){
			return el.closest('div.title-rating').getAttribute('data-rating_title');
		};

		var rating_text_for = function(star_rating, rating_title){
			let stars = parseInt(star_rating);
			if (stars === 0){
				return `Rating removed for ${rating_title}`;
			}
			return `${rating_title} rated ${stars} star` + (stars === 1 ? '' : 's');
		};

		var submit_rating = function(star_rating, rating_text){
			Pika.Account.ajaxLogin(function (){
				let grouped_work_id = form.querySelector('[name="grouped-work-id"]').value;
				let clearing_rating = parseInt(star_rating) === 0;
				// testing error
				// star_rating += "trigger-error";
				// Create a new FormData object for form data

				// clear rating
				// var url = '/GroupedWork/' + groupedWorkId + '/AJAX?method=clearUserRating';
				let formData = new FormData();
				formData.append('method', 'RateTitle');
				formData.append('grouped-work-id', grouped_work_id);
				formData.append('rating', star_rating);

				// Build the XHR url
				let protocol = window.location.protocol;
				let hostname = window.location.hostname;
				if(clearing_rating) {
					//clear rating
					var xhr_url = `${protocol}//${hostname}/GroupedWork/${encodeURIComponent(grouped_work_id)}/AJAX?` +
					`method=clearUserRating`;
				} else {
					var xhr_url = `${protocol}//${hostname}/GroupedWork/${encodeURIComponent(grouped_work_id)}/AJAX?` +
							`method=RateTitle&id=${encodeURIComponent(grouped_work_id)}&rating=${encodeURIComponent(star_rating)}`;
				}
				// console.log(xhr_url);
				// Create a new XMLHttpRequest object
				var xhr = new XMLHttpRequest();
				xhr.open('GET', xhr_url, true);

				// Set up a handler for when the request finishes
				xhr.onload = function (){
					if (xhr.status === 200){
						try {
							// Parse the JSON response
							var response = JSON.parse(xhr.responseText);
							// If the response contains an error message
							if (response.error){
								alert('Error submitting rating.');
							}else{
								// A cleared rating leaves nothing to remove, so the X goes back to hidden and
								// disabled. Any other rating makes it available again.
								set_remove_rating_available(!clearing_rating);
								alert(clearing_rating ? 'Rating removed.' : 'Rating submitted successfully.');
								// Removing a rating is not something to prompt for a review about.
								if (do_review === 1 && !clearing_rating){
									Pika.Ratings.doRatingReview(grouped_work_id);
								}
							}
						} catch(e){
							// If there is an error parsing the JSON
							alert('Error submitting rating.');
						}
					}else{
						// There was a problem with the request
						alert('Error submitting rating.');
					}
				};

				// Send the form data
				xhr.send(formData);

				// Update the output with the rating text
				output.textContent = rating_text;
			}, null, true);
		}

		Array.prototype.forEach.call(radios, function(el) {
			var label = el.nextElementSibling;

			label.addEventListener("click", function() {
				// A label still fires click even when the input it labels is disabled.
				if (el.disabled){
					return;
				}
				submit_rating(el.value, rating_text_for(el.value, rating_title_for(el)));
			});
		});

		form.addEventListener('submit', function(event) {
			event.preventDefault();
			event.stopImmediatePropagation();
			// Every star's label tells the patron to press enter, so honor the star that actually has
			// focus. Arrow keys check as they move, but tabbing to a star does not, and :checked alone
			// would submit whatever was checked when the page rendered.
			let focused = document.activeElement;
			let selected = (focused && focused.type === 'radio' && form.contains(focused))
					? focused
					: form.querySelector('input[type=radio]:checked');
			if (!selected || selected.disabled){
				return;
			}
			selected.checked = true;
			submit_rating(selected.value, rating_text_for(selected.value, rating_title_for(selected)));
		});
	});
}


