{if $showUsernameField}
    <div class="panel active">
        <a data-toggle="collapse" data-parent="#account-settings-accordion" href="#notificationsPanel">
            <div class="panel-heading">
                <h2 class="panel-title">
                    Notifications
                </h2>
            </div>
        </a>
        <div id="notificationsPanel" class="panel-collapse collapse in">
            <div class="panel-body">
                <form action="" method="post" class="form-horizontal" id="notificationsForm">
                    <input type="hidden" name="updateScope" value="contact">
                    <input type="hidden" name="profileUpdateAction" value="updateNotificationsPreferences">
                    <div class="form-group">
                        <div class="col-xs-4">
                            <label for="notification_method">Receive Library Notifications By:</label>
                        </div>
                        <div class="col-xs-8">
                            {html_options name="notification_method" id="notification_method" class="form-control" options=$notificationOptions selected=$profile->noticePreferenceId}
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-4">
                            <label for="ereceipt_method">Receive E-receipts by:</label>
                        </div>
                        <div class="col-xs-8">
                            {html_options name="ereceipt_method" id="ereceipt_method" class="form-control" options=$eReceiptOptions selected=$profile->ereceiptId}
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-4">
                            <label for="email_format">Email Format:</label>
                        </div>
                        <div class="col-xs-8">
                            {html_options name="email_format" id="email_format" class="form-control" options=$emailFormatOptions selected=$profile->emailFormatId}
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-8 col-xs-offset-4">
                            <input type="submit" value="Update Notifications" name="updateNotifications" class="btn btn-sm btn-primary">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/if}