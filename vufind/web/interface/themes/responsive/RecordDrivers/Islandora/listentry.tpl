{strip}
	<div id="archive{$jquerySafeId|escape}" class="resultsList" data-order="{$resultIndex}">
		{* Because colons give css & jquery trouble the Ids from Islandora have : replaced with _ *}
		{* the data-order attribute is used for user-defined ordering in user lists  *}
		<a id="record{$summId|escape:"url"}"></a>{* TODO: remove colons from these Ids as well *}

		{* Title row *}
		<div class="row result-title-row">
			<div class="col-12">
				<h2 class="h3">
					<span class="result-index">{$resultIndex}.</span>&nbsp;
					<a href="{$summUrl}" class="result-title notranslate">{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}</a>
					{if $summTitleStatement}
						&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|highlight|truncate:180:"..."}
					{/if}
				</h2>
			</div>
		</div>

		<div class="row">
			<div class="col-lg-1"><input type="checkbox" name="marked" id="favorite_{$summId|escape}" class="form-control-static" value="{$summId|escape}"  aria-label="Select archive item to delete"></div>
			{if $showCovers}
			<div class="col-sm-11 col-md-3 col-lg-3 col-xl-2 text-center">
				{if $disableCoverArt != 1}
					<a href="{$summUrl}">
						<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-fluid" alt="Thumbnail{if $summTitle} for '{$summTitle|escape}'{/if}">
					</a>
				{/if}
			</div>
			{/if}

			<div class="{if !$showCovers}col-sm-9 col-md-9 col-lg-9 col-xl-10{else}col-sm-6 col-md-6 col-lg-6 col-xl-8{/if}">

				{if $summAuthor}
					<div class="row">
						<div class="result-label col-sm-3">Author: </div>
						<div class="col-sm-9 result-value  notranslate">
							{if is_array($summAuthor)}
								{foreach from=$summAuthor item=author}
									<a href='/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
								{/foreach}
							{else}
								<a href='/Author/Home?author="{$summAuthor|escape:"url"}"'>{$summAuthor|highlight}</a>
							{/if}
						</div>
					</div>
				{/if}

				{if $listEntryNotes}
					{* User List Notes*}
					<div class="row">
						<div class="result-label col-lg-3">Notes: </div>
						<div class="user-list-entry-note result-value col-lg-9">
							{$listEntryNotes}
						</div>
					</div>
				{/if}

				{if $summPublisher}
					<div class="row">
						<div class="result-label col-sm-3">Publisher: </div>
						<div class="col-sm-9 result-value">
							{$summPublisher}
						</div>
					</div>
				{/if}

				{if $summFormat}
					<div class="row">
						<div class="result-label col-sm-3">Format: </div>
						<div class="col-sm-9 result-value">
							{$summFormat}
						</div>
					</div>
				{/if}

				{if $summPubDate}
					<div class="row">
						<div class="result-label col-sm-3">Pub. Date: </div>
						<div class="col-sm-9 result-value">
							{$summPubDate|escape}
						</div>
					</div>
				{/if}

				{if $summSnippets}
					{foreach from=$summSnippets item=snippet}
						<div class="row">
							<div class="result-label col-sm-3">{translate text=$snippet.caption}: </div>
							<div class="col-sm-9 result-value">
								{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br>{/if}
							</div>
						</div>
					{/foreach}
				{/if}

				<div class="row">
					<div class="col-sm-12 result-value" id="descriptionValue{$summId|escape}">{$summDescription|highlight|truncate_html:450:"..."}</div>
				</div>

				<div class="row">
					<div class="col-sm-12">
						{include file='Archive2/result-tools-horizontal.tpl'}
					</div>
				</div>

			</div>

			<div class="col-sm-2 col-md-2 col-lg-2 col-xl-1">
				{if $listEditAllowed}
					<div class="btn-group-vertical" role="group">
						<a href="/MyAccount/Edit?titleIdForListEntry={$summId|escape:"url"}{if !is_null($listSelected)}&amp;list_id={$listSelected|escape:"url"}{/if}" class="btn btn-outline-secondary">{translate text='Edit'}</a>
						{* Use a different delete URL if we're removing from a specific list or the overall favorites: *}
						<a href="/MyAccount/MyList/{$listSelected|escape:"url"}?delete={$summId|escape:"url"}" onclick="return confirm('Are you sure you want to delete this?');" class="btn btn-danger">{translate text='Delete'}</a>
					</div>
				{/if}
			</div>
		</div>
	</div>
{/strip}