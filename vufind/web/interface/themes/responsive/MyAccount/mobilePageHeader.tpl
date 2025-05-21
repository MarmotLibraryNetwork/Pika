{strip}
	{* taken from MyAccount/menu.tpl*}
	{* id attributes have prefix 'mobileHeader-' added *}
	<div class="row visible-xs">
		<div id="mobileHeader" class="col-tn-12 col-xs-12">

			<div id="mobileHeader-myAccountFines">
				<span class="expirationFinesNotice-placeholder"></span>
			</div>

			{* taken from MyAccount/menu.tpl*}
			<div class="myAccountLink{if $action=="CheckedOut"} active{/if}">
				<a href="/MyAccount/CheckedOut" id="mobileHeader-checkedOut">
					Checked Out Titles {if !$offline}<span class="checkouts-placeholder"><img src="/images/loading.gif" alt="loading"></span>{/if}
				</a>
			</div>
			<div class="myAccountLink{if $action=="Holds"} active{/if}">
				<a href="/MyAccount/Holds" id="mobileHeader-holds">
					Titles On Hold {if !$offline}<span class="holds-placeholder"><img src="/images/loading.gif" alt="loading"></span>{/if}
				</a>
			</div>

			{if $enableMaterialsBooking}
				<div class="myAccountLink{if $action=="Bookings"} active{/if}">
					<a href="/MyAccount/Bookings" id="mobileHeader-bookings">
						Scheduled Items  {if !$offline}<span class="bookings-placeholder"><img src="/images/loading.gif" alt="loading"></span>{/if}
					</a>
				</div>
			{/if}
			<div class="myAccountLink{if $action=="ReadingHistory"} active{/if}">
				<a href="/MyAccount/ReadingHistory">
					Reading History {if !$offline}<span class="readingHistory-placeholder"><img src="/images/loading.gif" alt="loading"></span>{/if}
				</a>
			</div>

				{** barcode image **}
				{if $showPatronBarcodeImage != 'none'}
					{*
					Codabar only displays numbers, –, $, :, /, +, ., and A, B, C, D as start/stop characters
					https://github.com/lindell/JsBarcode/wiki/

					CODE 39 only displays numbers, uppercase letters and some special characters (-, ., $, /, +, %, and space).
					https://github.com/lindell/JsBarcode/wiki/CODE39
					*}
					<h2 id="barcodeTitle" class="h4">Scannable Library Card Barcode</h2>
					<div style="text-align: center; min-height: 200px;">
						<svg role="img" id="barcode" style="margin: 0 auto;max-width: 100%" aria-labelledby="barcodeTitle"></svg>
						{literal}
						<script src="https://cdn.jsdelivr.net/jsbarcode/3.6.0/"></script>
						<script>
							try {
							JsBarcode("#barcode", "{/literal}{$user->barcode}{literal}", {
								format: {/literal}{if (stripos($showPatronBarcodeImage, 'code39') !== false)}"CODE39"{else}"codabar"{/if}{literal},
								{/literal}{if $showPatronBarcodeImage == 'code39mod43'}mod43: true, // check digit option for CODE39{/if}{literal}
								{/literal}{*{if $showPatronBarcodeImage == 'code39mod10'}mod10: true, // check digit option for CODE39{/if}*}{literal}
								lineColor: "#000000",
								width: 2,
								height: 200,
								displayValue: {/literal}{if !empty($displayBarcodeValue)}true{else}false{/if}{literal},
							});
							} catch (e){
								console.log(e, 'Hiding barcode and parent divs.');
								$("#barcodeTitle,#barcodeTitle+div").hide();
							}
						</script>
						{/literal}
					</div>
        {/if}
			<hr>
		</div>
	</div>

{/strip}
