{if $isStaffUser}
<div id="taxonomy-metadata">
	<div id="taxonomy-more-details-accordion" class="panel-group">

		<div class="panel" id="taxStaffViewPanel">
			<a data-toggle="collapse" href="#taxStaffViewPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Staff View</h2>
				</div>
			</a>
			<div id="taxStaffViewPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					{if $islandora_taxonomy_url}
						<div class="row archive-field-row">
							<div class="result-label col-sm-4">Islandora URL:</div>
							<div class="result-value col-sm-8"><a href="{$islandora_taxonomy_url}" target="_blank">{$islandora_taxonomy_url}</a></div>
						</div>
					{/if}
					{if $islandora_taxonomy_pika_json_url && $userRoles && in_array('opacAdmin', $userRoles)}
						<div class="row archive-field-row">
							<div class="result-label col-sm-4">Islandora Pika JSON:</div>
							<div class="result-value col-sm-8"><a href="{$islandora_taxonomy_pika_json_url}" target="_blank">{$islandora_taxonomy_pika_json_url}</a></div>
						</div>
					{/if}
					{include file="Archive2/partials/fieldRow.tpl" label="Term ID"         value=$tid}
					{include file="Archive2/partials/fieldRow.tpl" label="Vocabulary"      value=$vocabulary_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Owner"           value=$owner_id}
					{include file="Archive2/partials/fieldRow.tpl" label="Language Code"   value=$langcode}
{*					{include file="Archive2/partials/fieldRow.tpl" label="Published"       value=$status}*}
					{include file="Archive2/partials/fieldRow.tpl" label="Legacy PID"      value=$pid}
					{include file="Archive2/partials/fieldRow.tpl" label="Last Modified"   value=$changed|date_format:'%m/%d/%Y %I:%M:%S'}
					{include file="Archive2/partials/fieldRow.tpl" label="Pika Show In Search" value=$is_shown_in_search}
					{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage"         value=$pika_usage}
				</div>
			</div>
		</div>

	</div>
</div>
{/if}
