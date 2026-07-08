{include file="Archive2/partials/fieldRow.tpl" label="Album Title" value=$album_title}
{include file="Archive2/partials/fieldRow.tpl" label="Track Number" value=$track_number}
{include file="Archive2/partials/fieldRow.tpl" label="Total Tracks" value=$total_tracks}
{include file="Archive2/partials/fieldRow.tpl" label="Disc Number" value=$disc_number}
{include file="Archive2/partials/fieldRow.tpl" label="Total Discs" value=$total_discs}
{if $music_genre}
	<div class="row archive-field-row">
		<div class="result-label col-sm-4">Genre:</div>
		<div class="result-value col-sm-8">
			{if isset($music_genre.name)}
				{$music_genre.name|escape}
			{else}
				{foreach from=$music_genre item=genre}
					<div>{$genre.name|escape}</div>
				{/foreach}
			{/if}
		</div>
	</div>
{/if}
