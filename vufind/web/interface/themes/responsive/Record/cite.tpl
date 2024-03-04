{strip}
{if $citationCount < 1}
	{translate text="No citations are available for this record"}.
{else}
	<div style="text-align: left;">
		{if false && $ama}
			<b>{translate text="AMA Citation"}</b>
			<p style="width: 95%; padding-left: 25px; text-indent: -25px;">
				{include file=$ama}
			</p>
		{/if}

		{if $apa}
			<b>{translate text="APA Citation"}</b> <span class="styleGuide"><a href="https://owl.purdue.edu/owl/research_and_citation/apa_style/apa_formatting_and_style_guide/general_format.html">(style guide)</a></span>
			<p style="width: 95%; padding-left: 25px; text-indent: -25px;">
				{include file=$apa}
			</p>
		{/if}

		{if $chicagoauthdate}
			<b>{translate text="Chicago / Turabian - Author Date Citation"}</b> <span class="styleGuide"><a href="http://www.chicagomanualofstyle.org/tools_citationguide.html/">(style guide)</a></span>
			<p style="width: 95%; padding-left: 25px; text-indent: -25px;">
				{include file=$chicagoauthdate}
			</p>
		{/if}

		{if $chicagohumanities}
			<b>{translate text="Chicago / Turabian - Humanities Citation"}</b> <span class="styleGuide"><a href="http://www.chicagomanualofstyle.org/tools_citationguide.html/">(style guide)</a></span>
			<p style="width: 95%; padding-left: 25px; text-indent: -25px;">
				{include file=$chicagohumanities}
			</p>
		{/if}

		{if $mla}
			<b>{translate text="MLA Citation"}</b> <span class="styleGuide"><a href="https://owl.purdue.edu/owl/research_and_citation/mla_style/mla_formatting_and_style_guide/mla_formatting_and_style_guide.html">(style guide)</a></span>
			<p style="width: 95%; padding-left: 25px; text-indent: -25px;">
				{include file=$mla}
			</p>
		{/if}

	</div>
	<div class="alert alert-warning">
		<strong>Note! </strong>
		Citation formats are based on standards as of July 2022.  Citations contain only title, author, edition, publisher, and year published. Citations should be used as a guideline and should be double checked for accuracy.
	</div>
{/if}
{*{if $lightbox}
</div>
{/if}*}
{/strip}