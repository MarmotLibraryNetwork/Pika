{strip}
	<div class="panel" id="locationGeographyPanel"><a data-toggle="collapse" href="#locationGeographyPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Location & Geography</h2>
			</div>
		</a>
		<div id="locationGeographyPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Coordinates" value=$coordinates}
				{include file="Archive2/partials/fieldRow.tpl" label="Coordinates Text" value=$coordinates_text}
				{include file="Archive2/partials/fieldRow.tpl" label="Original Location" value=$original_location}
				{include file="Archive2/partials/fieldRow.tpl" label="Place Published" value=$place_published}
				{include file="Archive2/partials/fieldRow.tpl" label="Located At" value=$located_at}
				{include file="Archive2/partials/fieldRow.tpl" label="Location" value=$location}
				{include file="Archive2/partials/fieldRow.tpl" label="Location URL" value=$location_url}
				{include file="Archive2/partials/fieldRow.tpl" label="Shelf Location" value=$shelf_location}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Item Location" value=$rel_item_location}
			</div>
		</div>
	</div>
{/strip}
