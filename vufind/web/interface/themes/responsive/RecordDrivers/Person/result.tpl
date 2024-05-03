{strip}
<div id="record{$summId|escape}" class="resultsList">
    {if isset($summExplain)}
			<div class="hidden" id="scoreExplanationValue{$summId|escape}">
				<samp  style="overflow-wrap: break-word">{$summExplain}</samp>
			</div>
    {/if}

	{* Title Row *}
	<div class="row result-title-row">
		<div class="col-tn-12">
			<span class="result-index">{$resultIndex}.</span>&nbsp;
			<a href="{$recordDriver->getLinkUrl()}" class="result-title notranslate">{if !$summTitle}{translate text='Name not available'}{else}{$summTitle|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}</a>

			{* No Title statemennts for Genealogy Persons
			{if $summTitleStatement}
				<div class="searchResultSectionInfo">
					{$summTitleStatement|removeTrailingPunctuation|truncate:180:"..."|highlight}
				</div>
			{/if}
			*}

			{if isset($summScore)}
				&nbsp;(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$summId|escape}');">{$summScore}</a>)
			{/if}
		</div>
	</div>

	<div class="row">
{* Checkboxes currently not used for genealogy
				<div class="col-tn-1 col-sm-1" aria-live="polite">
					<input type="checkbox" id="select_{$summId|escape}" class="checkbox checkbox-results" aria-label="Add person to bookshelf" title="Add person to bookshelf">
				</div>

			<div class="col-tn-3 col-xs-3 col-sm-3 col-md-3 col-lg-2 text-center"> *}
			<div class="col-tn-4 col-xs-4 col-sm-4 col-md-4 col-lg-3 text-center">
				<a href="/Person/{$summShortId}">
				{if $summPicture}
					<img src="{$summPicture}" class="listResultImage" alt="{translate text='Picture'} of {if $summTitle}{$summTitle}{else}person{/if}"><br>
				{else}
					<img src="/interface/themes/default/images/person.png" class="listResultImage" alt=""><br>
				{/if}
				</a>
			</div>


		<div class="col-tn-8 col-xs-8 col-sm-8 col-md-8 col-lg-9">
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
</div>
{/strip}