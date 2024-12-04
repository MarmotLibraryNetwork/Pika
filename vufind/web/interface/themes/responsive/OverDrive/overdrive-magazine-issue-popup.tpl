{strip}
	<div class="row">
		<div class="col-sm-3"><img class="img-thumbnail" src="{$coverUrl}" alt="Cover for edition {$edition|escape}"></div>
		<div class="col-sm-9">
			<div class="row">
				<div class="col-tn-12">
				<p><strong>{$title}</strong> - {$edition}</p>
				</div>
			</div>
			<div class="row">
				<div class="col-tn-12" {*style="max-height:300px;overflow:hidden;"*}>{$description}</div>
			</div>
		</div>
	</div>
	</div>
{/strip}