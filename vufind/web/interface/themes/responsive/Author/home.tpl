{strip}
		{include file="Search/bookbag.tpl"}
<div>
	<h2>{$authorName}</h2>
	<div class="row">
		<div id="wikipedia_placeholder" class="col-xs-12">
		</div>
	</div>

	<div id="name-variations-placeholder"></div>

	{if $topRecommendations}
		{foreach from=$topRecommendations item="recommendations"}
			{include file=$recommendations}
		{/foreach}
	{/if}

	{* Information about the search *}

	<div class="result-head">

		{if $recordCount}
			{if $displayMode == 'covers'}
				There are {$recordCount|number_format} total results.
			{else}
				{translate text="Showing"} {$recordStart} - {$recordEnd} {translate text='of'} {$recordCount|number_format}
			{/if}
		{/if}
		<span>
			 &nbsp;{translate text='query time'}: {$qtime}s
		</span>

		{* Search Replacement Term notice *}
		{include file="Search/search-replacementTerm-notice.tpl"}

		{if $spellingSuggestions}
			<br><br><div class="correction"><strong>{translate text='spell_suggest'}</strong>:<br>
			{foreach from=$spellingSuggestions item=details key=term name=termLoop}
				{$term|escape} &raquo; {foreach from=$details.suggestions item=data key=word name=suggestLoop}<a href="{$data.replace_url|escape}">{$word|escape}</a>{if $data.expand_url} <a href="{$data.expand_url|escape}"><img src="/images/silk/expand.png" alt="{translate text='spell_expand_alt'}"/></a> {/if}{if !$smarty.foreach.suggestLoop.last}, {/if}{/foreach}{if !$smarty.foreach.termLoop.last}<br>{/if}
			{/foreach}
		</div>
		{/if}

		{* Search Debugging *}
		{include file="Search/search-debug.tpl"}


		{* User's viewing mode toggle switch *}
		{include file="Search/results-displayMode-toggle.tpl"}

		<div class="clearer"></div>
	</div>
	{* End Listing Options *}

	{include file=$resultsTemplate}

	{if $displayMode == 'covers'}
		{if $recordEnd < $recordCount}
			<a onclick="return Pika.Searches.getMoreResults()">
				<div class="row" id="more-browse-results">
					<img src="{img filename="browse_more_arrow.png"}" alt="Load More Search Results" title="Load More Search Results">
				</div>
			</a>
		{/if}
	{else}
		{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
	{/if}

		{include file="Search/searchTools.tpl" showAdminTools=true}
</div>
{/strip}

{* Embedded Javascript For this Page *}
	<script type="text/javascript">
		$(document).ready(function (){ldelim}
		{if $showWikipedia}
			Pika.Wikipedia.getWikipediaArticle('{$wikipediaAuthorName}');
		{/if}
			var url = '/Author/AJAX?method=nameVariations&author="' + {$lookfor} + '"';
			$.getJSON(url, function(data){ldelim}
							if (data.success) $("#name-variations-placeholder").html(data.body).fadeIn();
			{rdelim});

			{*{include file="Search/results-displayMode-js.tpl"}*}
			{if !$onInternalIP}
			{*if (!Globals.opac &&Pika.hasLocalStorage()){ldelim}*}
			{*var temp = window.localStorage.getItem('searchResultsDisplayMode');*}
			{*if (Pika.Searches.displayModeClasses.hasOwnProperty(temp)) Pika.Searches.displayMode = temp; *}{* if stored value is empty or a bad value, fall back on default setting ("null" returned when not set) *}
			{*else Pika.Searches.displayMode = '{$displayMode}';*}
			{*{rdelim}*}
			{*else*}
			{* Because content is served on the page, have to set the mode that was used, even if the user didn't chose the mode. *}
			Pika.Searches.displayMode = '{$displayMode}';
			{else}
			Pika.Searches.displayMode = '{$displayMode}';
			Globals.opac = 1; {* set to true to keep opac browsers from storing browse mode *}
			{/if}
			$('#'+Pika.Searches.displayMode).parent('label').addClass('active'); {* show user which one is selected *}

			{rdelim});

			$('.checkbox-results').change(function(){ldelim}
			Pika.GroupedWork.showBookbag(this);
				{rdelim});
			$('.bookbag').click(function(){ldelim}
			Pika.GroupedWork.openBookbag(this);
				{rdelim});
			$('body').on('click', 'span.remove', function(){ldelim}
			var checkedId = this.id.replace(/remove_/g, 'select_');
			if($("#"  + checkedId +":checked")){ldelim}
			$("#"+ checkedId).prop("checked", false);
			Pika.GroupedWork.showBookbag(this);
				{rdelim};
				{rdelim});

	</script>
