{strip}
{if $info}
	<div class="wikipedia_article">
		{if $info.image}
			<img src="{$info.image}" alt="{$info.altimage|escape}" style="width:150px" class="img-polaroid wikipedia_image">
		{/if}
		{$info.description|truncate_html:4500:"...":false}
		<div class="row smallText">
			<div class="col-xs-12">
				<a href="http://{$wiki_lang}.wikipedia.org/wiki/{$info.name|escape:"url"}" rel="external" onclick="window.open (this.href, 'child'); return false"><span class="note">{translate text='wiki_link'}</span></a>
			</div>
		</div>
	</div>
{/if}
{/strip}