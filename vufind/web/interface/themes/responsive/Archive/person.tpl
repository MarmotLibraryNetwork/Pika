{strip}
	<div class="col-sm-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h1>
		<div class="row">
			<div class="col-sm-4 col-md-5 col-lg-4 col-xl-3 text-center">
				<div class="main-project-image">
					<img src="{$medium_image}" class="img-responsive">
				</div>
			</div>
			<div id="main-content" class="col-sm-8 col-md-7 col-lg-8 col-xl-9">
				{if $genealogyData || $birthDate || $deathDate}
					{if $genealogyData->otherName}
						<div class='personDetail'><span class='result-label'>Other Names: </span><span class='personDetailValue'>{$genealogyData->otherName|escape}</span></div>
					{/if}
					{if $birthDate}
						<div class='personDetail'><span class='result-label'>Birth Date: </span><span class='personDetailValue'>{$birthDate}</span></div>
					{/if}
					{if $deathDate}
						<div class='personDetail'><span class='result-label'>Death Date: </span><span class='personDetailValue'>{$deathDate}</span></div>
					{/if}
					{if $genealogyData->ageAtDeath}
						<div class='personDetail'><span class='result-label'>Age at Death: </span><span class='personDetailValue'>{$genealogyData->ageAtDeath|escape}</span></div>
					{/if}
					{if $genealogyData->sex}
						<div class='personDetail'><span class='result-label'>Sex: </span><span class='personDetailValue'>{$genealogyData->sex|escape}</span></div>
					{/if}
					{if $genealogyData->race}
						<div class='personDetail'><span class='result-label'>Race: </span><span class='personDetailValue'>{$genealogyData->race|escape}</span></div>
					{/if}
					{if $genealogyData->causeOfDeath}
						<div class='personDetail'><span class='result-label'>Cause of Death: </span><span class='personDetailValue'>{$genealogyData->causeOfDeath|escape}</span></div>
					{/if}
				{/if}
			</div>
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script>
	$().ready(function(){ldelim}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
		{rdelim});
</script>