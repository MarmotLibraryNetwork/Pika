{* Helper template that extracts data from mediaObject and includes the audio viewer *}
{php}
    global $interface;

    // Get the mediaObject from the template variable
    $childMediaObject = $this->get_template_vars('childMediaObject');

    // Extract audio data
    $audio = $childMediaObject->getAudio();
    $interface->assign('audioUrl', $audio->fileUrl);
    $interface->assign('audioMime', $audio->mime);

    // Get thumbnail
    $thumb = $childMediaObject->getThumbnail();
    $interface->assign('videoThumbnailUrl', $thumb->fileUrl);

    // Get captions
    $captions = $childMediaObject->getCaptions();
    $captionsArray = json_decode(json_encode($captions), true);
    $interface->assign('captions', $captionsArray);

    // Get transcripts
    $transcripts = $childMediaObject->getTranscripts();
    $interface->assign('transcripts', $transcripts);
{/php}

{* Now include the actual viewer template *}
{include file="Archive2/audio.tpl"}
