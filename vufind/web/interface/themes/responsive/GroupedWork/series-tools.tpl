{strip}
			<div class="searchtools card">
			<div class="card-body small">
				<strong>{translate text='Series Tools'}:</strong>
						&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.GroupedWork.seriesEmailForm(this, '{$recordDriver->getPermanentId()|escape:"url"}');"><span class="bi bi-envelope" aria-hidden="true"></span>&nbsp;{translate text='Email this Series'}</a>
						&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.GroupedWork.showSaveSeriesToListForm(this,'{$recordDriver->getPermanentId()|escape:"url"}' )"><span class="bi bi-plus-lg" aria-hidden="true"></span>&nbsp;{translate text='Add Series to List'}</a>
						&nbsp;&nbsp;<a class="text-nowrap" href="/{$recordDriver->getModule()}/{$recordDriver->getPermanentId()|escape:"url"}/AJAX?method=exportSeriesToExcel""><span class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></span>&nbsp;{translate text='Export To Excel'}</a>
			</div>
			</div>
{/strip}