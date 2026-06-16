{include file="Archive2/partials/fieldRow.tpl" label="Postmark" value=$postmark}
{foreach from=$related_place item=place}
	{if $place.relation eq 'local:pml'}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Postmark Location: </div>
			<div class="result-value col-sm-8">
				{if $place.tid}
					<a href="/Archive2/Place/{$place.tid}">{$place.name|escape}</a>
				{else}
					{$place.name|escape}
				{/if}
			</div>
		</div>
	{/if}
{/foreach}
{if $display_model eq 'Postcard' && isset($includes_stamp)}{* $includes_stamp is boolean and likely set for every object *}
	{if $includes_stamp}{assign var="stamp_display" value="Yes"}{else}{assign var="stamp_display" value="No"}{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Includes Stamp" value=$stamp_display}
{/if}
{include file="Archive2/partials/fieldRow.tpl" label="Postcard Publisher Number" value=$postcard_pub_num}
