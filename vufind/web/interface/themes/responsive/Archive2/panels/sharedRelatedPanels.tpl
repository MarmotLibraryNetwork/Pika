{if $related_person}
    <div class="panel" id="relatedPersonPanel">
        <a data-toggle="collapse" href="#relatedPersonPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related People</h2>
            </div>
        </a>
        <div id="relatedPersonPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {foreach from=$related_person item=person}
                    <div class="row archive-field-row">
                        <div class="result-label col-sm-4">
                            {$person.relation_label}
                        </div>
                        <div class="result-value col-sm-4">
                            <a href="/Archive2/Person/{$person.tid}">{$person.name|escape}</a>
                        </div>
                        <div class="result-value col-sm-4">
                            {if $person.note}{$person.note}{/if}
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    </div>
{/if}
{if $related_place}
    <div class="panel" id="relatedPlacePanel">
        <a data-toggle="collapse" href="#relatedPlacePanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related Place</h2>
            </div>
        </a>
        <div id="relatedPlacePanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {foreach from=$related_place item=place}
                    <div class="row archive-field-row">
                        <div class="result-label col-sm-4">
                            {$place.relation_label}
                        </div>
                        <div class="result-value col-sm-8">
                            <a href="/Archive2/Place/{$place.tid}">{$place.name|escape}</a>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    </div>
{/if}

{if $related_organization}
    <div class="panel" id="relatedOrgPanel">
        <a data-toggle="collapse" href="#relatedOrgPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related Organization</h2>
            </div>
        </a>
        <div id="relatedOrgPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {foreach from=$related_organization item=org}
                    <div class="row archive-field-row">
                        <div class="result-label col-sm-4">
                            {$org.relation_label}
                        </div>
                        <div class="result-value col-sm-8">
                            <a href="/Archive2/Organization/{$org.tid}">{$org.name|escape}</a>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    </div>
{/if}