{strip}
	{if $isStaffUser}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Islandora URL:</div>
			<div class="result-value col-sm-8"><a href="{$islandora_url}" target="_blank">{$islandora_url}</a></div>
		</div>
		{if $userRoles && (in_array('opacAdmin', $userRoles))}
			<div class="row archive-field-row">
				<div class="result-label col-sm-4">Islandora Pika JSON:</div>
				<div class="result-value col-sm-8"><a href="{$islandora_pika_json_url}" target="_blank">{$islandora_pika_json_url}</a></div>
			</div>
		{/if}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Reload Cache:</div>
			<div class="result-value col-sm-8"><a href="{$cache_reload_url}">{$cache_reload_url}</a></div>
		</div>
		{include file="Archive2/partials/fieldRow.tpl" label="Node ID" value=$nid}
		{include file="Archive2/partials/fieldRow.tpl" label="UUID" value=$uuid}
		{*{include file="Archive2/partials/fieldRow.tpl" label="Status" value=$status} //This always displays as 1 *}
		{include file="Archive2/partials/fieldRow.tpl" label="Created" value=$created}{*TODO: patron-friendly display of EDFT conventions? *}
		{include file="Archive2/partials/fieldRow.tpl" label="Changed" value=$changed}
		{include file="Archive2/partials/fieldRow.tpl" label="Entered On" value=$record_creation_date}
		{include file="Archive2/partials/fieldRow.tpl" label="Entered By" value=$record_origin}
		{*{include file="Archive2/partials/fieldRow.tpl" label="Record Content Source" value=$record_content_source}
			Currently Boulder-only field *}
		{include file="Archive2/partials/fieldRow.tpl" label="Last Changed" value=$record_change_date}
		{if $member_of}
			<div class="row archive-field-row">
				<div class="result-label col-sm-4">Collection Node ID:</div>
				<div class="result-value col-sm-8">
					{* member_of may be a single entry (array with 'id' key) or a list of entries *}
					{if is_array($member_of) && isset($member_of.target_id)}
						<div><a href="/Archive2/Collection/{$member_of.target_id}">{$member_of.target_id}</a></div>
					{elseif is_array($member_of) && isset($member_of.id)}
						{*TODO: cofirm if the collection node id is ever set in an 'id' element here. (Confirmed 'target_id; *}
						<div><a href="/Archive2/Collection/{$member_of.id}">{$member_of.id}</a></div>
					{elseif is_array($member_of)}
						{foreach from=$member_of item=col}
							<div>
								{if is_array($col)}
									{if isset($col.target_id)}
										<a href="/Archive2/Collection/{$col.target_id}">{$col.target_id}</a>
									{elseif isset($col.id)}
										<a href="/Archive2/Collection/{$col.id}">{$col.id}</a>
									{/if}
								{else}
									<a href="/Archive2/Collection/{$col}">{$col}</a>
								{/if}
							</div>
						{/foreach}
					{else}
						<div><a href="/Archive2/Collection/{$member_of}">{$member_of}</a></div>
					{/if}
				</div>
			</div>
		{/if}
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
		{include file="Archive2/partials/fieldRow.tpl" label="Owner ID" value=$owner_id}
		{include file="Archive2/partials/fieldRow.tpl" label="Legacy PID" value=$pid}
	{/if}
{/strip}
