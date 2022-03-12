{strip}
    {if !empty($authorVariations)}
			<h4>Potential Author Name Variations</h4>
			<div class="row">
          {foreach from=$authorVariations item=variation}
						<div class="col-sm-4 col-md-6 text-left">
							<a href='/Author/Home?author="{$variation[0]}"' class="btn btn-default btn-block">{$variation[0]} ({$variation[1]})</a>
						</div>
          {/foreach}
			</div>
    {/if}
{/strip}