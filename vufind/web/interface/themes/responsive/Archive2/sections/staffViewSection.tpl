{strip}
	{if $isStaffUser}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Islandora URL:</div>
			<div class="result-value col-sm-8"><a href="{$islandora_url}" target="_blank">{$islandora_url}</a></div>
		</div>
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Reload Cache:</div>
			<div class="result-value col-sm-8"><a href="{$cache_reload_url}">{$cache_reload_url}</a></div>
		</div>
		{include file="Archive2/partials/fieldRow.tpl" label="Node ID" value=$nid}
		{include file="Archive2/partials/fieldRow.tpl" label="UUID" value=$uuid}
		{*{include file="Archive2/partials/fieldRow.tpl" label="Status" value=$status} //This always displays as 1 *}
		{include file="Archive2/partials/fieldRow.tpl" label="Created" value=$created}
		{include file="Archive2/partials/fieldRow.tpl" label="Changed" value=$changed}
		{include file="Archive2/partials/fieldRow.tpl" label="Entered By" value=$record_origin}
		{include file="Archive2/partials/fieldRow.tpl" label="Entered On" value=$record_creation_date}
		{include file="Archive2/partials/fieldRow.tpl" label="Last Changed" value=$record_change_date}
		{include file="Archive2/partials/fieldRow.tpl" label="Collection Node ID" value=$member_of}
		{include file="Archive2/partials/fieldRow.tpl" label="Access Terms" value=$access_terms}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Show In Search" value=$pika_show_in_search}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage" value=$pika_usage}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Access Limits" value=$pika_access_limits}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Shown On Homepage" value=$pika_shown_homepage}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Master Download" value=$pika_master_download}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Anon Master Download" value=$pika_anon_master_download}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika LC Download" value=$pika_lc_download}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Anon LC Download" value=$pika_anon_lc_download}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Claim Authorship" value=$pika_claim_authorship}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika DPLA" value=$pika_dpla}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Display" value=$pika_coll_display}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Options" value=$pika_coll_options}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Order" value=$pika_coll_order}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Image Map PID" value=$pika_image_map_pid}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Map Zoom" value=$pika_map_zoom}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Thumbnail URL" value=$pika_thumb_url}
		{include file="Archive2/partials/fieldRow.tpl" label="Pika Related Link" value=$pika_related_link}
		{include file="Archive2/partials/fieldRow.tpl" label="Migrated Filename" value=$migrated_filename}
		{include file="Archive2/partials/fieldRow.tpl" label="Migrated Identifier" value=$migrated_identifier}
		{include file="Archive2/partials/fieldRow.tpl" label="Migrated Relationship Note" value=$migrated_rel_note}
		{include file="Archive2/partials/fieldRow.tpl" label="Legacy MODS ID" value=$legacy_mods_id}
		{include file="Archive2/partials/fieldRow.tpl" label="Legacy PID" value=$pid}
		{include file="Archive2/partials/fieldRow.tpl" label="Owner ID" value=$owner_id}
	{/if}
{/strip}
