{strip}
	<div class="panel" id="pikaControlsPanel"><a data-toggle="collapse" href="#pikaControlsPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Pika-Specific Controls & Flags</h2>
			</div>
		</a>
		<div id="pikaControlsPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Access Limits" value=$pika_access_limits}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Anonymous LC Download" value=$pika_anon_lc_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Anonymous Master Download" value=$pika_anon_master_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Claim Authorship" value=$pika_claim_authorship}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Display" value=$pika_coll_display}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Options" value=$pika_coll_options}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Collection Order" value=$pika_coll_order}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika DPLA" value=$pika_dpla}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Image Map PID" value=$pika_image_map_pid}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika LC Download" value=$pika_lc_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Map Zoom" value=$pika_map_zoom}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Master Download" value=$pika_master_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Related Link" value=$pika_related_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Shown On Homepage" value=$pika_shown_homepage}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Show In Search" value=$pika_show_in_search}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Thumbnail URL" value=$pika_thumb_url}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage" value=$pika_usage}
			</div>
		</div>
	</div>
{/strip}
