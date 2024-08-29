{if $allowPinReset && !$offline}
<div class="panel active">
    <a data-toggle="collapse" data-parent="#account-settings-accordion" href="#pinPanel">
        <div class="panel-heading">
            <h2 class="panel-title">
                {translate text='Update PIN'}
            </h2>
        </div>
    </a>
    <div id="pinPanel" class="panel-collapse collapse in">
        <div class="panel-body">

{*            Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed*}
{*            back to server*}
            <form action="" method="post" class="form-horizontal" id="pinForm">
                <input type="hidden" name="updateScope" value="pin">
                
                <div class="form-group">
                    <div class="col-xs-4">
                        <label for="pin" class="control-label">{translate text='Old PIN'}:</label>
                    </div>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <input type="password" name="pin" id="pin" value=""
                                   class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}"
                                   aria-required="true">
{*                            No size limits in case previously set password doesn't meet current restrictions*}
                            <span class="input-group-btn" style="vertical-align: top" {* Override so button stays in place
                             when input requirement message displays *}>
                                <button aria-label="{translate text='PIN'} is hidden, click to show"
                                        onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin');"
                                        class="btn btn-default" type="button">
                                    <span class="glyphicon glyphicon-eye-close" aria-hidden="true" title="Show {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is hidden, click to show.</div>
                                    </span>
                                    <span class="glyphicon glyphicon-eye-open" style="display: none" title="Hide {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is visible, click to hide.</div>
                                    </span>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="col-xs-4">
                        <label for="pin1" class="control-label">{translate text='New PIN'}:</label>
                    </div>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <input type="password" name="pin1" id="pin1" value=""
                                   size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}"
                                   maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}"
                                   class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}"
                                   aria-required="true">
                            <span class="input-group-btn" style="vertical-align: top" {* Override so button stays in place
                                  when input requirement message displays *}>
                                <button aria-label="{translate text='PIN'} is hidden, click to show"
                                        onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin1')"
                                        class="btn btn-default" type="button">
                                    <span class="glyphicon glyphicon-eye-close" aria-hidden="true" title="Show {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is hidden, click to show.</div>
                                    </span>
                                    <span class="glyphicon glyphicon-eye-open" style="display: none" title="Hide {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is visible, click to hide.</div>
                                    </span>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-xs-4">
                        <label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label>
                    </div>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <input type="password" name="pin2" id="pin2" value=""
                                   size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}"
                                   maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}"
                                   class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}"
                                   aria-required="true">
                            <span class="input-group-btn" style="vertical-align: top" {* Override so button stays in place
                                  when input requirement message displays*}>
                                <button aria-label="{translate text='PIN'} is hidden, click to show"
                                        onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin2')"
                                        class="btn btn-default" type="button">
                                    <span class="glyphicon glyphicon-eye-close"
                                            aria-hidden="true"
                                            title="Show {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is hidden, click to show.</div>
                                    </span>
                                    <span
                                            class="glyphicon glyphicon-eye-open"
                                            style="display: none"
                                            title="Hide {translate text='PIN'}">
                                        <div class="hiddenText">{translate text='PIN'} is visible, click to hide.</div>
                                    </span>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-xs-8 col-xs-offset-4">
                        <input type="submit" value="{translate text='Update PIN'}" name="update" class="btn btn-sm btn-primary">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    {literal}
    $("#pinForm").validate({
        rules: {
            pin1: {
                minlength: {/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal},
                maxlength: {/literal}{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}{literal}},
            pin2: {
                equalTo: "#pin1",
                minlength: {/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal}
            }
        },
        submitHandler: function (form) {
            $("#pinForm input[type=submit]").attr("disabled", true);
            form.submit(); /* Using function variable form prevents recursion error that would trigger new loop of validations */
        }
    });
    {/literal}
</script>
{/if}