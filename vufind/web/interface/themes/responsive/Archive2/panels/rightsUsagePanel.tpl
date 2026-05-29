{strip}
	<div class="panel" id="rightsUsagePanel"><a data-toggle="collapse" href="#rightsUsagePanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Rights & Usage</h2>
			</div>
		</a>
		<div id="rightsUsagePanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Rights" value=$rights}
				{if $rights_creator || $debugDetails}
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">Rights Creator: </div>
						<div class="result-value col-sm-8">
							{if $rights_creator}
								{foreach from=$rights_creator item=creator name=creatorLoop}
									{if $creator.vocabulary eq 'corporate_body' && $creator.tid}
										<a href="/Archive2/Organization/{$creator.tid}">{$creator.name|escape}</a>
									{else}
										{$creator.name|escape}
									{/if}
									{if !$smarty.foreach.creatorLoop.last}, <br>{/if}
								{/foreach}
							{else}
								<span class="text-muted">Not provided</span>
							{/if}
						</div>
					</div>
				{/if}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Effective Date" value=$rights_effective_date}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Expiration" value=$rights_expiration}
				{if $rights_holder || $debugDetails}
					<div class="row archive-field-row">
						<div class="result-label col-sm-4">Rights Holder: </div>
						<div class="result-value col-sm-8">
							{if $rights_holder}
								{foreach from=$rights_holder item=holder name=holderLoop}
									{if $holder.vocabulary eq 'corporate_body' && $holder.tid}
										<a href="/Archive2/Organization/{$holder.tid}">{$holder.name|escape}</a>
									{elseif $holder.vocabulary eq 'person' && $holder.tid}
										<a href="/Archive2/Person/{$holder.tid}">{$holder.name|escape}</a>
									{else}
										{$holder.name|escape}
									{/if}
									{if !$smarty.foreach.holderLoop.last}, <br>{/if}
								{/foreach}
							{else}
								<span class="text-muted">Not provided</span>
							{/if}
						</div>
					</div>
				{/if}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights (Long)" value=$rights_long}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Note" value=$rights_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Organization Statement" value=$rights_org_statement}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement URI" value=$rights_org_statement.uri}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement Title" value=$rights_org_statement.title}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement Options" value=$rights_org_statement.options}
				{include file="Archive2/partials/fieldRow.tpl" label="Supporting Departments" value=$supporting_depts}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage" value=$pika_usage}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Access Limits" value=$pika_access_limits}
			</div>
		</div>
	</div>
{/strip}
