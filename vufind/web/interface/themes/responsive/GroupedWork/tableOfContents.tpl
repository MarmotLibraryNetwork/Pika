{strip}
	{* This Template is the default template used by Interface.php *}
	<div id="tableOfContentsPlaceholder" style="display:none"{if $tableOfContents} class="loaded"{/if}>

	{if $tableOfContents}
		<ol class="list-unstyled">
		{foreach from=$tableOfContents item=note}
			<li>{$note}</li>
		{/foreach}
		</ol>
		<script>
			Pika.GroupedWork.hasTableOfContentsInRecord = true;
		</script>
	{else}
		Loading Table Of Contents...
	{/if}

	</div>
	<div id="avSummaryPlaceholder"></div>
{/strip}