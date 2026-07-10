{strip}
	<h1 role="heading" aria-level="1" class="h2 notranslate">
		{$library->displayName}
	</h1>


	<div class="row">
		<div class="result-label col-md-4">{translate text='Branches'}:</div>
		<div class="col-md-8 result-value">
			<ul>
				{foreach from=$branches item=branch}
					<li><a href="{$branch.link}">{$branch.name}</a></li>
				{/foreach}
			</ul>
		</div>
	</div>
{/strip}