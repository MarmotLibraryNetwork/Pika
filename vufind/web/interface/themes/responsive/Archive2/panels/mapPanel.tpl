{if $geolocation}
	<div class="panel active" id="taxonomyMapPanel">
		<a data-toggle="collapse" href="#taxonomyMapPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Map</h2>
			</div>
		</a>
		<div id="taxonomyMapPanelBody" class="panel-collapse collapse in">
			<div class="panel-body">
				{include file="Archive2/sections/mapSection.tpl" coordinates=$geolocation title=$term_title}
			</div>
		</div>
	</div>
{/if}