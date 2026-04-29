{if $recordDriver}
<script>
	{literal}$(function(){{/literal}
		Pika.GroupedWork.loadEnrichmentInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		Pika.GroupedWork.loadReviewInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		{literal}});{/literal}
</script>
{/if}