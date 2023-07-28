{strip}
			<div class="searchtools well small">
				<strong>{translate text='Series Tools'}:</strong>
						&nbsp;&nbsp;<a href="#" onclick="return Pika.GroupedWork.seriesEmailForm(this, '{$recordDriver->getPermanentId()|escape:"url"}');"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>&nbsp;{translate text='Email this Series'}</a>
						&nbsp;&nbsp;<a href="#" onclick="return Pika.GroupedWork.showSaveSeriesToListForm(this,'{$recordDriver->getPermanentId()|escape:"url"}' )"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;{translate text='Add Series to List'}</a>
						&nbsp;&nbsp;<a href="/{$recordDriver->getModule()}/{$recordDriver->getPermanentId()|escape:"url"}/AJAX?method=exportSeriesToExcel""><span class="glyphicon glyphicon-th" aria-hidden="true"></span>&nbsp;{translate text='Export To Excel'}</a>
			</div>
{/strip}