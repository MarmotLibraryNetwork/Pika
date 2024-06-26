{strip}
<form class="form-horizontal" id="catalogUrlForm">
	<div class="alert alert-info">
		Any URL entered here <strong>must</strong> have an entry in a DNS server pointing the URL to this web server in order for the URL to be accessable via a web browser.
	</div>
	<label for="catalogUrl">New Catalog URL:{if !$isLocation} <span class="required-input">*</span>{/if}</label>
	<input type="text" class="form-control{if !$isLocation} required{/if}" id="catalogUrl" value="{$catalogUrl}"{if !$isLocation} aria-required="true"{/if}>
</form>
{/strip}
<script>
	{literal}
	$("#catalogUrlForm").validate({
		submitHandler: function(){
			Pika.Admin.setCatalogUrl("{/literal}{$id}", {$isLocation}{literal});
		}
	});
	{/literal}
</script>