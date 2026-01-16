{strip}
	<div class="panel" id="publicationProductionPanel"><a data-toggle="collapse" href="#publicationProductionPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Publication & Production Info</h2>
			</div>
		</a>
		<div id="publicationProductionPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Publisher" value=$publisher}
				{include file="Archive2/partials/fieldRow.tpl" label="Publication Statement" value=$publication_s}
				{include file="Archive2/partials/fieldRow.tpl" label="Published In" value=$published_in}
				{include file="Archive2/partials/fieldRow.tpl" label="Statement of Responsibility" value=$statement_of_resp}
				{include file="Archive2/partials/fieldRow.tpl" label="Action Note" value=$action_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Presented At" value=$presented_at}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Name" value=$degree_name}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Discipline" value=$degree_discipline}
				{include file="Archive2/partials/fieldRow.tpl" label="Catalog Link" value=$catalog_link}
			</div>
		</div>
	</div>
{/strip}
