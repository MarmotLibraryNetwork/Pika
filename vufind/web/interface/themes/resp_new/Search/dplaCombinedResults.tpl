{strip}
	<div id="dplaSearchResults">
		{foreach from=$searchResults item=result name="recordLoop"}
			<div class="result">
				<div class="dplaResult resultsList row">
					<div class="col-xs-12">
						<div class="row">
							<div class="col-xs-12">
								<span class="result-index">{$smarty.foreach.recordLoop.iteration})</span>&nbsp;
								<a href="{$result.link}" class="result-title notranslate">
									{if !$result.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$result.title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}
								</a>
							</div>
						</div>

						{if $result.format}
							<div class="row">
								<div class="result-label col-tn-3">{translate text='Format'}:</div>
								<div class="col-tn-9 result-value">{$result.format|escape}</div>
							</div>
						{/if}

						{*
						{if $result.publisher}
							<div class="row">
								<div class="result-label col-tn-3">{translate text='Publisher'}:</div>
								<div class="col-tn-9 result-value">{$result.publisher|escape}</div>
							</div>
						{/if}

						{if $result.date}
							<div class="row">
								<div class="result-label col-tn-3">{translate text='Date'}:</div>
								<div class="col-tn-9 result-value">{$result.date|escape}</div>
							</div>
						{/if}
						*}

						{if $result.description}
							<div class="row well-small">
								<div class="col-tn-12 result-value">{$result.description|truncate_html:450:"..."|strip_tags|htmlentities}</div>
							</div>
						{/if}
					</div>
				</div>
			</div>
		{/foreach}
	</div>
{/strip}