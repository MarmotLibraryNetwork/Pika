{literal}
<script type="text/javascript">
	function googleTranslateElementInit() {
		new google.translate.TranslateElement({
			pageLanguage: 'en',
			layout: google.translate.TranslateElement.InlineLayout.SIMPLE
{/literal}
			{if $google_included_languages} , includedLanguages: '{$google_included_languages}'{/if}
			{if $trackTranslation} , gaTrack: true, gaId: '{$googleAnalyticsId}'{/if}
{literal}
		}, 'google_translate_element');
	}
	{/literal}{* Make embedded iframe compliant with standard WCAG 2.1 4.1.2 *}{literal}
	$(function(){
		$("iframe[name='votingFrame']").attr('title', 'Language Selector for Google Translate');
	});
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
{/literal}
