{strip}
		{* Note:  Each subject link is meant to be a search for that specifc subject phrase, which
		 is why the phrases are quoted.  The quoting is meant to trigger the Subject Proper
		 search specification to be used, which is meant to match against search phrases
		 without stemming, synonym or stop-word processing in the solr field.
		 *}

	{$recordDriver->getSubjects()}
	{* Loads & assigned the template variables *}

	{if (($showLCSubjects || $showBisacSubjects) && !($showFastAddSubjects || $showOtherSubjects))}
		{*If only lc subjects or bisac subjects are chosen for display (but not the others), display those specific subjects *}

		{if $lcSubjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='LC Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$lcSubjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

		{if $bisacSubjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='Bisac Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$bisacSubjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

		{if $oclcFastSubjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='OCLC Fast Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$oclcFastSubjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

		{if $localSubjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='Local Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$localSubjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

		{if $otherSubjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='Other Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$otherSubjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

	{else}
		{* Display All the subjects *}
		{if $subjects}
			<div class="row">
				<div class="result-label col-xs-3">{translate text='Subjects'}</div>
				<div class="col-xs-9 result-value">
					{foreach from=$subjects item=subject name=loop}
						<a href="/Search/Results?lookfor=%22{$subject|escape:"url"}%22&amp;basicType=Subject">{$subject|escape}</a>
						<br>
					{/foreach}
				</div>
			</div>
		{/if}

	{/if}

{/strip}