
<form action="#" class="star_rating">
	<input type="hidden" name="grouped-work-id" value="{$id}">

	<fieldset>
		<legend class="visuallyhidden">Star rating for {$ratingTitle}</legend>

		<input value="0" id="{$id}-star0" checked="" type="radio" name="rating" class="visuallyhidden star0">
		<label for="{$id}-star0">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 0 stars</span>
			<svg viewBox="0 0 512 512">
				<g stroke-width="70" stroke-linecap="square">
					<path d="M91.5,442.5 L409.366489,124.633512"></path>
					<path d="M90.9861965,124.986197 L409.184248,443.184248"></path>
				</g>
			</svg>
		</label>

		<input value="1" id="{$id}-star1" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 1}checked=""{/if} aria-label="Rate {$ratingTitle} 1 star.">
		<label for="{$id}-star1">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 1 star</span>
			<svg viewBox="0 0 512 512">
				<path
								d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
				</path>
			</svg>
		</label>

		<input value="2" id="{$id}-star2" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 2}checked{/if} aria-label="Rate {$ratingTitle} 2 stars.">
		<label for="{$id}-star2">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 2 stars</span>
			<svg viewBox="0 0 512 512">
				<path
								d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
				</path>
			</svg>
		</label>

		<input value="3" id="{$id}-star3" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 3}checked{/if} aria-label="Rate {$ratingTitle} 3 stars.">
		<label for="{$id}-star3">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 3 stars</span>
			<svg viewBox="0 0 512 512">
				<path
								d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
				</path>
			</svg>
		</label>

		<input value="4" id="{$id}-star4" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 4}checked{/if} aria-label="Rate {$ratingTitle} 4 stars.">
		<label for="{$id}-star4">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 4 stars</span>
			<svg viewBox="0 0 512 512">
				<path
								d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
				</path>
			</svg>
		</label>

		<input value="5" id="{$id}-star5" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 5}checked{/if} aria-label="Rate {$ratingTitle} 5 stars.">
		<label for="{$id}-star5">
			<span class="visuallyhidden">Press enter to rate {$ratingTitle} 5 stars</span>
			<svg viewBox="0 0 512 512">
				<path
								d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
				</path>
			</svg>
		</label>
	</fieldset>
	<button type="submit" class="visuallyhidden">Submit rating for {$ratingTitle}</button>

	<output class="visuallyhidden"></output>
</form>
