{if $recordDriver}
<script type="text/javascript">
	{literal}$(function(){{/literal}
		VuFind.GroupedWork.loadEnrichmentInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		VuFind.GroupedWork.loadReviewInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		{if $enableProspectorIntegration == 1}
		VuFind.Prospector.loadRelatedProspectorTitles('{$recordDriver->getPermanentId()|escape:"url"}');
		{/if}
		{literal}});{/literal}
</script>
{/if}