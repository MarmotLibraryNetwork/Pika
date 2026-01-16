{strip}
	<div class="panel" id="classificationSubjectsPanel"><a data-toggle="collapse" href="#classificationSubjectsPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Classification & Subjects</h2>
			</div>
		</a>
		<div id="classificationSubjectsPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Classification" value=$classification}
				{include file="Archive2/partials/fieldRow.tpl" label="Dewey Classification" value=$dewey_classification}
				{include file="Archive2/partials/fieldRow.tpl" label="LCC Classification" value=$lcc_classification}
				{include file="Archive2/partials/fieldRow.tpl" label="Non-MARC Filing Suffix" value=$non_marc_filing_suffix}
				{include file="Archive2/partials/fieldRow.tpl" label="Genre" value=$genre}
				{include file="Archive2/partials/fieldRow.tpl" label="Genre TID" value=$genre.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Genre Name" value=$genre.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Genre Vocabulary" value=$genre.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Resource Type" value=$resource_type}
				{include file="Archive2/partials/fieldRow.tpl" label="Resource Type TID" value=$resource_type.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Resource Type Name" value=$resource_type.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Resource Type Vocabulary" value=$resource_type.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Legacy Resource Type" value=$legacy_resource_type}
				{include file="Archive2/partials/fieldRow.tpl" label="Legacy Resource Type TID" value=$legacy_resource_type.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Legacy Resource Type Name" value=$legacy_resource_type.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Legacy Resource Type Vocabulary" value=$legacy_resource_type.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Language" value=$language}
				{include file="Archive2/partials/fieldRow.tpl" label="Language TID" value=$language.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Language Name" value=$language.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Language Vocabulary" value=$language.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Transcription Language" value=$transcription_lang}
				{include file="Archive2/partials/fieldRow.tpl" label="Transcription Language TID" value=$transcription_lang.tid}
				{include file="Archive2/partials/fieldRow.tpl" label="Transcription Language Name" value=$transcription_lang.name}
				{include file="Archive2/partials/fieldRow.tpl" label="Transcription Language Vocabulary" value=$transcription_lang.vocabulary}
				{include file="Archive2/partials/fieldRow.tpl" label="Subject" value=$subject}
				{include file="Archive2/partials/fieldRow.tpl" label="Subject (General)" value=$subject_general}
				{include file="Archive2/partials/fieldRow.tpl" label="Subjects (Name)" value=$subjects_name}
				{include file="Archive2/partials/fieldRow.tpl" label="Geographic Subject" value=$geographic_subject}
				{include file="Archive2/partials/fieldRow.tpl" label="Target Audience" value=$target_audience}
				{include file="Archive2/partials/fieldRow.tpl" label="Genealogy Link" value=$genealogy_link}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Name" value=$degree_name}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Discipline" value=$degree_discipline}
			</div>
		</div>
	</div>
{/strip}
