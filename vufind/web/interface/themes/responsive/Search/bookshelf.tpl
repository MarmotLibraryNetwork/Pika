<div id="bookshelfContainer">
{foreach from=$items item=item key=key}
	<div class="row result-title-row">
		<div class="col-sm-10"><h3 class="h4" tabindex="0">{$item.title}</h3></div>
		<div class="col-sm-2"><button aria-label="remove {$item.title} from bookshelf" class="btn btn-sm btn-danger" onclick="Pika.GroupedWork.removeFromBookshelf({$idString},'{$key}')">Remove</button></div>
	</div>
	<div class="row">
		<div class="col-sm-2"><img src="{$item.cover}" alt="bookcover - {$item.title}"></div>
		<div class ="col-sm-9">
				<div class="col-sm-12"><em>{$item.author}</em></div>
			<div class="row">
				<div class="col-sm-12"><div class="summary">{$item.description|highlight|truncate_html:230:"..."}</div> </div>
			</div>
		</div>
	</div>
		<hr>
{/foreach}
</div>