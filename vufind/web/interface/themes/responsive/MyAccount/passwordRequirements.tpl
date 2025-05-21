{strip}
	<p><strong>&bull; {if $alphaNumericOnlyPins}Use numbers and letters.{elseif $numericOnlyPins}Use only numbers.{else}Use numbers and/or letters.{/if}</strong></p>
	{if $pinMinimumLength == $pinMaximumLength}
		<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} characters in length.</strong></p>
	{else}
		<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} to {$pinMaximumLength} characters in length.</strong></p>
	{/if}
	{if $sierraTrivialPin}
		<br>
		<p><strong>&bull; Do not repeat a character three or more times in a row (for example: <code>1112</code>, <code>zeee</code>, or <code>x7gp3333</code> will not work).</strong></p>
		<p><strong>&bull; Do not repeat a set of characters two or more times in a row (for example: <code>1212</code>, <code>abab</code>, <code>abcabc</code>, <code>abcdabcd</code>, <code>queue</code>, or <code>banana</code> will not work).</strong></p>
	{/if}
{/strip}