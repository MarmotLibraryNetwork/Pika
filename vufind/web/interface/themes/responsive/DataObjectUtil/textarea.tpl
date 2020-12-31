<textarea name='{$propName}' id='{$propName}' rows='{$property.rows}' cols='{$property.cols}' title='{$property.description}' class='form-control {if $property.required}required{/if}'>{$propValue|escape}</textarea>
{if $property.type == 'html'}
	<script type="text/javascript">
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
{/if}
