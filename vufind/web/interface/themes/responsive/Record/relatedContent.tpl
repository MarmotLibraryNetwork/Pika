{*  Apparently obsolete. pascal 4/6/22
{strip}
{foreach from=$enrichment.novelist.relatedContent item=contentSection}
	<dt>{$contentSection.title}</dt>
	<dd>
		<ul class="unstyled">
			{foreach from=$contentSection.content item=content}
				<li><a href="{$content.contentUrl}" onclick="return Pika.Account.ajaxLightbox('/GroupedWork/AJAX?method=getNovelistData&novelistUrl={$content.contentUrl|escape:"url"}, true')">{$content.title}{if $content.author} by {$content.author}{/if}</a></li>
			{/foreach}
		</ul>
	</dd>
{/foreach}
{/strip}*}
