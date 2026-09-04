<form id="{$title}Filter" action="{$cluster.list.url|escape}" method="post">
	<div class="facet-form d-flex flex-wrap align-items-center gap-2">
		{if $title == 'lexile_score'}
			<div id="lexile-range"></div>
		{/if}
		<div class="mb-3">
			<label for="{$title}from" class="yearboxlabel visually-hidden form-label">From:</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="from" name="{$title}from" id="{$title}from" value="">
		</div>
		<div class="mb-3">
			<label for="{$title}to" class="yearboxlabel visually-hidden form-label">To:</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="to" name="{$title}to" id="{$title}to" value="">
		</div>
		<input type="submit" value="Go" id="goButton-{$title}" class="goButton btn btn-primary">
	</div>
</form>