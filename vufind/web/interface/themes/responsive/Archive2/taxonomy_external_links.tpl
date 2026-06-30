{if $links}
	<div class="panel" id="taxonomyLinksPanel">
		<a data-toggle="collapse" href="#taxonomyLinksPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Links</h2>
			</div>
		</a>
		<div id="taxonomyLinksPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				<ul class="list-unstyled">
					{foreach from=$links item=link}
						<li><a href="{$link.uri|escape}">{$link.title|escape}</a></li>
					{/foreach}
				</ul>
			</div>
		</div>
	</div>
{/if}