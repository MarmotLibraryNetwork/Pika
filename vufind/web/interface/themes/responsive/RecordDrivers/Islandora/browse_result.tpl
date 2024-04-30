{strip}
	{if $browseMode == 'grid'}
		<div class="browse-list">
			<a href="{$summUrl}">
				<img class="img-responsive" src="{$bookCoverUrl}" alt=""{* Empty alt text since is just duplicates the link text*} {*alt="{$summTitle}"*} title="{$summTitle}">
				<div><strong>{$summTitle}</strong></div>
			</a>
		</div>

	{else}{*Default Browse Mode (covers) *}
		<div class="browse-thumbnail">
			<a href="{$summUrl}">
				<div>
					<img src="{$bookCoverUrlMedium}" alt="{$summTitle}{* by {$summAuthor}*}" title="{$summTitle}">
				</div>
			</a>
		</div>
	{/if}
{/strip}

