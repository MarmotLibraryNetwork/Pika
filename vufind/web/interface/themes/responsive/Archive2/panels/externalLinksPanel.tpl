{strip}
	<div class="panel" id="externalLinksPanel"><a data-toggle="collapse" href="#externalLinksPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">External Links & Media References</h2>
			</div>
		</a>
		<div id="externalLinksPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="External Link" value=$external_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Library" value=$library}
				{include file="Archive2/partials/fieldRow.tpl" label="Library TID" value=$library.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Library Name" value=$library.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Library Vocabulary" value=$library.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Catalog Link" value=$catalog_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Further Site Info" value=$further_site_info}
				{include file="Archive2/partials/fieldRow.tpl" label="Main Banner" value=$main_banner}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Related Link" value=$pika_related_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Genealogy Link" value=$genealogy_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika DPLA" value=$pika_dpla}
				{include file="Archive2/partials/fieldRow.tpl" label="Funding Note" value=$funding_note}
			</div>
		</div>
	</div>
{/strip}
