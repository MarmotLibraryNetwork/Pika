<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace Archive2;

require_once ROOT_DIR . '/services/Archive2/ArchiveObject.php';

/**
 * Responsible for displaying an "Audio + PDF" object from Islandora2: a compound
 * node with both an audio file and a PDF attached directly to it (not as children).
 * Renders the audio player above the PDF.js viewer.
 */
class AudioPDF extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        global $library;

        // parent::launch() checks for a null mediaObject (e.g. missing/invalid id in the
        // URL) and shows the unavailable page before this method touches $this->mediaObject.
        parent::launch();

        // Both the audio and the PDF are "Original File" media, so getOriginalMedia()
        // can't tell them apart; pick each one by its media bundle instead.
        $audio = null;
        $pdf   = null;
        foreach ($this->mediaObject->getMedia() as $m) {
            if ($audio === null && $m->bundle === 'audio' && $m->useIs('original file')) {
                $audio = $m;
            } elseif ($pdf === null && $m->bundle === 'document' && $m->mime === 'application/pdf') {
                $pdf = $m;
            }
        }

        if ($audio === null) {
            $this->logger->warning('Audio media not found for audio + pdf node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('audioUrl', null);
            $interface->assign('audioMime', null);
        } else {
            $interface->assign('audioUrl', $audio->fileUrl);
            $interface->assign('audioMime', $audio->mime);
        }

        $thumb = $this->mediaObject->getThumbnail();
        $interface->assign('videoThumbnailUrl', $thumb->fileUrl ?? null);

        $captions = method_exists($this->mediaObject, 'getCaptions') ? $this->mediaObject->getCaptions() : [];
        $interface->assign('captions', json_decode(json_encode($captions), true));

        $transcripts = method_exists($this->mediaObject, 'getTranscripts') ? $this->mediaObject->getTranscripts() : [];
        $interface->assign('transcripts', $transcripts);

        if ($pdf === null) {
            $this->logger->error('PDF media not found for audio + pdf node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('pdf_url', null);
            $interface->assign('iframe_src', null);
        } else {
            $interface->assign('pdf_url', $pdf->fileUrl);
            $libraryUrl = rtrim($library->catalogUrl, "/");
            $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $iframeSrc = "/js/pdfjs/web/viewer.html?file=" . urlencode($protocol . $libraryUrl . "/Archive2/AJAX?method=fetchPDFFile&pdf_file=" . $pdf->fileUrl . "&nid=" . $this->mediaObject->getNodeId());
            $interface->assign('iframe_src', $iframeSrc);
        }

        $interface->assign('viewer', 'audio_pdf');

        $title = $this->mediaObject->getTitle();
        parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}
