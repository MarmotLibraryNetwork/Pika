{strip}
<div>
	<div id="createBrowseCategoryComments">
		<p class="alert alert-info">
			Please enter a name for the browse category to be created.
		</p>
	</div>
	<form method="post" name="createBrowseCategory" id="createBrowseCategory" action="/Browse/AJAX" class="form">
		<div>
			{if $searchId}
				<input type="hidden" name="searchId" value="{$searchId}" id="searchId">
			{else}
				<input type="hidden" name="listId" value="{$listId}" id="listId">
			{/if}
			<input type="hidden" name="method" value="createBrowseCategory">
			<div class="form-group">
				<label for="categoryName" class="control-label">New Category Name</label>
				<input type="text" id="categoryName" name="categoryName" value="" class="form-control required" aria-required="true">
			</div>
			{if $property} {* If data for Select tag is present, use the object editor template to build the <select> *}
			<div class="form-group">
				<label for="make-as-a-sub-category-ofSelect" class="control-label">Add as a Sub-Category to (optional) : </label>
				{include file="DataObjectUtil/enum.tpl"} {* create select list *}
			</div>
			{/if}
		</div>
	</form>
</div>
{/strip}
<script>
	{literal}
	$('#categoryName').focus();
	$("#createBrowseCategory").validate({
		submitHandler: function(){
			Pika.Browse.createBrowseCategory()
		}
	});
	{/literal}
</script>