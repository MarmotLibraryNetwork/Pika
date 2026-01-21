{strip}
	<div class="panel" id="titlesDisplayPanel"><a data-toggle="collapse" href="#titlesDisplayPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Titles & Display Labels</h2>
			</div>
		</a>
		<div id="titlesDisplayPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Title" value=$title}
				{include file="Archive2/partials/fieldRow.tpl" label="Display Title" value=$display_title}
				{include file="Archive2/partials/fieldRow.tpl" label="Full Title" value=$full_title}
				{include file="Archive2/partials/fieldRow.tpl" label="Alternative Title" value=$alternative_title}
				{include file="Archive2/partials/fieldRow.tpl" label="Subtitle" value=$subtitle}
				{include file="Archive2/partials/fieldRow.tpl" label="Album Title" value=$album_title}
				{include file="Archive2/partials/fieldRow.tpl" label="Part Number" value=$part_number}
				{include file="Archive2/partials/fieldRow.tpl" label="Part Of" value=$part_of}
				{include file="Archive2/partials/fieldRow.tpl" label="Call Number" value=$call_number}
			</div>
		</div>
	</div>
{/strip}
