<textarea name='{$propName}' id='{$propName}' rows='{$property.rows}' cols='{$property.cols}' title='{$property.description}' class='form-control {if $property.required}required{/if}'>{$propValue|escape}</textarea>
{if $property.type == 'html'}
	<script>
		{literal}
		$(document).ready(function(){
		ClassicEditor
						.create( document.querySelector( '#{/literal}{$propName}{literal}' ),{
							toolbar: {
								items: [
									'heading',
									'|',
									'bold',
									'italic',
									'underline',
									'link',
									'fontSize',
									'alignment',
									'|',
									'bulletedList',
									'numberedList',
									'indent',
									'outdent',
									'|',
									'htmlEmbed',
									'blockQuote',
									'insertTable',
									'undo',
									'redo'
								]
							},
							language: 'en',
							table: {
								contentToolbar: [
									'tableColumn',
									'tableRow',
									'mergeTableCells'
								]
							},
							licenseKey: '',

						} )
/*						.then( editor => {
							window.editor = editor;
						} )*/
						.catch( error => {
						console.error( error );
						} );
		});
		{/literal}
	</script>
	<div style="background-color: #FAFAFA; border: 1px lightgrey solid; border-top: none">
		<span>&nbsp;<a href="https://marmot-support.atlassian.net/l/c/iWkS65uV" target="_blank">Editor tips</a>&nbsp;|</span>
		{if !empty($property.allowableTags)}
			<span class="">Allowed HTML tags : <small><code style="white-space: break-spaces">{$property.allowableTags|replace:'>':'> '|escape}</code></small></span>
		{/if}
	</div>
{/if}
