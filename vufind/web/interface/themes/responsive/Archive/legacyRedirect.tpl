{* Archive/legacyRedirect.tpl
 *
 * Shown when a legacy Islandora 1 Archive URL is permanently redirected to its
 * Islandora 2 Archive2 equivalent.  The 301 Location header is already sent by
 * the PHP controller; this page is the human-readable response body.
 *}
<div class="col-xs-12">
  <h1>Page Permanently Moved</h1>

  {if $newUrl}
    <p>This page has permanently moved to a new address. Please update your bookmarks.</p>

    <p>
      <strong>New address:</strong>
      <a id="legacy-redirect-url" href="{$newUrl|escape}">{$newUrl|escape}</a>
    </p>

    <p>
      You will be redirected automatically in
      <strong><span id="legacy-redirect-countdown">5</span></strong>
      second{if true}s{/if}.
    </p>

    <script>
      (function () {
        'use strict';
        var seconds  = 5;
        var display  = document.getElementById('legacy-redirect-countdown');
        var newUrl   = {$newUrl|json_encode};

        var interval = setInterval(function () {
          seconds -= 1;
          if (display) {
            display.textContent = seconds;
          }
          if (seconds <= 0) {
            clearInterval(interval);
            window.location.replace(newUrl);
          }
        }, 1000);
      }());
    </script>

  {else}

    <p>This page has been permanently moved, but the new address could not be determined automatically.</p>
    {if $pid}
      <p>The legacy identifier was: <code>{$pid|escape}</code></p>
    {/if}
    <p>Please try searching for the item using the search bar above.</p>

  {/if}
</div>
