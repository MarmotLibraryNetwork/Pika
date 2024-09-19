{if $showUsernameField}
    <div class="panel active">
        <a data-toggle="collapse" data-parent="#account-settings-accordion" href="#usernamePanel">
            <div class="panel-heading">
                <h2 class="panel-title">
                    Update Username
                </h2>
            </div>
        </a>
        <div id="usernamePanel" class="panel-collapse collapse in">
            <div class="panel-body">
                <form action="" method="post" class="form-horizontal" id="usernameForm">
                    <input type="hidden" name="updateScope" value="contact">
                    <input type="hidden" name="profileUpdateAction" value="updatePatronUsername">
                <div class="form-group">
                    <div class="col-xs-4"><label for="alternate_username">Username:</label>
                    </div>
                    <div class="col-xs-8">
                        {if !empty($linkedUsers) && count($linkedUsers) > 1 && $selectedUser != $activeUserId}
                            {*Security: Prevent changing email, username, or password for linked accounts. See D-4031 *}
                            {if !empty(trim($profile->alt_username))}{$profile->alt_username|escape}{/if}
                        {else}
                            <input type="text" name="alternate_username" id="alternate_username"
                                   value="{if !is_numeric(trim($profile->alt_username))}{$profile->alt_username|escape}{/if}" size="25"
                                   maxlength="25" class="form-control">
                        {/if}
                        <button href="#" class="btn-link" onclick="$('#usernameHelp').toggle()">What is this?</button>
                        <div id="usernameHelp" style="display:none">
                            A username is an optional feature. If you set one, your username&nbsp;
                            will be your alias on hold slips and can also be used to log into&nbsp;
                            your account in place of your card number. A username can be set,&nbsp;
                            reset or removed from the “Account Settings” section of your online&nbsp;
                            account. Usernames must be between 6 and 25 characters (letters and&nbsp;
                            number only, no special characters).
                        </div>
                    </div>
                </div>
                    <div class="form-group">
                        <div class="col-xs-8 col-xs-offset-4">
                            <input type="submit" value="Update Username" name="updateUsername" class="btn btn-sm btn-primary">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/if}