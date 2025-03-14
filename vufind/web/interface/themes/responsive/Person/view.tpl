{strip}

	{* Search Navigation *}
	{include file="GroupedWork/search-results-navigation.tpl"}

	{if $error}
		<div class="row">
			<div class="alert alert-danger">
				{$error}
			</div>
		</div>
	{/if}

	<h1 role="heading" aria-level="1" class="h2">
	{$person->firstName|escape} {$person->middleName|escape}{if $person->nickName} "{$person->nickName|escape}"{/if}{if $person->maidenName} ({$person->maidenName}){/if} {$person->lastName|escape}
	</h1>
	{if $userIsAdmin}
		<p class="btn-toolbar">
			<div class="btn-group">
				<a href='/Admin/People?objectAction=edit&amp;id={$id}' title='Edit this person' class='btn btn-primary'>
					Edit
				</a>
				<a href='/Admin/Marriages?objectAction=add&amp;personId={$id}' title='Add a Marriage' class='btn btn-default'>
					Add Marriage
				</a>
				<a href='/Admin/Obituaries?objectAction=add&amp;personId={$id}' title='Add an Obituary' class='btn btn-default'>
					Add Obituary
				</a>
			</div>
			<a href='/Admin/People?objectAction=delete&amp;id={$id}' title='Delete this person' class='btn btn-danger' onclick='return confirm("Removing this person will permanently remove them from the system.	Are you sure?")'>
				Delete
			</a>
		</p>
	{/if}
	{* Display Person Image *}
	<div class="row">
		<div class="col-xs-4 col-sm-5 col-md-4 col-lg-3 text-center">
				<p>
			{if $disableCoverArt != 1}
				<div id="recordcover" class="text-center">
					{*<a href="/Person/{$id}">*}
						{if $person->picture}
							<a target='_blank' href='{$person->getImageUrl('large')}'><img src="{$person->getImageUrl('medium')}" class="listResultImage" alt="Image of {$person->displayName()}"></a><br>
						{else}
							<img src="/interface/themes/default/images/person.png" class="listResultImage" alt="{translate text='No Cover Image'}"><br>
						{/if}
					{*</a>*}
				</div>
			{/if}
		</div>
		<div {*id="main-content"*} class="col-xs-8 col-sm-7 col-md-8 col-lg-9">
			{if $person->otherName}
				<div class='personDetail'><span class='result-label'>Other Names: </span><span class='personDetailValue'>{$person->otherName|escape}</span></div>
			{/if}
			{if $birthDate}
				<div class='personDetail'><span class='result-label'>Birth Date: </span><span class='personDetailValue'>{$birthDate}</span></div>
			{/if}
			{if $deathDate}
				<div class='personDetail'><span class='result-label'>Death Date: </span><span class='personDetailValue'>{$deathDate}</span></div>
			{/if}
			{if $person->ageAtDeath}
				<div class='personDetail'><span class='result-label'>Age at Death: </span><span class='personDetailValue'>{$person->ageAtDeath|escape}</span></div>
			{/if}
			{if $person->sex}
				<div class='personDetail'><span class='result-label'>Sex: </span><span class='personDetailValue'>{$person->sex|escape}</span></div>
			{/if}
			{if $person->race}
				<div class='personDetail'><span class='result-label'>Race: </span><span class='personDetailValue'>{$person->race|escape}</span></div>
			{/if}
			{if $person->veteranOf}
				{implode subject=$person->veteranOf glue=", " assign='veteranOf'}
				<div class='personDetail'><span class='result-label'>Veteran Of: </span><span class='personDetailValue'>{$veteranOf}</span></div>
			{/if}
			{if $person->causeOfDeath}
				<div class='personDetail'><span class='result-label'>Cause of Death: </span><span class='personDetailValue'>{$person->causeOfDeath|escape}</span></div>
			{/if}
		</div>
		</p>
	</div>
	{if count($marriages) > 0 || $userIsAdmin}
		<h2 class="h2 blockhead">Marriages</h2>
		{foreach from=$marriages item=marriage}
			<p class="marriageTitle">
				 {$marriage.spouseName}{if $marriage.formattedMarriageDate} - {$marriage.formattedMarriageDate}{/if}
				 {if $userIsAdmin}
						<div class="btn-toolbar">
							<a href='/Admin/Marriages?objectAction=edit&amp;id={$marriage.marriageId}' title='Edit this Marriage' class='btn btn-primary'>
								Edit
							</a>
							<a href='/Admin/Marriages?objectAction=delete&amp;id={$marriage.marriageId}' title='Delete this Marriage' onclick='return confirm("Removing this marriage will permanently remove it from the system.	Are you sure?")' class='btn btn-danger'>
								Delete
							</a>
						</div>
				 {/if}
			</p>
			{if $marriage.comments}
				<p class="marriageComments">{$marriage.comments|escape}</p>
			{/if}
		{/foreach}

	{/if}
	{if $person->cemeteryName || $person->cemeteryLocation || $person->mortuaryName || $person->cemeteryAvenue || $person->lot || $person->block || $person->grave || $person->addition}
		<h2 class="blockhead h3">Burial Details</h2>
		{if $person->cemeteryName}
		<div class='personDetail'><span class='result-label'>Cemetery Name: </span><span class='personDetailValue'>{$person->cemeteryName}</span></div>
		{/if}
		{if $person->cemeteryLocation}
		<div class='personDetail'><span class='result-label'>Cemetery Location: </span><span class='personDetailValue'>{$person->cemeteryLocation}</span></div>
		{/if}
		{if $person->cemeteryAvenue}
			<div class='personDetail'><span class='result-label'>Cemetery Avenue: </span><span class='personDetailValue'>{$person->cemeteryAvenue}</span></div>
		{/if}
		{if $person->addition || $person->lot || $person->block || $person->grave}
		<div class='personDetail'><span class='result-label'>Burial Location:</span>
		<span class='personDetailValue'>
			{if $person->addition}Addition {$person->addition}{if $person->block || $person->lot || $person->grave}, {/if}{/if}
			{if $person->block}Block {$person->block}{if $person->lot || $person->grave}, {/if}{/if}
			{if $person->lot}Lot {$person->lot}{if $person->grave}, {/if}{/if}
			{if $person->grave}Grave {$person->grave}{/if}
		</span></div>
		{if $person->tombstoneInscription}
		<div class='personDetail'><span class='result-label'>Tombstone Inscription: </span><div class='personDetailValue'>{$person->tombstoneInscription}</div></div>
		{/if}
		{/if}
		{if $person->mortuaryName}
		<div class='personDetail'><span class='result-label'>Mortuary Name: </span><span class='personDetailValue'>{$person->mortuaryName}</span></div>
		{/if}
	{/if}
	{if count($obituaries) > 0 || $userIsAdmin}
		<h2 class="blockhead">Obituaries</h2>
			{include file="Person/obituariesSection.tpl"}

	{/if}
	{if $person->ledgerVolume || $person->ledgerYear || $person->ledgerEntry}
		<h2 class="blockhead">Ledger Information</h2>
		{if $person->ledgerVolume}
			<div class='personDetail'><span class='result-label'>Volume:</span><span class='bold'>{$person->ledgerVolume}</span></div>
		{/if}
		{if $person->ledgerYear}
			<div class='personDetail'><span class='result-label'>Year:</span><span class='personDetailValue'>{$person->ledgerYear}</span></div>
		{/if}
		{if $person->ledgerYear}
			<div class='personDetail'><span class='result-label'>Entry:</span><span class='personDetailValue'>{$person->ledgerEntry}</span></div>
		{/if}
	{/if}
	<h2 class="h3">Comments</h2>
	{if $person->comments}
	<div class='personComments'>{$person->comments|escape}</div>
	{else}
	<div class='personComments'>No comments found.</div>
	{/if}
{/strip}