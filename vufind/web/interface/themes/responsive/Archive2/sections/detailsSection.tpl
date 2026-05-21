{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Resource Type" value=$resource_type.name}
	{include file="Archive2/partials/fieldRow.tpl" label="Genre" value=$genre.name}
	{include file="Archive2/partials/fieldRow.tpl" label="Language" value=$languageName}
	{include file="Archive2/partials/fieldRow.tpl" label="Date Created" value=$edtf_date_created}
	{include file="Archive2/partials/fieldRow.tpl" label="Date Issued" value=$edtf_date_issued}
	{include file="Archive2/partials/fieldRow.tpl" label="Date" value=$edtf_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Date Captured" value=$date_captured}
	{include file="Archive2/partials/fieldRow.tpl" label="Copyright Date" value=$copyright_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Date (Text)" value=$date_text}
	{include file="Archive2/partials/fieldRow.tpl" label="Postmark" value=$postmark}
	{include file="Archive2/partials/fieldRow.tpl" label="Physical Form" value=$physical_form}
	{include file="Archive2/partials/fieldRow.tpl" label="Extent" value=$extent}
	{include file="Archive2/partials/fieldRow.tpl" label="Measurement" value=$measurement}
	{include file="Archive2/partials/fieldRow.tpl" label="Creator" value=$linked_agent}
	{*TODO: remove Creator *}
	{include file="Archive2/partials/fieldRow.tpl" label="Statement of Responsibility" value=$statement_of_responsibility}
	{include file="Archive2/partials/fieldRow.tpl" label="Publisher" value=$publisher}
{/strip}
