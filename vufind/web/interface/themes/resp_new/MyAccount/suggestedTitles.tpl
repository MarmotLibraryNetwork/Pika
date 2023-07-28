{strip}
	<div id="main-content">
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		{* Internal Grid *}
		<h2 class="myAccountTitle">{translate text='Recommended for you'}</h2>

		<div id="pager" class="navbar form-inline">
			<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
		</div>

      {if !empty($resourceList)}
				<div class="striped">
            {foreach from=$resourceList item=suggestion name=recordLoop}
                {*<div class="result {if ($smarty.foreach.recordLoop.iteration % 2) == 0}alt{/if} record{$smarty.foreach.recordLoop.iteration}">*}
							<div class="result record{$smarty.foreach.recordLoop.iteration}">
                  {$suggestion}
							</div>
            {/foreach}
				</div>
      {else}
				<div class="alert alert-info">
					You have not rated any titles. Please rate some titles so we can display suggestions for you.
				</div>
      {/if}
	</div>
{/strip}