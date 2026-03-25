<div class="taxonomy-detail taxonomy-person">
	<div id="person-detail-accordion" class="panel-group">

		<div class="panel" id="personIdentityPanel">
			<a data-toggle="collapse" href="#personIdentityPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Identity</h2>
				</div>
			</a>
			<div id="personIdentityPanelBody" class="panel-collapse collapse in">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Given Name"    value=$given_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Middle Name"   value=$middle_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Family Name"   value=$family_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Maiden Name"   value=$maiden_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Birth Year"    value=$birth_year}
					{include file="Archive2/partials/fieldRow.tpl" label="Death Year"    value=$death_year}
					{include file="Archive2/partials/fieldRow.tpl" label="Race/Ethnicity" value=$race_ethnicity}
				</div>
			</div>
		</div>

		{if $notes}
		<div class="panel" id="personNotesPanel">
			<a data-toggle="collapse" href="#personNotesPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Notes</h2>
				</div>
			</a>
			<div id="personNotesPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-12">{$notes}</div>
					</div>
				</div>
			</div>
		</div>
		{/if}

		{if $military}
		<div class="panel" id="personMilitaryPanel">
			<a data-toggle="collapse" href="#personMilitaryPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Military Service</h2>
				</div>
			</a>
			<div id="personMilitaryPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Branch"          value=$military.branch}
					{include file="Archive2/partials/fieldRow.tpl" label="Conflict"        value=$military.conflict}
					{include file="Archive2/partials/fieldRow.tpl" label="Rank"            value=$military.rank}
					{include file="Archive2/partials/fieldRow.tpl" label="Service Start"   value=$military.svc_start}
					{include file="Archive2/partials/fieldRow.tpl" label="Service End"     value=$military.svc_end}
					{if $military.is_pow}
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">POW:</div>
						<div class="result-value col-sm-8">Yes</div>
					</div>
					{/if}
				</div>
			</div>
		</div>
		{/if}

		{if $academic}
		<div class="panel" id="personAcademicPanel">
			<a data-toggle="collapse" href="#personAcademicPanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Academic</h2>
				</div>
			</a>
			<div id="personAcademicPanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					{include file="Archive2/partials/fieldRow.tpl" label="Position Title"    value=$academic.position_title}
					{include file="Archive2/partials/fieldRow.tpl" label="Degree"            value=$academic.degree_name}
					{include file="Archive2/partials/fieldRow.tpl" label="Discipline"        value=$academic.discipline}
					{include file="Archive2/partials/fieldRow.tpl" label="Graduation Date"   value=$academic.graduation_date}
				</div>
			</div>
		</div>
		{/if}

		{if $related_place}
		<div class="panel" id="personRelatedPlacePanel">
			<a data-toggle="collapse" href="#personRelatedPlacePanelBody">
				<div class="panel-heading">
					<h2 class="panel-title">Related Place</h2>
				</div>
			</a>
			<div id="personRelatedPlacePanelBody" class="panel-collapse collapse">
				<div class="panel-body">
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">
							{if $related_place.relation_label}{$related_place.relation_label}{else}Place{/if}:
						</div>
						<div class="result-value col-sm-8">
							<a href="{$related_place.url}">{$related_place.name|escape}</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		{/if}

	</div>
</div>
