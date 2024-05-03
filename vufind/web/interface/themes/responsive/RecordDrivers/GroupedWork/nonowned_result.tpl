<div class="resultsList">
	{* Title row*}
	<div class="row result-title-row">
		<div class="col-tn-12">
			<h2 class="h3">
			<span class="result-index">{$resultIndex}.</span>&nbsp;
			<span class="result-title notranslate">
				{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}
			</span>
			</h2>
		</div>
	</div>

	<div class="row">
		<div class="col-tn-1 col-sm-1">&nbsp;</div>{* mimic checkbox column*}

		<div class="coversColumn col-xs-3 col-sm-3 col-md-3 col-lg-2 text-center">
			<img src="/bookcover.php?isn={$record.isbn|@formatISBN}&amp;issn={$record.issn}&amp;size=medium&amp;upc={$record.upc}" {* preserve space for good parsing *}
			     class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}">
		</div>

		<div class="col-xs-8 col-sm-8 col-md-8 col-lg-9">

			<div class="row">
				{if $record.author}
					<div class="result-label col-md-3">Author:</div>
					<div class="col-md-9 result-value  notranslate">
						{if is_array($record.author)}
							{foreach from=$summAuthor item=author}
								<a href='/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
							{/foreach}
						{else}
							<a href='/Author/Home?author="{$record.author|escape:"url"}"'>{$record.author|highlight}</a>
						{/if}
					</div>
				{/if}
			</div>

			{if $seriesVolume}
				{* For Grouped Work Series Page *}
				<div class="row">
					<div class="result-label col-tn-3">{translate text='Series Volume'}:</div>
					<div class="col-tn-9 result-value">{$seriesVolume}</div>
				</div>
			{/if}

			{if $record.publicationDate}
				<div class="row">
					<div class="result-label col-md-3">Published:</div>
					<div class="col-md-9 result-value">{$record.publicationDate|escape}</div>
				</div>
			{/if}

			<div class="row related-manifestations-header">
				<div class="col-xs-12 result-label related-manifestations-label">
					{translate text="Choose a Format"}
				</div>
			</div>

			<div class="row related-manifestation">
				<div class="col-tn-12">
					The library does not own any copies of this title.
				</div>
			</div>

		</div>
	</div>
</div>