{strip}
	<div class="panel" id="collectionRelationshipsPanel"><a data-toggle="collapse" href="#collectionRelationshipsPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Collection & Relationships</h2>
			</div>
		</a>
		<div id="collectionRelationshipsPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Collection" value=$collection}
				{include file="Archive2/partials/fieldRow.tpl" label="Member Of" value=$member_of}
				{include file="Archive2/partials/fieldRow.tpl" label="Linked Agent" value=$linked_agent}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Event" value=$related_event}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Object Paragraph" value=$related_object_paragraph}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Organization" value=$related_org}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Person Paragraph" value=$related_person_paragraph}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Place" value=$related_place}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Rights Document" value=$related_rights_doc}
				{include file="Archive2/partials/fieldRow.tpl" label="Located At" value=$located_at}
				{include file="Archive2/partials/fieldRow.tpl" label="Location" value=$location}
				{include file="Archive2/partials/fieldRow.tpl" label="Location URL" value=$location_url}
			</div>
		</div>
	</div>
{/strip}
