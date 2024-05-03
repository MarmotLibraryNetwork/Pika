{strip}
	<div id="page-content" class="col-tn-12">
		{if $error}
			<div class="alert alert-danger">{$error}</div>
		{/if}

		<h1 role="heading" aria-level="1" class="h2 text-center">Choose a Library Catalog</h1>

		<div class="text-center alert alert-info">{translate text='Select the Library Catalog you wish to use'}</div>

		<div id="selectLibraryMenu">
			<form id="selectLibrary" method="get" action="/MyAccount/SelectInterface" class="form">
				<input type="hidden" name="gotoModule" value="{$gotoModule}">
				<input type="hidden" name="gotoAction" value="{$gotoAction}">
				<div class="row">
					<div class="col-tn-12">
						<div class="browse-thumbnails-medium">
							{* browse-thumbnails-medium applies columns to our divs below *}
						{foreach from=$libraries item=displayName key=id}
							<div class="selectLibraryOption">
								<label for="library{$id}"><input type="radio" id="library{$id}" name="library" value="{$id}"> {$displayName}</label>
							</div>
						{/foreach}
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-tn-12">
						<div class="rememberMe checkbox">
							<label for="rememberThis"><input type="checkbox" name="rememberThis" {*checked="checked"*} id="rememberThis"> <strong>Remember This</strong></label>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="selectLibraryOption col-tn-12">
						<input type="submit" name="submit" value="Select Library Catalog" id="submitButton" class="btn btn-primary">
					</div>
				</div>
			</form>
		</div>
		<br>
	</div>
{/strip}