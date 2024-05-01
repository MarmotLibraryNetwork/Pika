{strip}
	{* User's viewing mode toggle switch *}
	<div class="row" id="selected-browse-label">{* browse styling replicated here *}
		<div class="btn-group btn-group-sm" data-toggle="buttons">
			<button tabindex="0" title="Covers" aria-label="change results to cover layout" onclick="Pika.Archive.toggleDisplayMode(this.id)" id="covers" class="btn btn-sm btn-default displayMode">
				<span class="thumbnail-icon"></span><span> Covers</span>
			</button>
			<button tabindex="0" title="Lists" aria-label="change results to list layout"  onclick="Pika.Archive.toggleDisplayMode(this.id)" type="radio" id="list"class="btn btn-sm btn-default displayMode">
				<span class="list-icon"></span><span> List</span>
			</button>
		</div>
		<div class="btn-group" id="hideSearchCoversSwitch"{if $displayMode != 'list'} style="display: none;"{/if}>
			<label for="hideCovers" class="checkbox{* control-label*}"> Hide Covers
				<input id="hideCovers" type="checkbox" onclick="Pika.Archive.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}>
			</label>
		</div>
	</div>
{/strip}
{* Embedded Javascript For this Page *}
<script>
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
		$('#'+Pika.Archive.displayMode).addClass('active'); {* show user which one is selected *}

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