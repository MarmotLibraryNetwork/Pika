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

require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/MediaObjectInterface.php';

use Islandora2\I2ObjectFactory;
use Islandora2\MediaObjectInterface;

/* responsible for displaying template */
class ArchiveObject extends \Action
{
    protected ?MediaObjectInterface $mediaObject = null;
    /** node ID */
    protected int $nid;


    public function __construct()
    {
        $nid = (int)$_GET['nid'];
        if ($nid <= 0) {
            // redirect to 404;
        }
        $factory = new I2ObjectFactory();
        $this->mediaObject = $factory->fromNodeId($nid);
    }

    public function display($mainContentTemplate, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl')
    {
        if ($this->mediaObject === null) {
            return;
        }

        $pageTitle = $pageTitle ?? $this->mediaObject->getTitle() ?? 'Archive Object';

        parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
    }

	public function launch()
	{
		global $interface;

        $interface->assign('showExploreMore', true);
        $interface->assign('debug_archive_object', true);

		// Expose every field from the Islandora node (with "field_" removed) to the templates.
		$nodeData = $this->mediaObject->getNodeWithoutFieldPrefix();
		foreach ($nodeData as $field => $value){
			$interface->assign($field, $value);
		}
        
        // Media
		$interface->assign('media', $nodeData['media'] ?? []);
        
        // Overrides
        // Dates
		$interface->assign('created', $this->formatDisplayDate($nodeData['created'] ?? null));
		$interface->assign('changed', $this->formatDisplayDate($nodeData['changed'] ?? null));

        // Viewing permissions (true or false)
        $interface->assign('can_view', $this->canCurrentUserView());

        // Download permissions
        $interface->assign('can_download', $this->canCurrentUserDownload());

        // Language
        $language = null;
        if ($this->mediaObject->language['name'] && $this->mediaObject->language['name'] != '') {
            $language = $this->mediaObject->language['name'];
        }
        $interface->assign('language', $language);

        // Titles
        $title = ($this->mediaObject->getTitle() !== null) ? $this->mediaObject->getTitle() : null;
        $interface->assign('title', $title);
        // breadcrumb
        $interface->assign('breadcrumbText', $title);

        $subtitle = ($this->mediaObject->subtitle !== null) ? $this->mediaObject->subtitle : null;
        $interface->assign('subtitle', $subtitle);

        // Summary
        // TODO: I am here
        //$summary = ($this->mediaObject->library['thename'] !== null) ? $this->mediaObject->library['name'] : null;
        // Description
        $description = ($this->mediaObject->getDescription() !== null) ? $this->mediaObject->getDescription() : null;
        $interface->assign('description', $description);

        // Subjects
        $subjects = $this->mediaObject->getSubjects();
        if (is_array($subjects)) {
            foreach ($subjects as $subject) {
                $subject['url'] = "/Archive/Subject?tid=" . $subject['tid']; # TODO: determine the correct url structure.
            }
        } else {
            $subjects = [];
        }
        $interface->assign('subjects', $subjects);

        // Extent (physical description)
        $extent = ($this->mediaObject->extent !== null) ? $this->mediaObject->extent : null;
        $interface->assign('physical_description', $extent);

        // Library
        $library = ($this->mediaObject->library['name'] !== null) ? $this->mediaObject->library['name'] : null;
        $interface->assign('library', $library);
        $libraryTid = ($this->mediaObject->library['tid'] !== null) ? $this->mediaObject->library['tid'] : null;
        $interface->assign('library_tid', $libraryTid);
        $libraryUrl = "/Archive/Library?tid=" . $libraryTid;
        $interface->assign('library_url', $libraryUrl);
        $libraryNamespace = ($this->mediaObject->library['namespace'] !== null) ? $this->mediaObject->library['namespace'] : null;
        $interface->assign('library_url', $libraryNamespace);

        // Location
        $locatedAt = ($this->mediaObject->located_at !== null) ? $this->mediaObject->located_at : null;
        $interface->assign('located_at', $locatedAt);
        $locationUrl = ($this->mediaObject->location_url !== null) ? $this->mediaObject->location_url : null;
        $interface->assign('location_url', $locationUrl);

        // Shelf Location
        $shelfLocation = ($this->mediaObject->shelf_location !== null) ? $this->mediaObject->shelf_location : null;
        $interface->assign('shelf_location', $shelfLocation);

        // Interview Location
        // NOTE: field_location is labeled as Interview Location in UI
        $rawInterviewLocations = ($this->mediaObject->location !== null) ? $this->mediaObject->location : [];
        $interviewLocations = [];
        // Determine if the location field has multipule locations
        // Single entry, put it into an array
        if (array_key_exists('id', $rawInterviewLocations)) {
            $tempLoc = $rawInterviewLocations;
            unset($rawInterviewLocations);
            $rawInterviewLocations = [];
            $rawInterviewLocations[] = $tempLoc;
        }

        foreach ($rawInterviewLocations as $rawInterviewLocation) {
            $interviewLocation = [
                'city' => $rawInterviewLocation['city'] ?? null,
                'state' => $rawInterviewLocation['state'] ?? '',
                'street' => $rawInterviewLocation['street'] ?? '',
                'county' => $rawInterviewLocation['county'] ?? '',
                'country' => $rawInterviewLocation['country'] ?? '',
                'zip' => $rawInterviewLocation['zip_code'] ?? '',
                'address2' => $rawInterviewLocation['address_2'] ?? '',
                'id' => $rawInterviewLocation['id'],
            ];
            $interviewLocations[] = $interviewLocation;
        }
        $interface->assign('interview_locations', $interviewLocations);

        // Local identifier
        $localIdentifier = ($this->mediaObject->local_identifier !== null) ? $this->mediaObject->shelf_location : null;
        $interface->assign('local_identifer', $localIdentifier);

        

    }

	private function formatDisplayDate($value): ?string {
		if ($value === null || $value === '') {
			return null;
		}

		try {
			if (is_numeric($value)) {
				$date = new \DateTimeImmutable('@' . (int)$value);
			}else{
				$date = new \DateTimeImmutable((string)$value);
			}
		}catch (\Exception $e){
			return null;
		}

		$date = $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		return $date->format('m/d/Y h:i a');
	}

    protected function canCurrentUserDownload(): bool {
        //$user = \UserAccount::getLoggedInUser();
        // TODO: implement user download permissions
        return true;

    }

    /**
     * Determine if the current patron can view the object.
     */
    protected function canCurrentUserView(): bool
    {
        return true;
        if ($this->mediaObject->pika_usage === 'no') {
            return false;
        }

        return true;

        $viewingRestrictions = $this->resolveViewingRestrictions();
        if (count($viewingRestrictions) === 0) {
            return true;
        }

        $canView            = false;
        $validHomeLibraries = [];
        $userPTypes         = [];

        $user = \UserAccount::getLoggedInUser();
        if ($user && $user->getHomeLibrary()) {
            $validHomeLibraries[] = $user->getHomeLibrary()->subdomain;
            $userPTypes           = $user->getRelatedPTypes();
            $linkedAccounts       = $user->getLinkedUsers();
            foreach ($linkedAccounts as $linkedAccount) {
                $validHomeLibraries[] = $linkedAccount->getHomeLibrary()->subdomain;
            }
        }

        global $locationSingleton;
        $physicalLocation         = $locationSingleton->getPhysicalLocation();
        $physicalLibrarySubdomain = null;
        if ($physicalLocation) {
            $physicalLibrary            = new \Library();
            $physicalLibrary->libraryId = $physicalLocation->libraryId;
            if ($physicalLibrary->find(true)) {
                $physicalLibrarySubdomain = $physicalLibrary->subdomain;
            }
        }

        foreach ($viewingRestrictions as $restriction) {
            $restrictionType = 'homeLibraryOrIP';
            if (strpos($restriction, ':') !== false) {
                [$restrictionType, $restriction] = explode(':', $restriction, 2);
            }
            $restrictionType  = strtolower(trim($restrictionType));
            $restrictionType  = str_replace(' ', '', $restrictionType);
            $restriction      = trim($restriction);
            $restrictionLower = strtolower($restriction);
            if ($restrictionLower === 'anonymousmasterdownload' || $restrictionLower === 'verifiedmasterdownload') {
                continue;
            }

            if ($restrictionType === 'homelibraryorip' || $restrictionType === 'patronsfrom') {
                $libraryDomain = trim($restriction);
                if ($restrictionLower === 'default' || array_search($libraryDomain, $validHomeLibraries, true) !== false) {
                    $canView = true;
                    break;
                }
            }

            if ($restrictionType === 'homelibraryorip' || $restrictionType === 'withinlibrary') {
                $libraryDomain = trim($restriction);
                if ($libraryDomain === $physicalLibrarySubdomain) {
                    $canView = true;
                    break;
                }
            }

            if ($restrictionType === 'ptypes' || $restrictionType === 'ptype') {
                $validPTypes = array_map('trim', explode(',', $restriction));
                foreach ($validPTypes as $pType) {
                    if (array_search($pType, $userPTypes, true) !== false) {
                        $canView = true;
                        break 2;
                    }
                }
            }
        }

        return $canView;
    }

    protected function resolveViewingRestrictions(): array
    {
        $raw = $this->mediaObject->pika_access_limits ?? null;
        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            $rawArray = preg_split('/[\r\n;]+/', $raw);
        }

        if (!is_array($rawArray)) {
            return [$rawArray];
        }
        return $rawArray;
    }

    protected function parseRestriction($restriction)
    {
        // has paramaters
        if (strstr($restriction, ':')) {
            $pieces = explode(':', $restriction);
            $k = trim($pieces[0]);
            // has multipule parameters
            if (strstr($pieces[1], ',')) {
                $subs = explode(',', $pieces[1]);
                foreach ($subs as $key => $val) {
                    $subs[$key] = trim($val);
                }
                // has single parameter
            } else {
                $v = trim($pieces[1]);
                $restrictions[$k] = [$v];
                return $restrictions;
            }
            $restrictions[$k] = $subs;
            return $restrictions;
        }
        $k = trim($restriction);
        $restrictions[$k] = 1;
        return $restrictions;
    }
}
