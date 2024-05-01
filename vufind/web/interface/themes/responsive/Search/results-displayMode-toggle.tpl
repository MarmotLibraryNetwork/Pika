{strip}
	{* User's viewing mode toggle switch *}
	<div class="row" id="selected-browse-label">{* browse styling replicated here *}
		<div class="btn-group btn-group-sm" data-toggle="buttons">
			<button tabindex="0" title="Covers" class="btn btn-sm btn-default displayMode" aria-label="change results to cover layout" onclick="Pika.Searches.toggleDisplayMode(this.id)" id="covers">
				<span class="thumbnail-icon"></span><span> Covers</span>
			</button>
			<button tabindex="0" title="Lists" class="btn btn-sm btn-default displayMode" aria-label="change results to list layout" onclick="Pika.Searches.toggleDisplayMode(this.id)" id="list">
				<span class="list-icon"></span><span> List</span>
			</button>
		</div>
		<div class="btn-group" id="hideSearchCoversSwitch"{if $displayMode != 'list'} style="display: none;"{/if}>
			<label for="hideCovers" class="checkbox{* control-label*}"> Hide Covers
				<input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}>
			</label>
		</div>
	</div>
{/strip}