{if $replacementTerm}
{strip}
	<div id="replacement-search-info-block" class="alert alert-warning">
		<p>There were <strong>no results </strong> for the original search <i><strong>{$oldTerm}</strong></i>. Showing results for <strong>{$replacementTerm}</strong> instead.</p>
		<p>See results for original search <a href="{$oldSearchUrl}"><strong>{$oldTerm}</strong></a></p>
	</div>
{/strip}
{/if}
