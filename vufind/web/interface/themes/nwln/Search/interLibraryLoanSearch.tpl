{strip}
	<h2>In {$interLibraryLoanName}</h2>
	<div class="row" id="prospectorSection">
		<div class="col-tn-12 col-sm-3">
			<img class="center-block" src="{img filename='innReachEncoreLogo.png'}" style="max-width: 100%" alt="{$interLibraryLoanName} Logo">
		</div>
		<div class="col-tn-12 col-sm-9">
			Didn’t find what you need? Items not owned by {if $consortiumName}{$consortiumName}{elseif $homeLibrary}{$homeLibrary}{else}the library{/if} can be requested from other {$interLibraryLoanName} libraries to be delivered to your local library for pickup.
			You can see what's available across the {$interLibraryLoanName} system here. Contact your local library to request items through {$interLibraryLoanName}.
		</div>
	</div>

	<div class="row" id="prospectorLinkSection">
		<div class="col-tn-12">
			<br>
			<button class="btn btn-sm btn-info pull-right" onclick="window.open('{$interLibraryLoanUrl}', 'child'); return false">See more results in {$interLibraryLoanName}</button>
		</div>
	</div>

	<style>
		{literal}
		#prospectorSection {
			padding-top: 15px;
		}
		#prospectorLinkSection {
			padding-bottom: 15px;
		}
		{/literal}
	</style>

{/strip}