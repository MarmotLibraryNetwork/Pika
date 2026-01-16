{strip}
	<div class="panel" id="locationPanel"><a data-toggle="collapse" href="#locationPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Location</h2>
			</div>
		</a>
		<div id="locationPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{* Local Identifier *}
				{if !empty($local_identifier)}
					<div class="row">
						<div class="result-label col-sm-4">Local Identifier{if is_array($local_identifier) && count($local_identifier) > 1}s{/if}: </div>
						<div class="result-value col-sm-8">
						{if is_array($local_identifier)}{implode subject=$local_identifier glue=', '}{else}{$local_identifier}{/if}
						</div>
					</div>
				{/if}

				{* Located At *}
				{if !empty($located_at)}
					<div class="row">
						<div class="result-label col-sm-4">Located at: </div>
						<div class="result-value col-sm-8">
							{if is_array($located_at)}
								{foreach from=$located_at item=location}
									<div>{$location}</div>
								{/foreach}
							{else}
								<div>{$located_at}</div>
							{/if}
						</div>
					</div>
				{/if}

				{* Shelf Location *}
				{if !empty($shelf_location)}
					<div class="row">
						<div class="result-label col-sm-4">Shelf Location: </div>
						<div class="result-value col-sm-8">
							{if is_array($located_at)}
								{foreach from=$shelf_location item=location}
									<div>{$location}</div>
								{/foreach}
							{else}
								<div>{$shelf_location}</div>
							{/if}
						</div>
					</div>
				{/if}
			</div>
		</div>
	</div>
{/strip}