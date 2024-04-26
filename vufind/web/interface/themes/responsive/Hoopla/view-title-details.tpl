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

	{if $showSeries}
		{assign var=series value=$recordDriver->getNoveListSeries()}
		{if $series}
			<div class="series row">
				<div class="result-label col-sm-4">{translate text='NoveList Series'}:</div>
				<div class="col-sm-8 result-value">
					<a href="/GroupedWork/{$recordDriver->getPermanentId()}/Series">{$series.seriesTitle}</a>{if $series.volume} volume {$series.volume}{/if}<br>
				</div>
			</div>
		{/if}

		{assign var=series value=$recordDriver->getSeries()}
		{if $series}
			<div class="series row">
				<div class="result-label col-sm-4">{translate text='Series'}:</div>
				<div class="col-sm-8 result-value">
						{if is_array($series) && !isset($series.seriesTitle)}
								{foreach from=$series item=seriesItem name=loop}
									<a href="/Search/Results?basicType=Series&lookfor=%22{$seriesItem.seriesTitle|removeTrailingPunctuation|escape:"url"}%22">{$seriesItem.seriesTitle|removeTrailingPunctuation|escape}</a>{if $seriesItem.volume} {if is_numeric($seriesItem.volume|removeTrailingPunctuation|trim)}volume {/if}{$seriesItem.volume}{/if}<br>
								{/foreach}
						{else}
							<a href="/Search/Results?basicType=Series&lookfor=%22{$series.seriesTitle|removeTrailingPunctuation|escape:"url"}%22">{$series.seriesTitle|removeTrailingPunctuation|escape}</a>{if $series.volume} {if is_numeric($series.volume|removeTrailingPunctuation|trim)}volume {/if}{$series.volume}{/if}<br>
						{/if}
				</div>
			</div>
		{/if}
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
				{implode subject=$recordDriver->getPhysicalDescriptions()|escape glue=", "}
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
			{*		<div class="col-sm-8 result-value bold statusValue here" id="statusValue">*}
{*			Available Online*}
		</div>
	</div>

{/strip}
