{strip}
	{foreach from=$rightsStatements item=rightsStatement}
		<div class="rightsStatement">{$rightsStatement}</div>
	{/foreach}
	{if !empty($limitationsNotes)}
		<div><em>{$limitationsNotes}</em></div>
	{/if}

	{if !empty($rightsHolders)}
		<div>
			<em>Rights held by&nbsp;
				{foreach from=$rightsHolders item="rightsHolder" name="rightsHolders"}
					{if $smarty.foreach.rightsHolders.iteration > 1}, {/if}
					{if $rightsHolder.link}<a href="{$rightsHolder.link}">{/if}{$rightsHolder.label}{if $rightsHolder.link}</a>{/if}
				{/foreach}
			</em>
		</div>
	{/if}
	{if $rightsCreatorTitle}
		<div><em>Rights created by <a href="{$rightsCreatorLink}">{$rightsCreatorTitle}</a></em></div>
	{/if}
	{if !empty($rightsEffectiveDate) || !empty($rightsExpirationDate)}
		<div><em>{if !empty($rightsEffectiveDate)}Rights statement effective {$rightsEffectiveDate}.  {/if}{if !empty($rightsEffectiveDate)}Rights statement expires {$rightsExpirationDate}.  {/if}</em></div>
	{/if}

	<div class="row">
		<div class="result-label col-sm-4">rightsstatements.org statement:</div>
		<div class="result-value col-sm-8">
			<a href='{$rightsStatementOrg}' target="_blank">
				{$rightsStatementOrg|translate}
			</a>
		</div>
	</div>

{/strip}