<script src="https://unpkg.com/mirador@latest/dist/mirador.min.js"></script>
{* Position and height must be set for viewer to display properly
   See https://github.com/ProjectMirador/mirador/wiki/Embedding-in-Another-Environment#styling-issues *}
<div class="clearfix" height="1200" style="position:relative; height: 1000px;">
    <div id="mirador-viewer"></div>
</div>

<script type="text/javascript">
    {include file="Archive2/mirador.init.tpl" nid=$nid}
</script>