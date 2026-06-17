{if !empty($related_person)}
    <div class="panel" id="relatedPersonPanel">
        <a data-toggle="collapse" href="#relatedPersonPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related People</h2>
            </div>
        </a>
        <div id="relatedPersonPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {include file="Archive2/partials/relatedTaxonomyTiles.tpl" items=$related_person defaultImage="/interface/themes/responsive/images/people.png"}
            </div>
        </div>
    </div>
{/if}
{if !empty($related_place)}
    <div class="panel" id="relatedPlacePanel">
        <a data-toggle="collapse" href="#relatedPlacePanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related Place</h2>
            </div>
        </a>
        <div id="relatedPlacePanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {include file="Archive2/partials/relatedTaxonomyTiles.tpl" items=$related_place defaultImage="/interface/themes/responsive/images/places.png"}
            </div>
        </div>
    </div>
{/if}

{if !empty($related_event)}
    <div class="panel" id="relatedEventPanel">
        <a data-toggle="collapse" href="#relatedEventPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related Events</h2>
            </div>
        </a>
        <div id="relatedEventPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {include file="Archive2/partials/relatedTaxonomyTiles.tpl" items=$related_event defaultImage="/interface/themes/responsive/images/events.png"}
            </div>
        </div>
    </div>
{/if}

{if !empty($related_organization)}
    <div class="panel" id="relatedOrgPanel">
        <a data-toggle="collapse" href="#relatedOrgPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related Organization</h2>
            </div>
        </a>
        <div id="relatedOrgPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {include file="Archive2/partials/relatedTaxonomyTiles.tpl" items=$related_organization defaultImage="/interface/themes/responsive/images/organization.png"}
            </div>
        </div>
    </div>
{/if}