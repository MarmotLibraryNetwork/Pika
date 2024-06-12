<form id="{$title}Filter" action="{$cluster.list.url|escape}" class="form-inline" method="post">
	<div class="facet-form">
		{if $title == 'lexile_score'}
			<div id="lexile-range"></div>
		{/if}
		<div class="form-group">
			<label for="{$title}from" class="yearboxlabel sr-only control-label">From:</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="from" name="{$title}from" id="{$title}from" value="">
		</div>
		<div class="form-group">
			<label for="{$title}to" class="yearboxlabel sr-only control-label">To:</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="to" name="{$title}to" id="{$title}to" value="">
		</div>
		<input type="submit" value="Go" id="goButton-{$title}" class="goButton btn btn-primary">
	</div>
</form>