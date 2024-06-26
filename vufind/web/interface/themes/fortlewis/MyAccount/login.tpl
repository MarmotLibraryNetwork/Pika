{strip}
<div id="page-content" class="col-xs-12">
	<h1 role="heading" aria-level="1" class="h2">{translate text='Log into your account'}</h1>
	<div id="loginFormWrapper">
		{if $message}{* Errors for Full Login Page *}
			<p class="alert alert-danger" id="loginError" >{$message|translate}</p>
		{else}
			<p class="alert alert-danger" id="loginError" style="display: none"></p>
		{/if}
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
			<form method="post" action="/MyAccount/Home" id="loginForm" class="form-horizontal">
				<div class="row">

					<div class="col-sm-6">
						<p><strong>Students, Faculty, and staff</strong>, log in with your Fort Lewis College Network Account.</p>
						<a href="/MyAccount/Home?casLogin" class="btn btn-primary">Student/Faculty/Staff Login</a>
					</div>

					<div class="col-sm-6">
						<p><strong>Community Members</strong>, log in with your name and library card number.</p>
						<div id="missingLoginPrompt" style="display: none">Please enter both {$usernameLabel} and {$passwordLabel}.</div>
						<div id="loginFormFields">
							<div id="loginUsernameRow" class="form-group">
								<label for="username" class="control-label col-xs-12 col-sm-4">{$usernameLabel}: </label>
								<div class="col-xs-12 col-sm-8">
									<input type="text" name="username" id="username" value="{$username|escape}" size="28" class="form-control">
								</div>
							</div>
							<div id="loginPasswordRow" class="form-group">
								<label for="password" class="control-label col-xs-12 col-sm-4">{$passwordLabel}: </label>
								<div class="col-xs-12 col-sm-8">
									<input type="password" name="password" id="password" size="28" onkeydown="return Pika.submitOnEnter(event, '#loginForm');" class="form-control">
									{if $showForgotPinLink}
										<p class="help-block">
											<strong>{translate text="Forgot PIN?"}</strong>
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

									{if !$inLibrary && !$isOpac}
										<label for="rememberMe" class="checkbox">
											<input type="checkbox" id="rememberMe" name="rememberMe">
											{translate text="Remember Me"}
										</label>
									{/if}
								</div>
							</div>

							<div id="loginPasswordRow2" class="form-group">
								<div class="col-xs-12 col-sm-offset-4 col-sm-8">
									<input type="submit" name="submit" value="Login" id="loginFormSubmit" class="btn btn-primary" onclick="return Pika.Account.preProcessLogin();">
									{if $followup}<input type="hidden" name="followup" value="{$followup}">{/if}
									{if $followupModule}<input type="hidden" name="followupModule" value="{$followupModule}">{/if}
									{if $followupAction}<input type="hidden" name="followupAction" value="{$followupAction}">{/if}
									{if $recordId}<input type="hidden" name="recordId" value="{$recordId|escape:"html"}">{/if}
									{*TODO: figure out how & why $recordId is set *}
									{if $id}<input type="hidden" name="id" value="{$id|escape:"html"}">{/if}{* For storing at least the list id when logging in to view a private list *}
									{if $comment}<input type="hidden" id="comment" name="comment" value="{$comment|escape:"html"}">{/if}
									{if $cardNumber}<input type="hidden" name="cardNumber" value="{$cardNumber|escape:"html"}">{/if}{* for masquerading *}
									{if $returnUrl}<input type="hidden" name="returnUrl" value="{$returnUrl}">{/if}
								</div>
							</div>

						</div>
					</div>
				</div>
			</form>
		{/if}
	</div>
</div>
{/strip}
{literal}
	<script>
		$('#username').focus().trigger('select'); // Select/highlight inputted text
		$(function(){
			Pika.Account.validateCookies();
			var haslocalStorage = Pika.hasLocalStorage() || false;
			if (haslocalStorage) {
				var rememberMe = (window.localStorage.getItem('rememberMe') == 'true'), // localStorage saves everything as strings
						showCovers = window.localStorage.getItem('showCovers') || false;
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
				if (showCovers.length > 0) {
					$("<input>").attr({
						type: 'hidden',
						name: 'showCovers',
						value: showCovers
					}).appendTo('#loginForm');
				}
			} else {
{/literal}{* // disable, uncheck & hide RememberMe checkbox if localStorage isn't available.*}{literal}
				$("#rememberMe").prop({checked : '', disabled: true}).parent().hide();
			}
{/literal}{* // Once Box is shown, focus on username input and Select the text;
			$("#modalDialog").on('shown.bs.modal', function(){
				$('#username').focus().trigger('select'); // Select/highlight inputted text
			})*}{literal}
		});
	</script>
{/literal}