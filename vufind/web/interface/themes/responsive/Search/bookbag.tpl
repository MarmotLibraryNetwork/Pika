<div id="bookbagContainer">
{foreach from=$items item=item key=key}
	<div class="row">
	  <div class="col-sm-2"><img src="{$item.cover}" alt="bookcover - {$item.title}"></div>
	  <div class ="col-sm-9">
			  <div class="col-sm-11"><span class="h4">{$item.title}</span></div>
		    <div class="col-sm-1"><button aria-label="remove {$item.title} from bookbag" class="btn btn-sm btn-danger" onclick="Pika.GroupedWork.removeFromBookbag({$idString},'{$key}')">remove</button></div>
			  <div class="col-sm-12"><em>{$item.author}</em></div>
		  <div class="row">
			  <div class="col-sm-12"><div class="summary">{$item.description|highlight|truncate_html:230:"..."}</div> </div>
		  </div>
	  </div>
  </div>
		<hr>
{/foreach}
</div>