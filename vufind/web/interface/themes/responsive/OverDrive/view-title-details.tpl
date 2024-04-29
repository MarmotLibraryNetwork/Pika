{strip}

			<div class="row">
				{foreach from=$recordDriver->getDetailedContributors() item=contributor name=loop}
          {if $smarty.foreach.loop.index == 5}
				<div id="showAdditionalContributorsLink">
					<a onclick="Pika.Record.moreContributors(); return false;" href="#">{translate text='more'} ...</a>
				</div>
          {*create hidden div*}
				<div id="additionalContributors" style="display:none">
					{/if}
					{if $contributor.role != "Other"}
					<div class="result-label col-md-3">{translate text=$contributor.role}:</div>
						<div class="result-value col-md-9">
							{if $contributor.role == "Author"}
							<a href='/Author/Home?author="{$contributor.name|trim|escape:"url"}"'>{$contributor.name|escape}</a>
							{else}
								<a href='/Search/Results?basicType=Author&lookfor={$contributor.name|trim|escape:"url"}'>{$contributor.name|escape}</a>
                  {*Do not link to an author page for contributors as that page is meant for titles authored by the
									person, instead do an author search to see other titles they are contributors of as well as authors of*}
							{/if}
						</div>
					{/if}
				{/foreach}
            {if $smarty.foreach.loop.index >= 5}
					<div>
						<a href="#" onclick="Pika.Record.lessContributors(); return false;">{translate text='less'} ...</a>
					</div>
				</div>{* closes hidden div *}
          {/if}
			</div>


	{if $recordDriver->getSeries()}
		<div class="series row">
			<div class="result-label col-md-3">Series: </div>
			<div class="col-md-9 result-value">
				{assign var=summSeries value=$recordDriver->getSeries()}
				{if $summSeries->fromNovelist}
					<a href="/GroupedWork/{$recordDriver->getPermanentId()}/Series">{$summSeries.seriesTitle}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
				{else}
					<a href="/Search/Results?lookfor={$summSeries.seriesTitle}">{$summSeries.seriesTitle}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
				{/if}
			</div>
		</div>
	{/if}

	{if $showPublicationDetails && $recordDriver->getPublicationDetails()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Published'}:</div>
			<div class="col-md-9 result-value">
				{implode subject=$recordDriver->getPublicationDetails() glue=", "}
			</div>
		</div>
	{/if}

	{if $showFormats}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Format'}:</div>
			<div class="col-md-9 result-value">
				{implode subject=$recordDriver->getFormats() glue=", "}
			</div>
		</div>
	{/if}

	{if $showEditions && $recordDriver->getEdition()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Edition'}:</div>
			<div class="col-md-9 result-value">
				{implode subject=$recordDriver->getEdition() glue=", "}
			</div>
		</div>
	{/if}


	{if $showISBNs && count($recordDriver->getISBNs()) > 0}
		<div class="row">
			<div class="result-label col-md-3">{translate text='ISBN'}:</div>
			<div class="col-md-9 result-value">
				{implode subject=$recordDriver->getISBNs() glue=", "}
			</div>
		</div>
	{/if}

	{if $showArInfo && $recordDriver->getAcceleratedReaderDisplayString()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Accelerated Reader'}: </div>
			<div class="result-value col-md-9">
				{$recordDriver->getAcceleratedReaderDisplayString()}
			</div>
		</div>
	{/if}

	{if $showLexileInfo && $recordDriver->getLexileDisplayString()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Lexile measure'}: </div>
			<div class="result-value col-md-9">
				{$recordDriver->getLexileDisplayString()}
			</div>
		</div>
	{/if}

	{if $showFountasPinnell && $recordDriver->getFountasPinnellLevel()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Fountas &amp; Pinnell'}:</div>
			<div class="col-md-9 result-value">
				{$recordDriver->getFountasPinnellLevel()|escape}
			</div>
		</div>
	{/if}


	<div class="row">
		<div class="result-label col-md-3">{translate text='Status'}:</div>
		<div class="col-md-9 result-value bold statusValue {$holdingsSummary.class}" id="statusValue">{$holdingsSummary.status|escape}</div>
	</div>
{/strip}
