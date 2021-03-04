{strip}
<div id="page-content" class="content">
	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{/if}
	<div class="alert alert-info">{translate text='Select the Library Catalog you wish to use'}</div>
	<div id="selectLibraryMenu">
		<form id="selectLibrary" method="get" action="/MyAccount/SelectInterface" class="form">
			<input type="hidden" name="gotoModule" value="{$gotoModule}">
			<input type="hidden" name="gotoAction" value="{$gotoAction}">
			<div>
				<div class="row home-page-browse-grid">
					{* home-page-browse-grid applys columns to our divs below *}
				{foreach from=$libraries item=displayName key=id}
					<div class="selectLibraryOption{* col-tn-12 col-sm-6 col-md-4*}">
						<label for="library{$id}"><input type="radio" id="library{$id}" name="library" value="{$id}"> {$displayName}</label>
					</div>
				{/foreach}
				</div>
				<div class="row">
				<div class="col-tn-12">
					<div class="selectLibraryOption checkbox">
						<label for="rememberThis"><input type="checkbox" name="rememberThis" {*checked="checked"*} id="rememberThis"> <strong>Remember This</strong></label>
					</div>
					<input type="submit" name="submit" value="Select Library Catalog" id="submitButton" class="btn btn-primary">
				</div>
				</div>
			</div>
		</form>
	</div>
	<br>
</div>
{/strip}