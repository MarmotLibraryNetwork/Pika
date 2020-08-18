<form action="/MyAccount/MyLists" id="myListFormHead">
    <h3 id="listsTitle">My Lists</h3>
{*    <div id="listTopButtons" class="btn-toolbar">*}
{*        <div class="btn-group">*}
{*            <button value="emailSelected" class="btn btn-sm btn-default">Email</button>*}
{*            <button value="copySelected" class="btn btn-sm btn-default">Copy Lists</button>*}
{*            <button value="transferSelected" class="btn btn-sm btn-danger">Transfer Lists</button>*}

{*        </div>*}
{*    </div>*}
    <input type="hidden" name="myListActionHead" id="myListActionHead" class="form">
    <input type="hidden" name="myListActionData" id="myListActionData" class="form">
    {foreach from=$myLists item=myList}
        {if $myList.id != -1}
            <div class="result">
        <div class="row">
            <div class="col-md-1">
{*                <label for="cb_{$myList.id}"></label><input type="checkbox" name="cb_{$myList.id}" id="cb_{$myList.id}" >*}
            </div>
            <div class="col-md-11">
                <div class="row">
                    <div class="col-xs-10 col-sm-10 col-md-10 col-lg-11">
                        <div class="row">
                            <div class="col-xs-12">
                                <a href="{$myList.url}" class="result-title notranslate">{$myList.name}</a>
                            </div>
                        </div>
                        <div class="row related-manifestation">
                            <div class="col-tn-3 col-xs-3">
                                <span class="result-label">Items:</span>
                                <span class="result-value">{if $myList.numTitles}{$myList.numTitles}{else}0{/if}</span>
                            </div>
                            <div class="col-tnt-4 col-xs-4">
                                <span class="result-label">List Access:</span>
                                <span class="result-value">{if $myList.isPublic}Public{else}Private{/if}</span>
                            </div>
                            <div class="col-tn-3 col-xs-3">
                                <span class="result-label">Default Sort:</span>
                                <span class="result-value">{$myList.defaultSort}</span>
                            </div>
                        </div>
                        <div class="row">

                            <div style="margin:5px;">
                            {if $myList.description}{$myList.description}{/if}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-tn-12 col-xs-12">
                                <div class="btn-group">
                                    <button value="emailList" class="btn btn-sm btn-default" id="FavEmail" onclick="return Pika.Lists.emailListAction('{$myList.id}');">Email List</button>
                                    <button value="exportToExcel" class="btn btn-sm btn-default" id="FavExcel" onclick="return Pika.Lists.exportListFromLists('{$myList.id}');">Export to Excel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-2 col-sm-2 col-md-2 col-lg-1">
                        {if $staff}
                        <div class="btn-group-vertical">
                            <button value="transferList" onclick="return Pika.Lists.transferListToUser({$myList.id}); " class="btn btn-danger">Transfer</button>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
            </div>
        {/if}
    {/foreach}

</form>
