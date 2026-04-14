{if $subjects}
    <div class="panel" id="subjectsPanel">
        <a data-toggle="collapse" href="#subjectsPanelBody">
            <div class="panel-heading">
                <h2 class="panel-title">Subjects</h2>
            </div>
        </a>
        <div id="subjectsPanelBody" class="panel-collapse collapse">
            <div class="panel-body">
                {foreach from=$subjects item=subject}
                    <div class="row archive-field-row">
                        <div class="result-value col-sm-12">
                            <a href="/Archive2/Results?filter[]=sm_field_subject:{$subject.name|escape}">{$subject.name|escape}</a>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    </div>
{/if}