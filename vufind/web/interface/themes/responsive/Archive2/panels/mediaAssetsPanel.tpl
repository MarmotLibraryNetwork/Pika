{strip}
	<div class="panel" id="mediaAssetsPanel"><a data-toggle="collapse" href="#mediaAssetsPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Media Assets</h2>
			</div>
		</a>
		<div id="mediaAssetsPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{if isset($media) && $media|@count > 0}
					{foreach from=$media key=mediaId item=asset}
						<div class="media-asset">
							<h3 class="h4">Asset {$mediaId}</h3>
							{include file="Archive2/partials/fieldRow.tpl" label="Media ID" value=$asset.mid}
							{include file="Archive2/partials/fieldRow.tpl" label="Bundle" value=$asset.bundle}
							{include file="Archive2/partials/fieldRow.tpl" label="Title" value=$asset.title}
							{include file="Archive2/partials/fieldRow.tpl" label="UUID" value=$asset.uuid}
							{include file="Archive2/partials/fieldRow.tpl" label="Version ID" value=$asset.vid}
							{include file="Archive2/partials/fieldRow.tpl" label="Language Code" value=$asset.langcode}
							{include file="Archive2/partials/fieldRow.tpl" label="Revision Created" value=$asset.revision_created}
							{include file="Archive2/partials/fieldRow.tpl" label="Revision User" value=$asset.revision_user}
							{include file="Archive2/partials/fieldRow.tpl" label="Revision Log Message" value=$asset.revision_log_message}
							{include file="Archive2/partials/fieldRow.tpl" label="Status" value=$asset.status}
							{include file="Archive2/partials/fieldRow.tpl" label="Name" value=$asset.name}
							{include file="Archive2/partials/fieldRow.tpl" label="Thumbnail" value=$asset.thumbnail}
							{include file="Archive2/partials/fieldRow.tpl" label="Thumbnail URL" value=$asset.thumbnail.url}
							{include file="Archive2/partials/fieldRow.tpl" label="Thumbnail MIME" value=$asset.thumbnail.mime}
							{include file="Archive2/partials/fieldRow.tpl" label="Thumbnail Filename" value=$asset.thumbnail.filename}
							{include file="Archive2/partials/fieldRow.tpl" label="Created" value=$asset.created}
							{include file="Archive2/partials/fieldRow.tpl" label="Changed" value=$asset.changed}
							{include file="Archive2/partials/fieldRow.tpl" label="Access Terms" value=$asset.field_access_terms}
							{include file="Archive2/partials/fieldRow.tpl" label="Captions" value=$asset.field_captions}
							{include file="Archive2/partials/fieldRow.tpl" label="File Size" value=$asset.field_file_size}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Audio File" value=$asset.field_media_audio_file}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Audio File URL" value=$asset.field_media_audio_file.url}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Audio File MIME" value=$asset.field_media_audio_file.mime}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Audio File Name" value=$asset.field_media_audio_file.filename}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Image" value=$asset.field_media_image}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Image URL" value=$asset.field_media_image.url}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Image MIME" value=$asset.field_media_image.mime}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Image Name" value=$asset.field_media_image.filename}
							{include file="Archive2/partials/fieldRow.tpl" label="Media File" value=$asset.field_media_file}
							{include file="Archive2/partials/fieldRow.tpl" label="Media File URL" value=$asset.field_media_file.url}
							{include file="Archive2/partials/fieldRow.tpl" label="Media File MIME" value=$asset.field_media_file.mime}
							{include file="Archive2/partials/fieldRow.tpl" label="Media File Name" value=$asset.field_media_file.filename}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Of" value=$asset.field_media_of}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Use" value=$asset.field_media_use}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Use TID" value=$asset.field_media_use.tid}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Use Name" value=$asset.field_media_use.name}
							{include file="Archive2/partials/fieldRow.tpl" label="Media Use Vocabulary" value=$asset.field_media_use.vocabulary}
							{include file="Archive2/partials/fieldRow.tpl" label="MIME Type" value=$asset.field_mime_type}
							{include file="Archive2/partials/fieldRow.tpl" label="Original Name" value=$asset.field_original_name}
							{include file="Archive2/partials/fieldRow.tpl" label="Height" value=$asset.field_height}
							{include file="Archive2/partials/fieldRow.tpl" label="Width" value=$asset.field_width}
							{include file="Archive2/partials/fieldRow.tpl" label="Complete" value=$asset.field_complete}
							{include file="Archive2/partials/fieldRow.tpl" label="FITS OIS File Information MD5" value=$asset.fits_ois_file_information_md5che}
						</div>
					{/foreach}
				{else}
					<p class="text-muted">No media assets supplied.</p>
				{/if}
			</div>
		</div>
	</div>
{/strip}
