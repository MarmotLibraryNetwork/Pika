{strip}
	{if $recordDriver->getSubjects()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Subjects'}</div>
			<div class="col-md-9 result-value">
				{assign var="subjects" value=$recordDriver->getSubjects()}
				{foreach from=$subjects item=subject name=loop}
					<a href="/Search/Results?lookfor=%22{$subject->value|escape:"url"}%22&amp;basicType=Subject">{$subject->value|escape}</a>
					<br>
				{/foreach}
			</div>
		</div>
	{/if}

{/strip}