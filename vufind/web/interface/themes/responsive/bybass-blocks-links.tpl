{strip}

	{* HTML and styling for skip block navigation taken from https://developer.mozilla.org *}

	<ul id="nav-access" class="a11y-nav">
		<li><a id="skip-main" href="#main">Skip to main content</a></li>
		<li><a id="skip-search" href="#lookfor">Skip to search</a></li>
		{if !empty($sidebar)}
			<li><a id="skip-select-language" href="#side-bar">Skip to sidebar</a></li>
		{/if}
	</ul>

{/strip}