{strip}
	<div class="striped">
		{foreach from=$links item="link"}
			<div class="row">
				<div class="col-tn-12">
					<a href="{$link.url}">{$link.title}</a>
				</div>
			</div>
		{/foreach}
	</div>
{/strip}