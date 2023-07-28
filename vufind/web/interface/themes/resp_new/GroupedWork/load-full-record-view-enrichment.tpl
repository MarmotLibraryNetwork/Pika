{if $recordDriver}
<script type="text/javascript">
	{literal}$(function(){{/literal}
		Pika.GroupedWork.loadEnrichmentInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		Pika.GroupedWork.loadReviewInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		{if $enableProspectorIntegration == 1}
		Pika.Prospector.loadRelatedProspectorTitles('{$recordDriver->getPermanentId()|escape:"url"}');
		{/if}
		{literal}});{/literal}
</script>
{/if}