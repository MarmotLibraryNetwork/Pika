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

/* Responsible for displaying video from Islandora2 */
class DigitalDocument extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        global $library;
        
        parent::launch();

        $pdf = $this->mediaObject->getOriginalMedia();
        // if original media isn't found try to get the just the PDF file
        if ($pdf === null) {
            // TODO: this overrides the viewer hint.
            $pdf = $this->mediaObject->getPDFMedia();
        }

        if ($pdf === null) {
            $this->logger->error('PDF media not found for digital document.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('pdf_url', null);
            $interface->assign('iframe_src', null);
        } else {
            $interface->assign('pdf_url', $pdf->fileUrl);
            $libraryUrl = rtrim($library->catalogUrl, "/");
            $protocol = $_SERVER['HTTPS'] ? 'https://' : 'http://';
            $iframeSrc = "/js/pdfjs/web/viewer.html?file=" . urlencode($protocol . $libraryUrl . "/Archive2/AJAX?method=fetchPDFFile&pdf_file=" . $pdf->fileUrl);
            $interface->assign('iframe_src', $iframeSrc);
        }
        
        $interface->assign('viewer', 'pdfjs');

        $title = $this->mediaObject->getTitle();
        return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}