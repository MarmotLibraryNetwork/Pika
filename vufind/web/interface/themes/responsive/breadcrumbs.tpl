{strip}
	{* Added Breadcrumbs to appear above the format filter icons - JE 6/26/15 *}
	{if $showBreadcrumbs}
	<nav aria-label="Breadcrumb" class="row breadcrumbs">
		<div class="col-xs-12 col-sm-9">
			<ol class="breadcrumb small">
				<li><a href="{$homeBreadcrumbLink}" id="home-breadcrumb">{translate text=$homeLinkText}</a> <span class="divider">&raquo;</span></li>
				{include file="$module/breadcrumbs.tpl"}
			</ol>
		</div>
	</nav>
	{/if}
{/strip}