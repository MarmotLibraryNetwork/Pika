	<div id="main-content">
		<h1>{$pageTitleShort}</h1>

		{if $error}
			<div class="alert alert-danger">
				{$error}
			</div>
		{/if}
		<div class="row">
			<div class="col-tn-12">
				<p>
					<a class="btn btn-sm btn-default" href='/Admin/LoanRuleDeterminers?objectAction=list'>Return to List</a>
				</p>
			</div>
		</div>
		<div class="row">
			<div class="col-tn-12">

				<div class="alert alert-info">
				<p>To reload loan rule determiners:
					<ol>
						<li>Open Sierra/Millennium</li>
						<li>Go to the Loan Rules configuration page (In Circulation module go to Admin &gt; Parameters &gt; Circulation &gt; Loan Rule Determiner.)</li>
						<li>Copy the entire table by highlighting it and pressing Ctrl+C.</li>
						<li>Paste the data in the text area below.</li>
						<li>Select the Reload Data button.</li>
					</ol>
					</p>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-tn-12">

		<form name="importLoanRules" action="/Admin/LoanRuleDeterminers" method="post">
			<fieldset>
				<input type="hidden" name="objectAction" value="doLoanRuleDeterminerReload">
				<div class="col-xs-12">
				<textarea rows="20" cols="80" name="loanRuleDeterminerData"></textarea>
				</div>
				<div class="col-xs-12">
				<input type="submit" name="reload" value="Reload Data" class="btn btn-primary pull-right">
				</div>
			</fieldset>
		</form>
			</div>
		</div>

	</div>
