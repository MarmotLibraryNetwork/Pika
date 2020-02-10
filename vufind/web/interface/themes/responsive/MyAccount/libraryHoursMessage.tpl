{strip}
{if $libraryHoursMessage}
	<div class="libraryHours alert alert-success">
    {$libraryHoursMessage}
    {if $showLibraryHoursAndLocationsLink}
			<a href="/AJAX/JSON?method=getHoursAndLocations" data-title="Library Hours and Locations" class="modalDialogTrigger pull-right">
				Additional {if !isset($numHours) || $numHours > 0}Library Hours{/if}{if (!isset($numHours) || $numHours > 0) && $numLocations != 1} &amp; {/if}{if $numLocations != 1}Locations{/if}
			</a>
    {/if}
	</div>
{/if}
{/strip}