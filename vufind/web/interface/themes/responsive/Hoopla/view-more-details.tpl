{strip}
	{* Details not shown in the Top/Main Section of the Record view should be shown here *}
	{if $recordDriver && !$showPublicationDetails && $recordDriver->getPublicationDetails()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Published'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getPublicationDetails() glue=", "}
			</div>
		</div>
	{/if}

	{if !$showFormats}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Format'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getFormats() glue=", "}
			</div>
		</div>
	{/if}

	{if $recordDriver && !$showEditions && $recordDriver->getEdition()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Edition'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getEdition() glue=", "}
			</div>
		</div>
	{/if}


	{if !$showPhysicalDescriptions && $recordDriver->getPhysicalDescriptions()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='eContent_Description_Label'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getPhysicalDescriptions() glue="<br>"}
			</div>
		</div>
	{/if}

	{if $recordDriver->getLanguage()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Language'}:</div>
			<div class="col-xs-9 result-value">
				{$recordDriver->getLanguage()}
			</div>
		</div>
	{/if}

	{if $recordDriver && !$showISBNs && count($recordDriver->getISBNs()) > 0}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='ISBN'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getISBNs() glue=", "}
			</div>
		</div>
	{/if}

	{if $recordDriver && count($recordDriver->getISSNs()) > 0}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='ISSN'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getISSNs() glue=", "}
			</div>
		</div>
	{/if}

	{if $recordDriver && count($recordDriver->getUPCs()) > 0}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='UPC'}:</div>
			<div class="col-xs-9 result-value">
				{implode subject=$recordDriver->getUPCs() glue=", "}
			</div>
		</div>
	{/if}

	{if $recordDriver && $recordDriver->getAcceleratedReaderData() != null}
		{assign var="arData" value=$recordDriver->getAcceleratedReaderData()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Accelerated Reader'}:</div>
			<div class="col-xs-9 result-value">
				{$arData.interestLevel|escape}<br/>
				Level {$arData.readingLevel|escape}, {$arData.pointValue|escape} Points
			</div>
		</div>
	{/if}

	{if $recordDriver && $recordDriver->getLexileCode()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Lexile code'}:</div>
			<div class="col-xs-9 result-value">
				{$recordDriver->getLexileCode()|escape}
			</div>
		</div>
	{/if}

	{if $recordDriver && $recordDriver->getLexileScore()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Lexile measure'}:</div>
			<div class="col-xs-9 result-value">
				{$recordDriver->getLexileScore()|escape}
			</div>
		</div>
	{/if}

	{if $recordDriver && $recordDriver->getFountasPinnellLevel()}
		<div class="row">
			<div class="result-label col-xs-3">{translate text='Fountas &amp; Pinnell'}:</div>
			<div class="col-xs-9 result-value">
				{$recordDriver->getFountasPinnellLevel()|escape}
			</div>
		</div>
	{/if}

	{if $notes}
		<h4>{translate text='Notes'}</h4>
		{foreach from=$notes item=note name=loop}
			<div class="row">
				<div class="result-label col-xs-3">{$note.label}</div>
				<div class="col-xs-9 result-value">{$note.note}</div>
			</div>
		{/foreach}
	{/if}
{/strip}