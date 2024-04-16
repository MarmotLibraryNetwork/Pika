{strip}
<table id="prospectorTitles" class="table table-striped">
	<th>Title</th>
	<th>Author</th>
	<th>Publication Date</th>
	<th>Format</th>
  {foreach from=$prospectorResults item=prospectorTitle}
	  {if $similar.recordId != -1}
		  <tr>
			  <td>
		      <a href="{$prospectorTitle.link}" rel="external" target="_blank">{$prospectorTitle.title|removeTrailingPunctuation|escape}</a>
			  </td>
		    <td>
				  {if $prospectorTitle.author}<small>{$prospectorTitle.author|escape}</small>{/if}
		    </td>
			  <td>
				  {if $prospectorTitle.pubDate}<small>{$prospectorTitle.pubDate|escape}</small>{/if}
			  </td>
			  <td>
				  {if $prospectorTitle.format}<small>{$prospectorTitle.format|escape}</small>{/if}
			  </td>
		  </tr>
	  {/if}
  {/foreach}
</table>
{/strip}