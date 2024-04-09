{strip}
	{* Supply $label & $actionPath for this template *}

	{if !empty($linkedUsers) && count($linkedUsers) > 1} {* Linked Users contains the active user as well *}
		<form action="{$actionPath}" method="get" class="form form-inline" id="switchLinkedUsers">
			<div id="linkedUserOptions" class="form-group">
				<label class="control-label" for="patronId">{translate text="$label"}: </label>
				<div class="controls">
					<select name="patronId" id="patronId" class="form-control" {*onclick="$('#switchLinkedUsers').submit()" // javascript jump menus are not keybaord-accessible *}>
						{foreach from=$linkedUsers item=tmpUser}
							<option value="{$tmpUser->id}" {if $selectedUser == $tmpUser->id}selected="selected"{else} {/if}>{$tmpUser->displayName} - {$tmpUser->getHomeLibrarySystemName()}</option>
						{/foreach}
					</select>
					<button type="submit" class="btn btn-primary">Change Account</button>{* Submit button needed for keyboard accessiblility *}
				</div>
			</div>
		</form>
	{/if}

{/strip}