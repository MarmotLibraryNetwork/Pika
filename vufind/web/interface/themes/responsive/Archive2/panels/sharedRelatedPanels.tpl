{if !empty($related_person)}
    <div class="panel" id="relatedPersonPanel">
        <a data-toggle="collapse" href="#relatedPersonPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Related People</h2>
            </div>
        </a>
        <div id="relatedPersonPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                <div class="related-objects results-covers home-page-browse-thumbnails">
                    {foreach from=$related_person item=person}
                        <figure class="browse-thumbnail-sorted">
                            <a href="{$person.url|escape}"{if $person.name} data-title="{$person.name|escape}"{/if}>
                                <img src="{if $person.thumbnail}{$person.thumbnail|escape}{else}/interface/themes/responsive/images/people.png{/if}"
                                     alt="{$person.name|escape}">
                            </a>
                            <figcaption class="explore-more-category-title">
                                <strong>{$person.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
                                {if $person.relation_label} ({$person.relation_label|stripRelatorCode|escape}){/if}
                            </figcaption>
                        </figure>
                    {/foreach}
                </div>
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
                <div class="related-objects results-covers home-page-browse-thumbnails">
                    {foreach from=$related_place item=place}
                        <figure class="browse-thumbnail-sorted">
                            <a href="{$place.url|escape}"{if $place.name} data-title="{$place.name|escape}"{/if}>
                                <img src="{if $place.thumbnail}{$place.thumbnail|escape}{else}/interface/themes/responsive/images/places.png{/if}"
                                     alt="{$place.name|escape}">
                            </a>
                            <figcaption class="explore-more-category-title">
                                <strong>{$place.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
                                {if $place.relation_label} ({$place.relation_label|stripRelatorCode|escape}){/if}
                            </figcaption>
                        </figure>
                    {/foreach}
                </div>
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