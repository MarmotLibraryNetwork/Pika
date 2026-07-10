{strip}
	<nav id="masquerade-header" class="navbar-fixed-top">
		<div id="masquerade-header-content" class="container-fluid">
			<div class="row">
				<div id="masquerade-header-title" class="col-7 col-sm-8 col-md-4 col-xl-3">
					<h4>
						<span class="bi bi-sunglasses"></span>
						&nbsp;
						Masquerade Mode
					</h4>
				</div>
				<div id="masquerade-header-name-section" class="d-none d-md-block col-md-5 col-xl-6">
					<h5>Masquerading As {$userDisplayName|capitalize}</h5>
				</div>

				<div id="masquerade-header-end" class="col-5 col-sm-4 col-md-3 col-xl-2 float-end">
					<button class="btn btn-masquerade btn-block float-end" onclick="Pika.Account.endMasquerade()">End Masquerade</button>
				</div>
			</div>

		</div>
	</nav>
{/strip}