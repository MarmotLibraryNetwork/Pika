{strip}
	<table class="table table-striped table-condensed">
		<thead>
		<tr>
			{display_if_inconsistent array=$relatedRecords key="publicationDate"}
				<th>Pub. Date</th>
			{/display_if_inconsistent}
			{if $relatedManifestation.isEContent}
				<th>Source</th>
			{/if}
			{display_if_inconsistent array=$relatedRecords key="edition"}
				<th>Edition</th>
			{/display_if_inconsistent}
			{display_if_inconsistent array=$relatedRecords key="publisher"}
				<th>Publisher</th>
			{/display_if_inconsistent}
			{display_if_inconsistent array=$relatedRecords key="physical"}
			<th>
				{if $relatedManifestation.isEContent}
					Content Desc.
				{else}
					Physical Desc.
				{/if}
				</th>
			{/display_if_inconsistent}
{*			{display_if_inconsistent array=$relatedRecords key="language"}*}
{*				<th>Language</th>*}
{*			{/display_if_inconsistent}*}
			<th>Availability</th>
			{display_if_set array=$relatedRecords key="abridged"}
				<th>Abridged</th>
			{/display_if_set}
			<td></td> {* Can't be <th>, for accessiblity. "Table header elements should have visible text. Ensure that the table header can be used by screen reader users. If the element is not a header, marking it up with a `td` is more appropriate." *}
		</tr>
		</thead>
		{foreach from=$relatedRecords item=relatedRecord key=index}
			<tr{if $promptAlternateEdition && $index===0} class="danger"{/if}>
				{* <td>
				{$relatedRecord.holdRatio}
				</td> *}
				{display_if_inconsistent array=$relatedRecords key="publicationDate"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.publicationDate}</a></td>
				{/display_if_inconsistent}
				{if $relatedManifestation.isEContent}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.eContentSource}</a></td>
				{/if}
				{display_if_inconsistent array=$relatedRecords key="edition"}
					<td>{*<a href="{$relatedRecord.url}">*}{$relatedRecord.edition}{*</a>*}</td>
				{/display_if_inconsistent}
				{display_if_inconsistent array=$relatedRecords key="publisher"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.publisher}</a></td>
				{/display_if_inconsistent}
				{display_if_inconsistent array=$relatedRecords key="physical"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.physical}</a></td>
				{/display_if_inconsistent}
{*				{display_if_inconsistent array=$relatedRecords key="language"}*}
{*					<td><a href="{$relatedRecord.url}">{implode subject=$relatedRecord.language glue=","}</a></td>*}
{*				{/display_if_inconsistent}*}
				<td>
					{include file='GroupedWork/statusIndicator.tpl' statusInformation=$relatedRecord viewingIndividualRecord=1}
					{include file='GroupedWork/copySummary.tpl' summary=$relatedRecord.itemSummary totalCopies=$relatedRecord.copies itemSummaryId=$relatedRecord.id recordViewUrl=$relatedRecord.url}
				</td>
				{display_if_set array=$relatedRecords key="abridged"}
					<td>{if $relatedRecord.abridged}Abridged{/if}</td>
				{/display_if_set}
				<td>
					<div class="{*btn-group /* affects the right-top corner when used */ *}btn-group-vertical btn-group-sm">
						<a href="{$relatedRecord.url}" class="btn btn-sm btn-info">More Info</a>
						{foreach from=$relatedRecord.actions item=curAction}
							{if empty($curAction.url)} {* For accessibility, use buttons instead of <a> when there is no URL *}
								<button {if $curAction.onclick}onclick="{$curAction.onclick}"{/if} class="btn btn-sm btn-default" {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</button>
							{else}
								<a href="{$curAction.url}" {if $curAction.onclick}onclick="{$curAction.onclick}"{/if} class="btn btn-sm btn-default" {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</a>
							{/if}
						{/foreach}
					</div>
				</td>
			</tr>
		{/foreach}
	</table>
{/strip}