{strip}
	<div class="panel" id="identifiersAdministrativePanel"><a data-toggle="collapse" href="#identifiersAdministrativePanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Identifiers & Administrative</h2>
			</div>
		</a>
		<div id="identifiersAdministrativePanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Identifier" value=$identifier}
				{include file="Archive2/partials/fieldRow.tpl" label="Local Identifier" value=$local_identifier}
				{include file="Archive2/partials/fieldRow.tpl" label="ISBN" value=$isbn}
				{include file="Archive2/partials/fieldRow.tpl" label="OCLC Number" value=$oclc_number}
				{include file="Archive2/partials/fieldRow.tpl" label="PID" value=$pid}
				{include file="Archive2/partials/fieldRow.tpl" label="Model" value=$model}
				{include file="Archive2/partials/fieldRow.tpl" label="Model TID" value=$model.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Model Name" value=$model.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Model Vocabulary" value=$model.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Owner ID" value=$owner_id}
				{include file="Archive2/partials/fieldRow.tpl" label="Migrated Filename" value=$migrated_filename}
				{include file="Archive2/partials/fieldRow.tpl" label="Migrated Identifier" value=$migrated_identifier}
				{include file="Archive2/partials/fieldRow.tpl" label="Migrated Relationship Note" value=$migrated_rel_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Legacy MODS ID" value=$legacy_mods_id}
				{include file="Archive2/partials/fieldRow.tpl" label="Alternate Search Terms" value=$alt_search_terms}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Thumbnail URL" value=$pika_thumb_url}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Related Link" value=$pika_related_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika DPLA" value=$pika_dpla}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Display" value=$pika_coll_display}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Options" value=$pika_coll_options}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Order" value=$pika_coll_order}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Image Map PID" value=$pika_image_map_pid}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Map Zoom" value=$pika_map_zoom}
				{include file="Archive2/partials/fieldRow.tpl" label="Real Estate Data" value=$real_estate_data}
				{include file="Archive2/partials/fieldRow.tpl" label="Record Content Source" value=$record_content_source}
				{include file="Archive2/partials/fieldRow.tpl" label="Record Origin" value=$record_origin}
				{include file="Archive2/partials/fieldRow.tpl" label="Weight" value=$weight}
			</div>
		</div>
	</div>
{/strip}
