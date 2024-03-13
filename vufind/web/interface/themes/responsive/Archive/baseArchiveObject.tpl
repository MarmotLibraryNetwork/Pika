{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" class="h2">
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h1>
		<div class="row">
			<div class="col-xs-4 col-sm-5 col-md-4 col-lg-3 text-center">
				<div class="main-project-image">
					{if $large_image}<a href="{$large_image}">{/if}
					<img src="{$medium_image}" class="img-responsive">
						{if $large_image}</a>{/if}
				</div>

			</div>
			<div id="main-content" class="col-xs-8 col-sm-7 col-md-8 col-lg-9">
				{if !empty($alternateNames)}
					<div class="row">
						<div class="result-label col-sm-4">Alternate Name{if count($alternateNames) > 1}s{/if}: </div>
						<div class="result-value col-sm-8">
							{foreach from=$alternateNames item=alternateName}
								{$alternateName}<br>
							{/foreach}
						</div>
					</div>
				{/if}

				{if strlen($placeStartDate)}
					<div class="row">
						<div class="result-label col-sm-4">Founded: </div>
						<div class="result-value col-sm-8">
							{$placeStartDate}
						</div>
					</div>
				{/if}

				{if strlen($placeEndDate)}
					<div class="row">
						<div class="result-label col-sm-4">Dissolved: </div>
						<div class="result-value col-sm-8">
							{$placeEndDate}
						</div>
					</div>
				{/if}

				{if strlen($organizationStartDate)}
					<div class="row">
						<div class="result-label col-sm-4">Established: </div>
						<div class="result-value col-sm-8">
							{$organizationStartDate}
						</div>
					</div>
				{/if}

				{if strlen($organizationEndDate)}
					<div class="row">
						<div class="result-label col-sm-4">Dissolved: </div>
						<div class="result-value col-sm-8">
							{$organizationEndDate}
						</div>
					</div>
				{/if}

				{if $eventStartDate || $eventEndDate}
					<div class="row">
						<div class="result-label col-sm-4">Date: </div>
						<div class="result-value col-sm-8">
							{$eventStartDate} {if $eventEndDate} to {$eventEndDate}{/if}
						</div>
					</div>
				{/if}

				{if $relatedPlaces && $recordDriver->getType() == 'event'}
					<div class="row">
						<div class="result-label col-sm-4">Took place at: </div>
						<div class="result-value col-sm-8">
							{foreach from=$relatedPlaces item=entity}
								<a href='{$entity.link}'>
									{$entity.label}
								</a>
								{if $entity.role}
									&nbsp;({$entity.role})
								{/if}
								{if $entity.note}
									&nbsp;- {$entity.note}
								{/if}
								<br>
							{/foreach}
						</div>
					</div>
				{/if}

				{if $primaryUrl}
					<div class="row">
						<div class="result-label col-sm-4">Website: </div>
						<div class="result-value col-sm-8">
							<a href="{$primaryUrl}">{$primaryUrl}</a>
						</div>
					</div>
				{/if}

				{if $addressInfo && $addressInfo.hasDetailedAddress}
					<div class="row">
						<div class="result-label col-sm-4">Address: </div>
						<div class="result-value col-sm-8">
							<address>
								{if $addressInfo.addressStreetNumber || $addressInfo.addressStreet}
									{$addressInfo.addressStreetNumber} {$addressInfo.addressStreet}<br>
								{/if}
								{if $addressInfo.address2}
									{$addressInfo.address2}<br>
								{/if}
								{if $addressInfo.addressCity || $addressInfo.addressState || $addressInfo.addressZipCode}
									{$addressInfo.addressCity}
									{if $addressInfo.addressCity && $addressInfo.addressState}
										,&nbsp;
									{/if}
									{$addressInfo.addressState} {$addressInfo.addressZipCode}
								{/if}
							</address>
						</div>
					</div>
					{if $addressInfo.addressCounty}
						<div class="row">
							<div class="result-label col-sm-4">County: </div>
							<div class="result-value col-sm-8">
									{if $addressInfo.addressCounty}
										{$addressInfo.addressCounty}
									{/if}
							</div>
						</div>
					{/if}
					{if $addressInfo.addressCountry}
						<div class="row">
							<div class="result-label col-sm-4">Country: </div>
							<div class="result-value col-sm-8">
									{if $addressInfo.addressCountry}
										{$addressInfo.addressCountry}
									{/if}
							</div>
						</div>
					{/if}
					{if $addressInfo.addressOtherRegion}
						<div class="row">
							<div class="result-label col-sm-4">Other Region: </div>
							<div class="result-value col-sm-8">
									{if $addressInfo.addressOtherRegion}
										{implode subject=$addressInfo.addressOtherRegion}
									{/if}
							</div>
						</div>
					{/if}
				{/if}

				{* Display map if it exists *}
				{if $mapsKey && $addressInfo.latitude && $addressInfo.longitude}
					{if $addressInfo.latitude && $addressInfo.longitude}
						<div class="row">
							<div class="result-label col-sm-4">Location: </div>
							<div class="result-value col-sm-8">
								<iframe width="100%" height="" frameborder="0" style="border:0" src="https://www.google.com/maps/embed/v1/place?q={$addressInfo.latitude|escape}%2C%20{$addressInfo.longitude|escape}&key={$mapsKey}" allowfullscreen></iframe>
							</div>
						</div>
						<div class="row">
							<div class="result-value col-sm-8 col-sm-offset-4">
								{$addressInfo.latitude}, {$addressInfo.longitude}
							</div>
						</div>
					{/if}
				{/if}

			</div>
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script>
	$().ready(function(){ldelim}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>