{strip}
	{* User's viewing mode toggle switch *}
	<div class="row" id="selected-browse-label">{* browse styling replicated here *}
		<div class="btn-group btn-group-sm" data-toggle="buttons">
			<label for="covers" title="Covers" class="btn btn-sm btn-default"><input onchange="Pika.Archive.toggleDisplayMode(this.id)" type="radio" id="covers">
				<span class="thumbnail-icon"></span><span> Covers</span>
			</label>
			<label for="list" title="Lists" class="btn btn-sm btn-default"><input onchange="Pika.Archive.toggleDisplayMode(this.id)" type="radio" id="list">
				<span class="list-icon"></span><span> List</span>
			</label>
		</div>
		<div class="btn-group" id="hideSearchCoversSwitch"{if $displayMode != 'list'} style="display: none;"{/if}>
			<label for="hideCovers" class="checkbox{* control-label*}"> Hide Covers
				<input id="hideCovers" type="checkbox" onclick="Pika.Archive.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}>
			</label>
		</div>
	</div>
{/strip}
{* Embedded Javascript For this Page *}
<script type="text/javascript">
	$(function(){ldelim}
		if (!Globals.opac && Pika.hasLocalStorage()) {ldelim} {* store setting in browser if not an opac computer *}
			Pika.Account.showCovers = {if $showCovers}true{else}false{/if};
			window.localStorage.setItem('showCovers', Pika.Account.showCovers ? 'on' : 'off');
{*			console.log('Set showcovers to '+ Pika.Account.showCovers); *}
		{rdelim}
		{if !$onInternalIP}
		{* Because content is served on the page, have to set the mode that was used, even if the user didn't choose the mode. *}
		Pika.Archive.displayMode = '{$displayMode}';
		{else}
		Pika.Archive.displayMode = '{$displayMode}';
		Globals.opac = 1; {* set to true to keep opac browsers from storing browse mode *}
		{/if}
		$('#'+Pika.Archive.displayMode).parent('label').addClass('active'); {* show user which one is selected *}

			Pika.Archive.ajaxReloadCallback = function(){ldelim}
				{if $displayType == 'map'}
				Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0, 'true');
				{elseif $displayType == 'mapNoTimeline'}
				Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0, 'false');
				{elseif $displayType == 'timeline'}
				Pika.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0);
				{elseif $displayType == 'scroller'}
				Pika.Archive.reloadScrollerResults('{$exhibitPid|urlencode}', 0);
				{elseif $displayType == 'basic'}
				Pika.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}', 1);
				{else}
				Pika.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}', 1);
				{/if}
			{rdelim};

	{rdelim});
</script>