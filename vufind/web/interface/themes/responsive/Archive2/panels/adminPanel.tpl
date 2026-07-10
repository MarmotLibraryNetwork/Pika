{* Not part of the Library Archive More Details settings. This panel predates the section-based accordion and is not currently referenced. *}
{strip}
	{if $isStaffUser} {*prevent display from non-staff users *}
    <div class="panel" id="adminPanel"><a data-bs-toggle="collapse" href="#adminPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Admin</h2>
            </div>
        </a>
        <div id="adminPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                <div class="row archive-field-row">
                    <div class="result-label col-sm-4">Islandora URL</div>
                    <div class="result-value col-sm-8"><a href="{$islandora_url}">{$islandora_url}</a></div>
                </div>
                <div class="row archive-field-row">
                    <div class="result-label col-sm-4">Reload cache</div>
                    <div class="result-value col-sm-8"><a href="{$cache_reload_url}">{$cache_reload_url}</a></div>
                </div>
            </div>
        </div>
    </div>
	{/if}
{/strip}

