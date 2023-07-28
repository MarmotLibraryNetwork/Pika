<div>
	<h1>Oops, an error occurred</h1>
{*	<h2>This error has been logged and we are working on a fix.</h2>*}
	<h4>{$error->getMessage()}</h4>
	<h4>{translate text="Please contact the Library Reference Department for assistance"}<br></h4>
	{if $supportEmail}
	<h4><a href="mailto:{$supportEmail}">{$supportEmail}</a></h4>
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
		<h4>{translate text="Debug Information"}</h4>
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
