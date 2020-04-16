{* Javascript to embed on results pages to enable display modes. *}
{if !$onInternalIP}
	if (!Globals.opac &&Pika.hasLocalStorage()){ldelim}
	var temp = window.localStorage.getItem('searchResultsDisplayMode');
	if (Pika.Searches.displayModeClasses.hasOwnProperty(temp)) Pika.Searches.displayMode = temp; {* if stored value is empty or a bad value, fall back on default setting ("null" returned when not set) *}
	else Pika.Searches.displayMode = '{$displayMode}';
{rdelim}
	else Pika.Searches.displayMode = '{$displayMode}';
{else}
	Pika.Searches.displayMode = '{$displayMode}';
	Globals.opac = 1; {* set to true to keep opac browsers from storing browse mode *}
{/if}
$('#'+Pika.Searches.displayMode).parent('label').addClass('active'); {* show user which one is selected *}