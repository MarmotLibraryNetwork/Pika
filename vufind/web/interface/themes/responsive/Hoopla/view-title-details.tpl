{strip}
	{* Display more information about the title*}
	{if $recordDriver->getAuthor()}
		<div class="row">
			<div class="result-label col-sm-4">Author: </div>
			<div class="col-sm-8 result-value">
				<a href='{$path}/Author/Home?author="{$recordDriver->getAuthor()|escape:"url"}"'>{$recordDriver->getAuthor()|highlight}</a>
			</div>
		</div>
	{/if}

	{assign var=summSeries value=$recordDriver->getSeries()}
	{if $summSeries}
		<div class="series row">
			<div class="result-label col-sm-4">Series: </div>
			<div class="col-sm-8 result-value">
				{if $summSeries->fromNovelist}
					<a href="{$path}/GroupedWork/{$recordDriver->getPermanentId()}/Series">{$summSeries.seriesTitle|removeTrailingPunctuation|escape}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
				{else}
					<a href="{$path}/Search/Results?lookfor={$summSeries.seriesTitle}">{$summSeries.seriesTitle|removeTrailingPunctuation|escape}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
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
		<div class="col-sm-8 result-value result-value-bold statusValue here" id="statusValue">
			Available Online
		</div>
	</div>

{/strip}
