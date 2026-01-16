<div class="panel active" id="detailsPanel"><a data-toggle="collapse" href="#detailsPanelBody">
        <div class="panel-heading">
            <h2 class="panel-title">Details</h2>
        </div>
    </a>
    <div id="detailsPanelBody" class="panel-collapse collapse in">
        <div class="panel-body">
            {* Names *}
				{if $familyName}
					<div class="row">
						<div class="result-label col-sm-4">Family Name: </div>
						<div class="result-value col-sm-8">
							{$familyName}
						</div>
					</div>
				{/if}
				{if $givenName}
					<div class="row">
						<div class="result-label col-sm-4">Given Name: </div>
						<div class="result-value col-sm-8">
							{$givenName}
						</div>
					</div>
				{/if}
				{if $middleName}
					<div class="row">
						<div class="result-label col-sm-4">Middle Name: </div>
						<div class="result-value col-sm-8">
							{$middleName}
						</div>
					</div>
				{/if}
				{if $maidenNames}
					<div class="row">
						<div class="result-label col-sm-4">Maiden Name{if count($maidenNames) > 1}s{/if}: </div>
						<div class="result-value col-sm-8">
							{implode subject=$maidenNames}
						</div>
					</div>
				{/if}

				{if $alternateNames}
					<div class="row">
						<div class="result-label col-sm-4">Alternate Name{if count($alternateNames) > 1}s{/if}: </div>
						<div class="result-value col-sm-8">
							{implode subject=$alternateNames}
						</div>
					</div>
				{/if}

				{* Migration information *}
				{if $migratedFileName}
					<div class="row">
						<div class="result-label col-sm-4">Migrated Filename: </div>
						<div class="result-value col-sm-8">
							{$migratedFileName}
						</div>
					</div>
				{/if}

				{if $migratedIdentifier}
					<div class="row">
						<div class="result-label col-sm-4">Migrated Identifier: </div>
						<div class="result-value col-sm-8">
							{$migratedIdentifier}
						</div>
					</div>
				{/if}

				{if $contextNotes}
					<div class="row">
						<div class="result-label col-sm-4">Migration Context Notes: </div>
						<div class="result-value col-sm-8">
							{$contextNotes}
						</div>
					</div>
				{/if}

				{if $relationshipNotes}
					<div class="row">
						<div class="result-label col-sm-4">Migration Relationship Notes: </div>
						<div class="result-value col-sm-8">
							{$relationshipNotes}
						</div>
					</div>
				{/if}
        </div>
    </div>
</div>