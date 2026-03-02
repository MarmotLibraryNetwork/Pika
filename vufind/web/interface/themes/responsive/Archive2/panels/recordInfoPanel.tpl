{strip}
    <div class="panel" id="recordInfoPanel"><a data-toggle="collapse" href="#recordInfoPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Record Info</h2>
            </div>
        </a>
        <div id="recordInfoPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                <div class="row">
                    <div class="result-label col-sm-4">Node ID: </div>
                    <div class="result-value col-sm-8">
                        {$nid}
                    </div>
                </div>

                {if $member_of}
                    <div class="row">
                        <div class="result-label col-sm-4">Member Node ID: </div>
                        <div class="result-value col-sm-8">
                        {if is_array($member_of)}
                            {foreach from=$collectionInfo item="collection"}
                                <a href="{$collection.link}">{$collection.pid}</a> ({$collection.label})<br>
                            {/foreach}
                        </div>
                        {/if}
                    </div>
                {/if}

                {* Record Origin Info *}
                {if $recordOrigin}
                    <div class="row">
                        <div class="result-label col-sm-4">Entered By: </div>
                        <div class="result-value col-sm-8">
                            {$recordOrigin}
                        </div>
                    </div>
                {/if}
                {if $recordCreationDate}
                    <div class="row">
                        <div class="result-label col-sm-4">Entered On: </div>
                        <div class="result-value col-sm-8">
                            {$recordCreationDate}
                        </div>
                    </div>
                {/if}
                {if $recordChangeDate}
                    <div class="row">
                        <div class="result-label col-sm-4">Last Changed: </div>
                        <div class="result-value col-sm-8">
                            {$recordChangeDate}
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/strip}