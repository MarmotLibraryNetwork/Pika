<div class="taxonomy-detail taxonomy-corporate-body">
	<div id="corporate-body-detail-accordion" class="panel-group">

		<div class="panel" id="corpBodyIdentityPanel">
			<a data-toggle="collapse" href="#corpBodyIdentityPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Organization</h2>
				</div>
			</a>
			<div id="corpBodyIdentityPanelBody" class="panel-collapse collapse in">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name"     value=$alternate_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Organization Type"  value=$organization_type}
					{if $organization_url && $organization_url.uri}
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">Website:</div>
						<div class="result-value col-sm-8">
							<a href="{$organization_url.uri|escape}" target="_blank" rel="noopener">
								{if $organization_url.title}{$organization_url.title|escape}{else}{$organization_url.uri|escape}{/if}
							</a>
						</div>
					</div>
					{/if}
					{include file="Archive2/partials/fieldRow.tpl" label="Founded"            value=$founded_year}
					{include file="Archive2/partials/fieldRow.tpl" label="Dissolved"          value=$dissolved_year}
				</div>
			</div>
		</div>

		{if $notes}
		<div class="panel" id="corpBodyNotesPanel">
			<a data-toggle="collapse" href="#corpBodyNotesPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Notes</h2>
				</div>
			</a>
			<div id="corpBodyNotesPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-12">{$notes}</div>
					</div>
				</div>
			</div>
		</div>
		{/if}

		{if $related_place}
		<div class="panel" id="corpBodyRelatedPlacePanel">
			<a data-toggle="collapse" href="#corpBodyRelatedPlacePanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Related Place</h2>
				</div>
			</a>
			<div id="corpBodyRelatedPlacePanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">Place:</div>
						<div class="result-value col-sm-8">
							{if is_array($related_place) && $related_place.url}
								<a href="{$related_place.url}">{$related_place.name|escape}</a>
							{else}
								{$related_place|escape}
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
		{/if}

		{if $related_organization}
		<div class="panel" id="corpBodyRelatedOrgPanel">
			<a data-toggle="collapse" href="#corpBodyRelatedOrgPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Related Organization</h2>
				</div>
			</a>
			<div id="corpBodyRelatedOrgPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Organization" value=$related_organization}
				</div>
			</div>
		</div>
		{/if}

	</div>
</div>
