{strip}
	{if !empty($local_identifier)}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Local Identifier{if is_array($local_identifier) && count($local_identifier) > 1}s{/if}:</div>
			<div class="result-value col-sm-8">
				{if is_array($local_identifier)}{implode subject=$local_identifier glue=', '}{else}{$local_identifier}{/if}
			</div>
		</div>
	{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Identifier" value=$identifier}
	{include file="Archive2/partials/fieldRow.tpl" label="ISBN" value=$isbn}
	{include file="Archive2/partials/fieldRow.tpl" label="OCLC Number" value=$oclc_number}
	{if !empty($located_at)}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Located At:</div>
			<div class="result-value col-sm-8">
				{if is_array($located_at)}{foreach from=$located_at item=loc}<div>{$loc}</div>{/foreach}{else}{$located_at}{/if}
			</div>
		</div>
	{/if}
	{if !empty($shelf_location)}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Shelf Location:</div>
			<div class="result-value col-sm-8">
				{if is_array($shelf_location)}{foreach from=$shelf_location item=loc}<div>{$loc}</div>{/foreach}{else}{$shelf_location}{/if}
			</div>
		</div>
	{/if}
	{if $parent_collection || $debugDetails}
	<div class="row archive-field-row">
		<div class="result-label col-sm-4">Collection:</div>
		<div class="result-value col-sm-8">
			{if $parent_collection}
				{foreach from=$parent_collection item=coll}
					<div><a href="{$coll.url|escape}">{$coll.title|escape}</a></div>
				{/foreach}
			{else}
				<span class="text-muted">Not provided</span>
			{/if}
		</div>
	</div>
	{/if}
	{if $library_name}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Library:</div>
			<div class="result-value col-sm-8">
				{if $library_url}<a href="{$library_url}">{$library_name|escape}</a>{else}{$library_name|escape}{/if}
			</div>
		</div>
	{/if}
{/strip}
