{strip}
	<div id="main-content">
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		{* Internal Grid *}
		<h1 role="heading" aria-level="1" class="h2 myAccountTitle">{translate text='Recommended for you'}</h1>

		<div id="pager" class="navbar form-inline">
			<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
		</div>

      {if !empty($resourceList)}
				<div class="striped">
            {foreach from=$resourceList item=suggestion}
                {*<div class="result {if ($suggestion@iteration % 2) == 0}alt{/if} record{$suggestion@iteration}">*}
							<div class="result record{$suggestion@iteration}">
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