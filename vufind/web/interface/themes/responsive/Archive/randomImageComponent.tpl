{strip}
	<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
		<div class="archiveComponent horizontalComponent">
			<div class="archiveComponentBody">
				<div class="archiveComponentBox">
					<div class="archiveComponentHeader">Random Image</div>
					<div class="archiveComponentRandomImage row">
						<figure id="randomImagePlaceholder">
							{include file="Archive/randomImage.tpl"}
						</figure>
						<button id="refreshRandomImage" class="btn btn-default" onclick="return Pika.Archive.nextRandomObject('evld:localHistoryArchive');"><img src="/interface/themes/responsive/images/refresh.png" alt="New Random Image"></button>					</div>
				</div>
			</div>
		</div>
	</div>
{/strip}