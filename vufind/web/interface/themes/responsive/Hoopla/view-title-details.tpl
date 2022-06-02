{strip}
	{* Display more information about the title*}
	{if $recordDriver->getPrimaryAuthor()}
		<div class="row">
			<div class="result-label col-sm-4">Author: </div>
			<div class="col-sm-8 result-value">
				<a href='/Author/Home?author="{$recordDriver->getPrimaryAuthor()|escape:"url"}"'>{$recordDriver->getPrimaryAuthor()|highlight}</a>
			</div>
		</div>
	{/if}

	{assign var=summSeries value=$recordDriver->getSeries()}
	{if $summSeries}
		<div class="series row">
			<div class="result-label col-sm-4">Series: </div>
			<div class="col-sm-8 result-value">
				{if $summSeries->fromNovelist}
					<a href="/GroupedWork/{$recordDriver->getPermanentId()}/Series">{$summSeries.seriesTitle|removeTrailingPunctuation|escape}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
				{else}
					<a href="/Search/Results?lookfor={$summSeries.seriesTitle}">{$summSeries.seriesTitle|removeTrailingPunctuation|escape}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
				{/if}
			</div>
		</div>
	{/if}

		{if $recordDriver->getDetailedContributors()}
			<div class="row">
				<div class="result-label col-sm-4">{translate text='Contributors'}:</div>
				<div class="col-sm-8 result-value">
						{foreach from=$recordDriver->getDetailedContributors() item=contributor name=loop}
						{if $smarty.foreach.loop.index == 5}
					<div id="showAdditionalContributorsLink">
						<a onclick="Pika.Record.moreContributors(); return false;" href="#">{translate text='more'} ...</a>
					</div>
						{*create hidden div*}
					<div id="additionalContributors" style="display:none">
							{/if}
						<a href='/Search/Results?basicType=Author&lookfor={$contributor.name|trim|escape:"url"}'>{$contributor.name|escape}</a>
							{*Do not link to an author page for contributors as that page is meant for titles authored by the
							person, instead do an author search to see other titles they are contributors of as well as authors of*}
							{if $contributor.role}
								&nbsp;{$contributor.role}
							{/if}
							{if $contributor.title}
								&nbsp;<a href="/Search/Results?lookfor={$contributor.title}&amp;basicType=Title">{$contributor.title}</a>
							{/if}
						<br>
							{/foreach}
							{if $smarty.foreach.loop.index >= 5}
						<div>
							<a href="#" onclick="Pika.Record.lessContributors(); return false;">{translate text='less'} ...</a>
						</div>
					</div>{* closes hidden div *}
						{/if}
				</div>
			</div>
		{/if}

		{if $showPublicationDetails && $recordDriver->getPublicationDetails()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Published'}:</div>
			<div class="col-sm-8 result-value">
				{implode subject=$recordDriver->getPublicationDetails() glue=", "}
			</div>
		</div>
	{/if}

	{if $showFormats}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Format'}:</div>
			<div class="col-sm-8 result-value">
				{implode subject=$recordDriver->getFormats() glue=", "}
			</div>
		</div>
	{/if}

	{if $showEditions && $recordDriver->getEdition()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Edition'}:</div>
			<div class="col-sm-8 result-value">
				{implode subject=$recordDriver->getEdition() glue=", "}
			</div>
		</div>
	{/if}

	{if $showISBNs && count($recordDriver->getISBNs()) > 0}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='ISBN'}:</div>
			<div class="col-sm-8 result-value">
				{implode subject=$recordDriver->getISBNs() glue=", "}
			</div>
		</div>
	{/if}

	{if $showPhysicalDescriptions && $recordDriver->getPhysicalDescriptions()}
		<div class="row">
			{* Use a different label for Econtent Views *}
			<div class="result-label col-sm-4">{translate text='Content Description'}:</div>
			<div class="col-sm-8 result-value">
				{implode subject=$recordDriver->getPhysicalDescriptions() glue=", "}
			</div>
		</div>
	{/if}

	{if $showArInfo && $recordDriver->getAcceleratedReaderDisplayString()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Accelerated Reader'}: </div>
			<div class="result-value col-sm-8">
				{$recordDriver->getAcceleratedReaderDisplayString()}
			</div>
		</div>
	{/if}

	{if $showLexileInfo && $recordDriver->getLexileDisplayString()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Lexile measure'}: </div>
			<div class="result-value col-sm-8">
				{$recordDriver->getLexileDisplayString()}
			</div>
		</div>
	{/if}

	{if $showFountasPinnell && $recordDriver->getFountasPinnellLevel()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Fountas &amp; Pinnell'}:</div>
			<div class="col-sm-8 result-value">
				{$recordDriver->getFountasPinnellLevel()|escape}
			</div>
		</div>
	{/if}

	{if $recordDriver->getMPAARating()}
		<div class="row">
			<div class="result-label col-sm-4">{translate text='Rating'}:</div>
			<div class="col-sm-8 result-value">{$recordDriver->getMPAARating()|escape}</div>
		</div>
	{/if}

	{* Detailed status information *}
	<div class="row">
		<div class="result-label col-sm-4">{translate text='Status'}:</div>
		<div class="col-sm-8 result-value">
			{include file='GroupedWork/statusIndicator.tpl' statusInformation=$statusSummary viewingIndividualRecord=1}
			{*		<div class="col-sm-8 result-value result-value-bold statusValue here" id="statusValue">*}
{*			Available Online*}
		</div>
	</div>

{/strip}
