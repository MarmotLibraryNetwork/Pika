<form class="form-horizontal" id="save-to-list-form">
	<div>
{* TODO: these variables are likely obsolete
		<input type="hidden" name="submit" value="1">
		<input type="hidden" name="record_id" value="{$id|escape}">
		<input type="hidden" name="source" value="{$source|escape}">
*}

		{if !empty($largeLists)}
			<p>
				{translate text='The following lists currently have the maximum number of items in them'}:<br>
				{foreach from=$largeLists item="list"}
				  <a href="/MyAccount/MyList/{$list.id}">{$list.title|escape:"html"}</a><br>
				{/foreach}
			</p>
		{/if}

		{* Only display the list drop-down if the user has lists that do not contain
		 this item OR if they have no lists at all and need to create a default list *}
		{if (!empty($nonContainingLists) || (empty($containingLists) && empty($nonContainingLists))) }
		  {assign var="showLists" value="true"}
		{/if}

	  {if $showLists}
		  <div class="form-group">
			  <label for="addToList-list" class="col-sm-3">{translate text='Choose a List'}</label>
			  <div class="col-sm-9">
				  <select name="list" id="addToList-list">
					  {foreach from=$nonContainingLists item="list"}
						  <option value="{$list.id}">{$list.title|escape:"html"}</option>
						  {foreachelse}
						  <option value="">{translate text='My Favorites'}</option>
					  {/foreach}
				  </select>
				  &nbsp;or&nbsp;
				  <button class="btn btn-sm btn-default" onclick="return Pika.GroupedWork.showCreateSeriesListForm('{$this}', '{$id|escape:"url"}')">{translate text="Create a New List"}</button>
			  </div>
			</div>
		{else}
		  <button class="btn btn-sm btn-default" onclick="return Pika.GroupedWork.showCreateSeriesListForm('{$this}','{$id|escape:"url"}')">{translate text="Create a New List"}</button>
	  {/if}


			<div>
					The following titles will be added to your list:
					<ul>
				{foreach from=$seriesTitles item=title}
						<li>{$title.title}</li>
					{/foreach}
					</ul>
			</div>


	</div>
</form>
