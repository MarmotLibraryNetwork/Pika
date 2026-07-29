<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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

class ColoStateBookClub extends ExternalRequestPhysicalItemsDriver {
	/**
	 * Avoid displaying additional links for this collection.
	 * (Since the access link is just predetermined text to be used by URL replacement feature)
	 * @return array
	 */
	protected function getLinks(){
		return [];
	}

	/**
	 * Replace the inherited "Request Online" external link with an action that pops up the
	 * in-house Book Club Kit request form (login required, prepopulated with patron + title info).
	 */
	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $isExternalReservationItem = false, $relatedUrls = null, $volumeData = null){
		// Use the bare id (not getIdWithSource()) - index.php's loadModuleActionId() prepends the
		// indexing profile's sourceName itself when the URL module matches a recordUrlComponent
		// (e.g. "ColoradoBookClub"), so embedding the source here too would double-prefix the id.
		$url = '/' . $this->getModule() . '/' . $this->getId() . '/AJAX?method=getBookClubKitRequestForm';
		return [
			[
				'url'          => '',
				'onclick'      => "return Pika.Account.ajaxLightbox('$url', true);",
				'title'        => 'Request Book Club Kit',
				'requireLogin' => false, // login is gated by the ajaxLightbox call itself
			],
		];
	}
}