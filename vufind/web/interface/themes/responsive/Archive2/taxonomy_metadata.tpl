<div id="taxonomy-metadata">
	<div id="taxonomy-more-details-accordion" class="panel-group">

		<div class="panel" id="taxCorePanel">
			<a data-toggle="collapse" href="#taxCorePanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Record Information</h2>
				</div>
			</a>
			<div id="taxCorePanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Term ID"         value=$tid}
					{include file="Archive2/partials/fieldRow.tpl" label="Vocabulary"      value=$vocabulary_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Legacy PID"      value=$pid}
					{include file="Archive2/partials/fieldRow.tpl" label="Owner"           value=$owner_id}
					{include file="Archive2/partials/fieldRow.tpl" label="Language Code"   value=$langcode}
					{include file="Archive2/partials/fieldRow.tpl" label="Published"       value=$status}
					{include file="Archive2/partials/fieldRow.tpl" label="Last Modified"   value=$changed}
				</div>
			</div>
		</div>

	</div>
</div>
