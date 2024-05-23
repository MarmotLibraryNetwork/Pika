<form action="#" class="star_rating">
	<input type="hidden" name="grouped-work-id" value="{$id}">
	<input value="0" id="{$id}-star0" checked="" type="radio" name="rating" class="visuallyhidden star0" />
	<label for="{$id}-star0">
		<span class="visuallyhidden">0 Stars</span>
		<svg viewBox="0 0 512 512">
			<g stroke-width="70" stroke-linecap="square">
				<path d="M91.5,442.5 L409.366489,124.633512"></path>
				<path d="M90.9861965,124.986197 L409.184248,443.184248"></path>
			</g>
		</svg>
	</label>

	<input value="1" id="{$id}-star1" type="radio" name="rating" class="visuallyhidden"{if $ratingData.user == 1} checked=""{/if} />
	<label for="{$id}-star1">
		<span class="visuallyhidden">1 Star</span>
		<svg viewBox="0 0 512 512">
			<path class="stroke"
			      d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
			</path>
		</svg>
	</label>

	<input value="2" id="{$id}-star2" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 2}checked{/if}/>
	<label for="{$id}-star2">
		<span class="visuallyhidden">2 Stars</span>
		<svg viewBox="0 0 512 512">
			<path
							d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
			</path>
		</svg>
	</label>

	<input value="3" id="{$id}-star3" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 3}checked{/if}/>
	<label for="{$id}-star3">
		<span class="visuallyhidden">3 Stars</span>
		<svg viewBox="0 0 512 512">
			<path
							d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
			</path>
		</svg>
	</label>

	<input value="4" id="{$id}-star4" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 4}checked{/if}/>
	<label for="{$id}-star4">
		<span class="visuallyhidden">4 Stars</span>
		<svg viewBox="0 0 512 512">
			<path
							d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
			</path>
		</svg>
	</label>

	<input value="5" id="{$id}-star5" type="radio" name="rating" class="visuallyhidden" {if $ratingData.user == 5}checked{/if}/>
	<label for="{$id}-star5">
		<span class="visuallyhidden">5 Stars</span>
		<svg viewBox="0 0 512 512">
			<path
							d="M512 198.525l-176.89-25.704-79.11-160.291-79.108 160.291-176.892 25.704 128 124.769-30.216 176.176 158.216-83.179 158.216 83.179-30.217-176.176 128.001-124.769z">
			</path>
		</svg>
	</label>

	<button type="submit" class="btn-small visuallyhidden focusable">Submit rating</button>

	<output></output>
</form>
