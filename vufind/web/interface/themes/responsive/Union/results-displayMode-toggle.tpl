{strip}
	{* User's viewing mode toggle switch *}
	<div class="row" id="selected-browse-label">{* browse styling replicated here *}
		<div class="btn-group" id="hideSearchCoversSwitch">
			<label for="hideCovers" class="checkbox{* form-label*}"> Hide Covers
				<input id="hideCovers" type="checkbox" onclick="return reloadCombinedResults();">
			</label>
		</div>
	</div>
{/strip}