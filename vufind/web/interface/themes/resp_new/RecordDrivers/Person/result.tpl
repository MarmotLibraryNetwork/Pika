{strip}
<div id="record{$summId|escape}" class="resultsList row">
    {if isset($summExplain)}
			<div class="hidden" id="scoreExplanationValue{$summId|escape}">
				<samp  style="overflow-wrap: break-word">{$summExplain}</samp>
			</div>
    {/if}

	<div class="imageColumn col-md-3">
		<div class="selectTitle col-md-4">
			<label for="selected{if $summShortId}{$summShortId}{else}{$summId|escape}{/if}" class="resultIndex checkbox"><strong>{$resultIndex}</strong>
				<input type="checkbox" name="selected[{$summShortId|escape:"url"}]" id="selected{$summShortId|escape:"url"}" style="display:none">&nbsp;
			</label>
		</div>

		<div class="col-md-7 text-center">
			<a href="/Person/{$summShortId}">
			{if $summPicture}
			<img src="{$summPicture}" class="alignleft listResultImage" alt="{translate text='Picture'}"><br>
			{else}
			<img src="/interface/themes/default/images/person.png" class="alignleft listResultImage" alt="{translate text='No Cover Image'}"><br>
			{/if}
			</a>
		</div>
	</div>

	<div class="col-md-9">
		<div class="row">
			<strong>
				<a href="{$recordDriver->getLinkUrl()}" class="title">{if !$summTitle}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}</a>
				{if $summTitleStatement}
					<div class="searchResultSectionInfo">
					{$summTitleStatement|removeTrailingPunctuation|truncate:180:"..."|highlight}
					</div>
				{/if}
				{if isset($summScore)}
					&nbsp;(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$summId|escape}');">{$summScore}</a>)
				{/if}
			</strong>
		</div>

		<div class="row">
			<div class="resultDetails col-md-9">
				{if $birthDate}
					<div class="row">
						<div class='result-label col-md-3'>Born: </div>
						<div class="col-md-9 result-value">{$birthDate}</div>
					</div>
				{/if}
				{if $deathDate}
					<div class="row">
						<div class='result-label col-md-3'>Died: </div>
						<div class="col-md-9 result-value">{$deathDate}</div>
					</div>
				{/if}
				{if $numObits}
					<div class="row">
						<div class='result-label col-md-3'>Num. Obits: </div>
						<div class="col-md-9 result-value">{$numObits}</div>
					</div>
				{/if}
				{if $dateAdded}
					<div class="row">
						<div class='result-label col-md-3'>Added: </div>
						<div class="col-md-9 result-value">{$dateAdded|date_format}</div>
					</div>
				{/if}
				{if $lastUpdate}
					<div class="row">
						<div class='result-label col-md-3'>Last Update: </div>
						<div class="col-md-9 result-value">{$lastUpdate|date_format}</div>
					</div>
				{/if}
				
			</div>
		</div>
	</div>
</div>
{/strip}