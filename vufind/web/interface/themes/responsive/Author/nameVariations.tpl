{strip}
    {if !empty($authorVariations)}
			<h4>Potential Author Name Variations</h4>
			<div class="row">
          {foreach from=$authorVariations item=variation}
						<div class="col-md-4 col-lg-6 text-start">
							<a href='/Author/Home?author="{$variation[0]}"' class="btn btn-outline-secondary btn-block">{$variation[0]} {*({$variation[1]})*}</a>
						</div>
          {/foreach}
			</div>
    {/if}
{/strip}