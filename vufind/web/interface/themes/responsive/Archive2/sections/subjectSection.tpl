{strip}
	{foreach from=$subjects item=subject}
		<div class="row archive-field-row">
			<div class="result-value col-sm-12">
				<a href="/Archive2/Results?filter[]=sm_subject:&#34;{$subject.name|escape:url}&#34;">{$subject.name|escape}</a>
				{*TODO <a href="/Archive2/Results?filter[]=sm_field_subject:&#34;{$subject.name|escape:url}&#34;">{$subject.name|escape}</a>*}
			</div>
		</div>
	{/foreach}
{/strip}
