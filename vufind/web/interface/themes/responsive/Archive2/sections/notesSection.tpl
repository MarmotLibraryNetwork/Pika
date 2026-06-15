{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="General Note" value=$note}
	{include file="Archive2/partials/fieldRow.tpl" label="General Note" value=$general_note}
	{*TODO: Does general_note exist? might just be note always *}
	{include file="Archive2/partials/fieldRow.tpl" label="Local Note" value=$local_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Context Notes" value=$context_notes}
	{include file="Archive2/partials/fieldRow.tpl" label="History" value=$history}
	{include file="Archive2/partials/fieldRow.tpl" label="Acquisition Note" value=$acq_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Arrangement" value=$arrangement}
	{include file="Archive2/partials/fieldRow.tpl" label="Citation Notes" value=$citation_notes}
	{*{include file="Archive2/partials/fieldRow.tpl" label="Material Description" value=$material_description}// displays in art information *}
	{include file="Archive2/partials/fieldRow.tpl" label="Physical Description Note" value=$phys_desc_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Related Materials Note" value=$rel_materials_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Ownership Note" value=$ownership_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Reproduction Note" value=$repro_note}
{/strip}
