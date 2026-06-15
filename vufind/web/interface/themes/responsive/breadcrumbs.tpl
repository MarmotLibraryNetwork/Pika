{strip}
	{if $showBreadcrumbs}
		<nav aria-label="Breadcrumb" class="row breadcrumbs">
			<div class="col-xs-12 col-sm-9">
				<ol class="breadcrumb small">
					{if !$archiveOnlyInterface}
						<li><a href="{$homeBreadcrumbLink}" id="home-breadcrumb">{translate text=$homeLinkText}</a> <span class="divider">&raquo;</span></li>
					{else}
						<li><a href="/">Home</a> <span class="divider">&raquo;</span></li>
					{/if}
					{include file="$module/breadcrumbs.tpl"}
				</ol>
			</div>
		</nav>
	{/if}
{/strip}