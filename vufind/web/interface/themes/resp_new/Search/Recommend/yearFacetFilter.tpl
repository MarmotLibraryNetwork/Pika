<form id="{$title}Filter" action="{$fullPath}" class="form-inline" method="post">
	<div class="facet-form">
		<div class="form-group">
			<label for="{$title}yearfrom" class="yearboxlabel sr-only control-label">From</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="from" name="{$title}yearfrom" id="{$title}yearfrom" value="">
		</div>
		<div class="form-group">
			<label for="{$title}yearto" class="yearboxlabel sr-only control-label">To</label>
			<input type="text" size="4" maxlength="4" class="yearbox form-control" placeholder="to" name="{$title}yearto" id="{$title}yearto" value="">
		</div>

		<input type="submit" value="Go" class="goButton btn btn-sm btn-primary">

		{if $title == 'publishDate'}
			<div id="yearDefaultLinks">
				{assign var=thisyear value=$smarty.now|date_format:"%Y"}
				Published in the last:<br>
				<a onclick="$('#{$title}yearfrom').val('{$thisyear-1}');$('#{$title}yearto').val('');" href='javascript:void(0);'>year</a>
				&bullet; <a onclick="$('#{$title}yearfrom').val('{$thisyear-5}');$('#{$title}yearto').val('');" href='javascript:void(0);'>5&nbsp;years</a>
				&bullet; <a onclick="$('#{$title}yearfrom').val('{$thisyear-10}');$('#{$title}yearto').val('');" href='javascript:void(0);'>10&nbsp;years</a>
			</div>
		{/if}
	</div>
</form>
