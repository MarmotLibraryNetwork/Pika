{strip}
<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-label="Close Window">&times;</button>
	<h2 class="modal-title h4" id="myModalLabel">{translate text='Log into your account'}</h2>{* Sematically subheading of main page's h1 (for accessibility *}
</div>
<div class="modal-body">
	<p class="alert alert-danger" id="loginError" style="display: none"></p>
	<p class="alert alert-danger" id="cookiesError" style="display: none">It appears that you do not have cookies enabled on this computer.  Cookies are required to access account information.</p>
	<p class="alert alert-info" id="loading" style="display: none">
		Logging you in now. Please wait.
	</p>
	{if $offline && !$enableLoginWhileOffline}
		<div class="alert alert-warning">
			<p>
				The Library’s accounts system is down. Tech support is working to assess and fix the problem as quickly as possible.
			</p>
			<p>
				Thank you for your patience and understanding.
			</p>
		</div>
	{else}
		<form method="post" action="/MyAccount/Home" id="loginForm" class="form-horizontal" role="form" onsubmit="return Pika.Account.processAjaxLogin()">
			<div class="row">

				<div class="col-sm-6">
					<p><strong>Students, Faculty, and staff</strong>, log in with your Fort Lewis College Network Account.</p>
					<a href="/MyAccount/Home?casLogin" class="btn btn-primary">Student/Faculty/Staff Login</a>
				</div>

				<div class="col-sm-6">
					<p><strong>Community Members</strong>, log in with your name and library card number.</p>
					<div id="missingLoginPrompt" style="display: none">Please enter both {$usernameLabel} and {$passwordLabel}.</div>
					<div id="loginUsernameRow" class="form-group">
						<label for="username" class="control-label col-xs-12 col-sm-4">{$usernameLabel}:</label>
						<div class="col-xs-12 col-sm-8">
							<input type="text" name="username" id="username" value="{$username|escape}" size="28" class="form-control">
						</div>
					</div>
					<div id="loginPasswordRow" class="form-group">
						<label for="password" class="control-label col-xs-12 col-sm-4">{$passwordLabel}: </label>
						<div class="col-xs-12 col-sm-8">
							<input type="password" name="password" id="password" size="28" onkeydown="return Pika.submitOnEnter(event, '#loginForm');" class="form-control">
							{if $showForgotPinLink}
								<p class="text-muted help-block">
									<strong>{translate text="Forgot PIN?"}</strong>&nbsp;
									<a href="/MyAccount/EmailResetPin">{translate text='Reset My PIN'}</a>
								</p>
							{/if}
							{include file="MyAccount/selfReglink.tpl"}
						</div>
					</div>
					<div id="loginPasswordRow2" class="form-group">
						<div class="col-xs-12 col-sm-offset-4 col-sm-8">
							<label for="showPwd" class="checkbox">
								<input type="checkbox" id="showPwd" name="showPwd" onclick="return Pika.pwdToText('password')">
								{translate text="Reveal Password"}
							</label>

							{if !$isOpac}
								<label for="rememberMe" class="checkbox">
									<input type="checkbox" id="rememberMe" name="rememberMe">
									{translate text="Remember Me"}
								</label>
							{/if}
						</div>
					</div>
					<div class="form-group">
						<div class="col-xs-12 col-sm-offset-4 col-sm-8">
					<span class="modal-buttons">
						<input type="submit" name="submit" value="{if $multistep}Continue{else}Login{/if}" id="loginFormSubmit" class="btn btn-primary extraModalButton" onclick="return Pika.Account.processAjaxLogin()">
					</span>
						</div>
					</div>
				</div>

			</div>
		</form>
	{/if}
</div>
	{* The modal-footer is required for any follow-on actions that would add action buttons to the modal dialog *}
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" id="modalClose">Close</button>
		<span class="modal-buttons"></span>
	</div>
{/strip}
{literal}
<script>
	$('#username').focus().trigger('select'); // Select/highlight inputted text
	$(function(){
		Pika.Account.validateCookies();
		var haslocalStorage = Pika.hasLocalStorage() || false;
			if (haslocalStorage) {
				var rememberMe = (window.localStorage.getItem('rememberMe') == 'true'); // localStorage saves everything as strings
				if (rememberMe) {
					var lastUserName = window.localStorage.getItem('lastUserName'),
							lastPwd = window.localStorage.getItem('lastPwd');
{/literal}{*// showPwd = (window.localStorage.getItem('showPwd') == 'true'); // localStorage saves everything as strings *}{literal}
					$("#username").val(lastUserName);
					$("#password").val(lastPwd);
{/literal}{*// $("#showPwd").prop("checked", showPwd  ? "checked" : '');
//					if (showPwd) Pika.pwdToText('password');*}{literal}
				}
				$("#rememberMe").prop("checked", rememberMe ? "checked" : '');
			} else {
{/literal}{* // disable, uncheck & hide RememberMe checkbox if localStorage isn't available.*}{literal}
				$("#rememberMe").prop({checked : '', disabled: true}).parent().hide();
			}
{/literal}{* // Once Box is shown, focus on username input and Select the text;*}{literal}
			$("#modalDialog").on('shown.bs.modal', function(){
				$('#username').focus().trigger('select'); // Select/highlight inputted text
			})
		});
</script>
{/literal}