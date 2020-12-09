<form action="/MyAccount/MyLists" id="myListFormHead">
    <h3 id="listsTitle">My Lists</h3>
    <div id="listTopButtons" class="btn-toolbar">
        <div class="btn-group">
            <a href="#" onclick="return Pika.Account.showCreateListForm();" class="btn btn-sm btn-primary">Create a New List</a>
            {if $showConvertListsFromClassic}
                <button value="copySelected" class="btn btn-sm btn-default" onclick="return Pika.Lists.importListsFromClassic();">Import Lists from Classic</button>
            {/if}



        </div>
    </div>
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


                                {if $myList.isPublic}
                                <div class="result-tools-horizontal btn-toolbar">

                                    <div class="btn-group btn-group-sm">
                                        <div class="share-tools" >
                                            <span class="share-tools-label hidden-inline-xs">SHARE LIST</span>
                                            <a herf="#" onclick="return Pika.Lists.emailListAction({$myList.id})" title="share via e-mail">
                                                <img src="{img filename='email-icon.png'}" alt="E-mail this" style="cursor:pointer;">
                                            </a>
                                            <a href="#" id="FavExcel" onclick="return Pika.Lists.exportListFromLists('{$myList.id}');" title="Export List to Excel">
                                                <img src="{img filename='excel.png'}" alt="Export to Excel" />
                                            </a>
                                            <a href="https://twitter.com/compose/tweet?text={$myList.title|escape:"html"}+{$url|escape:"html"}/MyAccount/MyList/{$myList.id}" target="_blank" title="Share on Twitter">
                                                <img src="{img filename='twitter-icon.png'}" alt="Share on Twitter">
                                            </a>
                                            <a href="http://www.facebook.com/sharer/sharer.php?u={$url|escape:"html"}/MyAccount/MyList/{$myList.id}" target="_blank" title="Share on Facebook">
                                                <img src="{img filename='facebook-icon.png'}" alt="Share on Facebook">
                                            </a>

                                            {include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|escape:"html"|cat:"/MyAccount/MyList/"|cat:$myList.id description="See My List '"|cat:$myList.title|cat:"' at $homeLibrary"}


                                        </div>
                                    </div>
                                </div>
                                    {else}
                                    <div class="btn-group btn-group-sm">
                                        <button value="emailList" class="btn btn-sm btn-default" id="Email List" onclick='return Pika.Lists.emailListAction("{$myList.id}")' title="share via e-mail">Email List</button>
                                        <button value="exportToExcel" class="btn btn-sm btn-default" id="FavExcel" onclick="return Pika.Lists.exportListFromLists('{$myList.id}');">Export to Excel</button>
                                    </div>
                                    {/if}


                            </div>
                        </div>
                    </div>
                    <div class="col-xs-2 col-sm-2 col-md-2 col-lg-1">
                        {if $staff && $myList.isPublic}
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
