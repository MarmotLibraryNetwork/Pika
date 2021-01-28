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
	<div style="background-color: #FAFAFA; border: 1px lightgrey solid; border-top: none">
		<span>&nbsp;<a href="https://docs.google.com/document/d/1X95FQzu4rXeASs1wQbos2z0zSxGX6K4j4_mhgNA7nPM" target="_blank">Editor tips</a>&nbsp;|</span>
		{if !empty($property.allowableTags)}
			<span class="">Allowed HTML tags : <small><code>{$property.allowableTags|replace:'>':'> '|escape}</code></small></span>
		{/if}
	</div>
{/if}
