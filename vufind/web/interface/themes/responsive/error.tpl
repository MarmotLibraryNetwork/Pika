<div>
	<h1>Oops, an error occurred</h1>
	<div class="h4">{$error->getMessage()}</div>
	<div class="h4">{translate text="Please contact the Library Reference Department for assistance"}<br></div>
	{if $supportEmail}
	<div class="h4"><a href="mailto:{$supportEmail}">{$supportEmail}</a></div>
	{/if}
</div>

{* Return to Advanced Search Link *}
{if $searchType == 'advanced'}
	<h5>
		<a href="/Search/Advanced">Edit This Advanced Search</a>
	</h5>
{/if}

{* Search Debugging *}
{include file="Search/search-debug.tpl"}

    {if $parseError}
			<div class="alert alert-danger">
          {$parseError}
			</div>
    {/if}

<div id="debug">
	{if $debug}
		<div class="h4">{translate text="Debug Information"}</div>
		<p class="errorStmt">{$error->getDebugInfo()}</p>
		{assign var=errorCode value=$error->getCode()}
		{if $errorCode}
			<p class="errorMsg">{translate text="Code"}: {$errorCode}</p>
		{/if}
		<p>{translate text="Backtrace"}:</p>
		{foreach from=$error->backtrace item=trace}
			[{$trace.line}] {$trace.file}<br>
		{/foreach}
	{/if}
</div>
