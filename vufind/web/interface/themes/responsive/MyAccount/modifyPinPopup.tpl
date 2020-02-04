{* Deprecated. THis should be deleted and the main resetPin template should be used instead. pascal 10-18-2018
<div>
	<form method="post" name="modifyPinNumber" action="/MyAccount/Profile">
		<div>
			<input type="hidden" name="updatePin" value="true"/>
			<div>
				<label for="pin" class='loginLabel'>Please enter your current PIN:</label><input type="password" name="pin" id="pin" size="4" maxlength="4" />
			</div>
			<div>
				<label for="pin1" class='loginLabel'>Enter your <strong>new</strong> PIN:</label><input type="password" name="pin1" id="pin1" size="4" maxlength="4" />
			</div>
			<div>
				<label for="pin2" class='loginLabel'>Enter your <strong>new</strong> PIN again:</label><input type="password" name="pin2" id="pin2" size="4" maxlength="4" />
			</div>
			<div>
				<input type="submit" value="Set new PIN" />
			</div>
		</div>
	</form>
</div>
*}
