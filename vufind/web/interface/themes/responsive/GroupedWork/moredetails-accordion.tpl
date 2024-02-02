{strip}
	<div id="more-details-accordion" class="panel-group">
		{foreach from=$moreDetailsOptions key="moreDetailsKey" item="moreDetailsOption"}
			<div class="panel {if $moreDetailsOption.openByDefault}active{/if}" id="{$moreDetailsKey}Panel" {if $moreDetailsOption.hideByDefault}style="display:none"{/if}>
				<a data-toggle="collapse" href="#{$moreDetailsKey}PanelBody">
					<div class="panel-heading">
						<div class="panel-title">
							{$moreDetailsOption.label}
						</div>
					</div>
				</a>
				<div id="{$moreDetailsKey}PanelBody" class="panel-collapse collapse {if $moreDetailsOption.openByDefault}in{/if}">
					<div class="panel-body">
						{if $moreDetailsKey == 'description'}
							{* make text-full items easier to read by placing an empty line where linebreaks exist *}
							{$moreDetailsOption.body|replace:"\n":"<br>\n"}
						{else}
							{$moreDetailsOption.body}
						{/if}
					</div>
					{if $moreDetailsOption.onShow}
						<script>
							{literal}
							$('#{/literal}{$moreDetailsKey}Panel'){literal}.on('shown.bs.collapse', function () {
								{/literal}{$moreDetailsOption.onShow}{literal}
							});
							{/literal}
						</script>
					{/if}
				</div>
			</div>
		{/foreach}
	</div> {* End of tabs*}
{/strip}
{literal}
<script>
	$(function(){
		$('#excerptPanel').on('show.bs.collapse', function (e) {
			Pika.GroupedWork.getGoDeeperData({/literal}'{$recordDriver->getPermanentId()}'{literal}, 'excerpt');
		});
		$('#tableOfContentsPanel').on('show.bs.collapse', function (e) {
			Pika.GroupedWork.getGoDeeperData({/literal}'{$recordDriver->getPermanentId()}'{literal}, 'tableOfContents');
		});
		$('#authornotesPanel').on('show.bs.collapse', function (e) {
			Pika.GroupedWork.getGoDeeperData({/literal}'{$recordDriver->getPermanentId()}'{literal}, 'authornotes');
		})
	})
</script>
{/literal}
