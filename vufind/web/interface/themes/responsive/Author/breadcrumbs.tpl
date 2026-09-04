
{if $lastsearch}<li class="breadcrumb-item"><a href="{$lastsearch|escape}">{translate text="Search"}</a></li>{/if}
 
{if $pageTemplate=="home.tpl"}<li class="breadcrumb-item active" aria-current="page"><em>{$author.0|escape}, {$author.1|escape}</em></li>{/if}

{if $pageTemplate=="list.tpl"}<li class="breadcrumb-item active" aria-current="page"><em>{translate text="Author Results for"} {$lookfor|escape}</em></li>{/if}
