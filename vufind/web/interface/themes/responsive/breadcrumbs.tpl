{strip}
	{if $showBreadcrumbs}
		<nav aria-label="Breadcrumb" class="row breadcrumbs">
			<div class="col-tn-12">
				<ol class="breadcrumb small">
					{if !$archiveOnlyInterface}
						<li class="breadcrumb-item"><a href="{$homeBreadcrumbLink}" id="home-breadcrumb">{translate text=$homeLinkText}</a></li>
					{else}
						<li class="breadcrumb-item"><a href="/">Home</a></li>
					{/if}
					{include file="$module/breadcrumbs.tpl"}
				</ol>
			</div>
		</nav>
	{/if}
{/strip}