{strip}
{if $transcription}
	{foreach from=$transcription item=transcript}
		<div class="transcript">
			{if $transcript.location}
				<div class="transcriptLocation">From the {$transcript.location|escape}</div>
			{/if}
			{if $transcript.language}
				<div class="transcriptLanguage">({$transcript.language|escape})</div>
			{/if}
			<div class="transcriptText">{$transcript.text|nl2br}</div>
		</div>
	{/foreach}
{/if}
{/strip}
