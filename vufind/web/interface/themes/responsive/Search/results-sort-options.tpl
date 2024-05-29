{strip}
	{* Sort the results*}
	{if $recordCount && $action != 'MyList'}
		{* Do not display search sort while displaying a list *}
		<div id="results-sort-label" class="row results-sidebar-label"{if $displaySidebarMenu} style="display: none"{/if}>
			<label for="results-sort">{translate text='Sort Results By'}</label>
		</div>
		{* The div below has to be immediately after the div above for the menubar hiding/showing to work *}
		<div class="row"{if $displaySidebarMenu} style="display: none"{/if}>
			<div class="input-group">
				<select id="results-sort" name="sort" class="form-control">
					{foreach from=$sortList item=sortData key=sortLabel}
						<option value="{$sortData.sortUrl|escape}"{if $sortData.selected} selected="selected"{/if}>{translate text=$sortData.desc}</option>
					{/foreach}
				</select>
				<span class="input-group-btn">
					<button class="btn btn-primary" onclick="document.location.href = document.getElementById('results-sort').options[document.getElementById('results-sort').selectedIndex ].value">GO</button>
				</span>
			</div>
		</div>
	{/if}
{/strip}