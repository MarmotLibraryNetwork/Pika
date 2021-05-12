{*<div id="page-content" *}{*class="col-xs-12 col-sm-8 col-md-9 col-lg-9" defined by container*}{*>*}
<form action="" method="post" id="offlineCircForm">
	<div id="main-content" class="full-result-content">
		<h2>Migrate Circs</h2>

		{if $error}
			<div class="alert alert-danger">
				{$error}
			</div>
		{/if}

		{if $results}
			<div class="alert alert-info" id="offline-circulation-result">
				{$results}
			</div>
		{/if}

		<div class="row">
			<div class="col-tn-12 helpTextUnsized well">
				<p>This will load circs into the Pika Offline Circ tables to be processed as check outs.</p>
			<p>Circs can be loaded from either an INI formatted text
				or from a CSV formatted text.
			</p>
			<dl class="dl-horizontal">
				<dt>CSV :</dt> <dd><code>patronBarcode, itemBarcode</code></dd>
				<dt>INI :</dt> <dd><code>patronBarcode = itemBarcode</code></dd>
			</dl>

			<div class="alert alert-info">
				<ul>
					<li>The barcodes can optionally have quotes surrounding it. <code>"patronBarcode" = "itemBarcode"</code></li>
					<li>Lines starting with # will be ignored as comment lines.<code>#patronBarcode = itemBarcode</code><br>
						(Values that are or start with # must be entered manually.)</li>
				</ul>
			</div>

		</div>
		</div>
		<div class="row">
			<div class="col-xs-3">
				<div><label for="login">{$ILSname} Username</label>:</div>
				<div><input type="text" name="login" id="login" value="{$lastLogin}" class="required" onchange="clearOfflineCircResults();"> </div>
			</div>
			<div class="col-xs-3">
				<div><label for="password1">{$ILSname} Password</label>:</div>
				<div><input type="password" name="password1" id="password1" value="{$lastPassword1}" class="required" onchange="clearOfflineCircResults();"></div>
			</div>
			<div class="col-xs-4">
				<label for="showPwd" class="checkbox">
					<input type="checkbox" id="showPwd" name="showPwd" onclick="return Pika.pwdToText('password1')">
					Show {$ILSname} Password
				</label>
			</div>
		</div>
		<div class="row">

			<fieldset>
				<div class="col-xs-12">
					<div><label for="barcodesToCheckOut">Enter barcodes to check out (one pair per line)</label>:</div>
					<textarea rows="20" cols="80" name="barcodesToCheckOut" id="barcodesToCheckOut" class="required form-control"></textarea>

{*					<textarea rows="10" cols="20" name="barcodesToCheckOut" id="barcodesToCheckOut" class="required" onchange="clearOfflineCircResults();"></textarea>*}
				</div>
				<div class="col-xs-12">
					<button name="submit" class="btn btn-primary pull-right">Submit Offline Checkouts</button>
				</div>
			</fieldset>
		</div>
	</div>

</form>

{literal}
<script type="text/javascript">
	function clearOfflineCircResults(){
		$("#offline-circulation-result").hide();
	}
	$("#offlineCircForm").validate();

</script>
{/literal}