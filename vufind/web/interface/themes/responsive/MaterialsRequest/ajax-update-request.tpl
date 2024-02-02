{if $error}
	<div class="alert alert-danger">{$error}</div>
{else}

<form id="materialsRequestUpdateForm" action="/MaterialsRequest/Update" method="post" class="form form-horizontal">
	{include file="MaterialsRequest/request-form-fields.tpl"}

</form>

<script>
Pika.MaterialsRequest.authorLabels = {$formatAuthorLabelsJSON};
Pika.MaterialsRequest.specialFields = {$specialFieldFormatsJSON};
Pika.MaterialsRequest.setFieldVisibility();
$("#materialsRequestUpdateForm").validate();
</script>

{/if}