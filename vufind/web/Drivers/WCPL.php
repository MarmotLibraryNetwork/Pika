<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
/*
require_once 'DriverInterface.php';
require_once ROOT_DIR . '/Drivers/HorizonAPI3_23.php';

class WCPL extends HorizonAPI3_23 {

	function translateFineMessageType($code){
		switch ($code){
			case "abs":       return "Automatic Bill Sent";
			case "acr":       return "Address Correction Requested";
			case "adjcr":     return "Adjustment credit, for changed";
			case "adjdbt":    return "Adjustment debit, for changed";
			case "balance":   return "Balancing Entry";
			case "bcbr":      return "Booking Cancelled by Borrower";
			case "bce":       return "Booking Cancelled - Expired";
			case "bcl":       return "Booking Cancelled by Library";
			case "bcsp":      return "Booking Cancelled by Suspension";
			case "bct":       return "Booking Cancelled - Tardy";
			case "bn":        return "Billing Notice";
			case "chgs":      return "Charges Misc. Fees";
			case "cr":        return "Claimed Return";
			case "credit":    return "Credit";
			case "damage":    return "Damaged";
			case "dc":        return "Debt Collection";
			case "dynbhm":    return "Dynix Being Held Mail";
			case "dynbhp":    return "Dynix Being Held Phone";
			case "dynfnl":    return "Dynix Final Overdue Notice";
			case "dynhc":     return "Dynix Hold Cancelled";
			case "dynhexp":   return "Dynix Hold Expired";
			case "dynhns":    return "Dynix Hold Notice Sent";
			case "dynnot1":   return "Dynix First Overdue Notice";
			case "dynnot2":   return "Dynix Second Overdue Notice";
			case "edc":       return "Exempt from Debt Collection";
			case "fdc":       return "Force to Debt Collection";
			case "fee":       return "ILL fees/Postage";
			case "final":     return "Final Overdue Notice";
			case "finalr":    return "Final Recall Notice";
			case "fine":      return "Fine";
			case "hcb":       return "Hold Cancelled by Borrower";
			case "hcl":       return "Hold Cancelled by Library";
			case "hclr":      return "Hold Cancelled & Reinserted in";
			case "he":        return "Hold Expired";
			case "hncko":     return "Hold Notification - Deliver";
			case "hncsa":     return "Hold - from closed stack";
			case "hnmail":    return "Hold Notification - Mail";
			case "hnphone":   return "Hold Notification - Phone";
			case "ill":       return "Interlibrary Loan Notification";
			case "in":        return "Invoice";
			case "infocil":   return "Checkin Location";
			case "infocki":   return "Checkin date";
			case "infocko":   return "Checkout date";
			case "infodue":   return "Due date";
			case "inforen":   return "Renewal date";
			case "l":         return "Lost";
			case "ld":        return "Lost on Dynix";
			case "lf":        return "Found";
			case "LostPro":   return "Lost Processing Fee";
			case "lr":        return "Lost Recall";
			case "msg":       return "Message to Borrower";
			case "nocko":     return "No Checkout";
			case "Note":      return "Comment";
			case "notice1":   return "First Overdue Notice";
			case "notice2":   return "Second Overdue Notice";
			case "notice3":   return "Third Overdue Notice";
			case "noticr1":   return "First Recall Notice";
			case "noticr2":   return "Second Recall Notice";
			case "noticr3":   return "Third Recall Notice";
			case "noticr4":   return "Fourth Recall Notice";
			case "noticr5":   return "Fifth Recall Notice";
			case "nsn":       return "Never Send Notices";
			case "od":        return "Overdue Still Out";
			case "odd":       return "Overdue Still Out on Dynix";
			case "odr":       return "Recalled and Overdue Still Out";
			case "onlin":     return "Online Registration";
			case "payment":   return "Fine Payment";
			case "pcr":       return "Phone Correction Requested";
			case "priv":      return "Privacy - Family permission";
			case "rd":        return "Request Deleted";
			case "re":        return "Request Expired";
			case "recall":    return "Item is recalled before due date";
			case "refund":    return "Refund of Payment";
			case "ri":        return "Reminder Invoice";
			case "rl":        return "Requested item lost";
			case "rn":        return "Reminder Billing Notice";
			case "spec":      return "Special Message";
			case "supv":      return "See Supervisor";
			case "suspend":   return "Suspension until ...";
			case "unpd":      return "Damaged Material Replacement";
			case "waiver":    return "Waiver of Fine";
			default:
				return $code;
		}
	}

	public function translateLocation($locationCode){
		$locationCode = strtoupper($locationCode);
		$locationMap = [
				"ADR" => "Athens Drive Community Library",
				"BKM" => "Bookmobile",
				"CAM" => "Cameron Village Regional Library",
				"CRY" => "Cary Community Library",
				"DUR" => "Duraleigh Road Community Library",
				"ELF" => "Express Library - Fayetteville St.",
				"ERL" => "East Regional Library",
				"EVA" => "Eva H. Perry Regional Library",
				"FUQ" => "Fuquay-Varina Community Library",
				"GRE" => "Green Road Community Library",
				"HSP" => "Holly Springs Community Library",
				"LEE" => "Leesville Community Library",
				"NOR" => "North Regional Library",
				"ORL" => "Olivia Raney Local History Library",
				"RBH" => "Richard B. Harrison Community Library",
				"SER" => "Southeast Regional Library",
				"SGA" => "Southgate Community Library",
				"WAK" => "Wake Forest Community Library",
				"WCPL"=>  "Wake County Public Libraries",
				"WEN" => "Wendell Community Library",
				"WRL" => "West Regional Library",
				"ZEB" => "Zebulon Community Library",
		];
		return $locationMap[$locationCode] ?? 'Unknown';
	}

	public function translateCollection($collectionCode){
		$collectionCode = strtoupper($collectionCode);
		$collectionMap = [
				'AHS000'  => 'Adult Non-Fiction',
				'AHS100'  => 'Adult Non-Fiction',
				'AHS200'  => 'Adult Non-Fiction',
				'AHS300'  => 'Adult Non-Fiction',
				'AHS400'  => 'Adult Non-Fiction',
				'AHS500'  => 'Adult Non-Fiction',
				'AHS600'  => 'Adult Non-Fiction',
				'AHS700'  => 'Adult Non-Fiction',
				'AHS800'  => 'Adult Non-Fiction',
				'AHS900'  => 'Adult Non-fiction',
				'AHSBIO'  => 'Biography',
				'AHSFICT' => 'Fiction',
				'AHSJBIO' => 'Juvenile Biography',
				'AHSJNFI' => 'Childrens Non-Fiction',
				'AHSMYST' => 'Mystery',
				'AHSNCNF' => 'North Carolina Non-Fiction',
				'AHSPER'  => 'Periodicals',
				'AHSREFR' => 'Athens High Reference',
				'AHSSCFI' => 'Science Fiction',
				'AHSSCOL' => 'Story Collection',
				'AHSTRAV' => 'Travel',
				'AHSYAFI' => 'Young Adult Fiction',
				'AHSYANF' => 'Young Adult Non-Fiction',
				'AHSYASC' => 'YA Story Collection',
				'AHSYGRA' => 'YA Graphic Novels',
				'BKMABEA' => 'Audio Books - Children',
				'BKMADUL' => 'Adult collection',
				'BKMBBOO' => 'Board Books',
				'BKMEREA' => 'Beginning Readers',
				'BKMJFIC' => 'Childrens Fiction',
				'BKMJNF'  => 'Childrens Non-fiction',
				'BKMPICT' => 'Picture books',
				'BKMPTRE' => 'Bkm Parent/teacher Resources',
				'CRYCOFF' => 'Cary Children\'s Librarian Office',
				'ERLRHOM' => 'Educator Reference Collection',
				'EVAJEDU' => 'Juvenile Education Resources',
				'FA'      => 'Fast Add',
				'FA-BI'   => 'Fast Add',
				'FA-I'    => 'Fast Add',
				'FLIPVID' => 'Employees only',
				'ILLS'    => 'Ill Items',
				'LAPTOP'  => 'Employees only',
				'LCDPROJ' => 'Employees only',
				'NEWAFIC' => 'New Fiction',
				'NEWANFI' => 'New Nonfiction',
				'NEWBIOG' => 'New Nonfiction',
				'NEWBUSI' => 'New Nonfiction',
				'NEWCARE' => 'New Nonfiction',
				'NEWHORR' => 'New Fiction',
				'NEWINSP' => 'New Fiction',
				'NEWMYST' => 'New Fiction',
				'NEWPARE' => 'New Nonfiction',
				'NEWROMA' => 'New Fiction',
				'NEWSFIC' => 'New Fiction',
				'ORDER'   => 'Item is on order',
				'ORLANF'  => 'Closed Stacks',
				'ORLATLA' => 'Reading Room Atlas Case',
				'ORLAUDI' => 'Reading Room - Circulating',
				'ORLBHX'  => 'Reading Room',
				'ORLBHXS' => 'Closed Stacks',
				'ORLBIOG' => 'Closed Stacks',
				'ORLCENM' => 'Microforms Room',
				'ORLCENP' => 'Reading Room',
				'ORLCIRC' => 'Circulating Collection',
				'ORLCIVW' => 'Closed Stacks',
				'ORLCWRF' => 'Reading Room',
				'ORLDIR'  => 'Closed Stacks',
				'ORLFAMS' => 'Closed Stacks',
				'ORLFICH' => 'Microforms Room',
				'ORLFILM' => 'Microforms Room',
				'ORLGENC' => 'Main Desk',
				'ORLGENE' => 'Reading Room',
				'ORLGENS' => 'Closed Stacks',
				'ORLGOVM' => 'Microforms Room',
				'ORLGOVP' => 'Closed Stacks',
				'ORLGREF' => 'Reading Room',
				'ORLMAP'  => 'Map Case',
				'ORLMSS'  => 'Closed Stacks',
				'ORLNCFI' => 'Closed Stacks',
				'ORLNPAP' => 'Microforms Room',
				'ORLPERI' => 'Reading Room',
				'ORLPRO'  => 'Closed Stacks',
				'ORLSERI' => 'Closed Stacks',
				'ORLVALT' => 'Orl Rare Book Vault',
				'ORLVFIL' => 'Closed Stacks',
				'POPJRAD' => 'Children\'s Readers\' Advisory',
				'POPYARA' => 'Young Adult Readers\' Advisory',
				'RBHLANF' => 'Lee Non-fict - Does Not Circ',
				'RBHLBIO' => 'Lee Biography - Does Not Circulate',
				'RBHLEAS' => 'Lee Easy Bk - Does Not Circ.',
				'RBHLFIC' => 'Lee Fiction - Does Not Circ.',
				'RBHLJBI' => 'Lee Juv Biog - Does Not Circ.',
				'RBHLJFI' => 'Lee Juv Fict - Does Not Circ.',
				'RBHLJNF' => 'Lee Juv Nf - Does Not Circ',
				'RBHLRBR' => 'Rare Book Room',
				'SYSABAD' => 'Audio Books - Adult Fiction',
				'SYSABAN' => 'Audio Books - Adult Nonfiction',
				'SYSABDN' => 'Audio Books - Downloadable',
				'SYSABEA' => 'Audio Books - Children',
				'SYSABJV' => 'Audio Books - Juvenile',
				'SYSABYA' => 'Audio Books - Young Adult',
				'SYSAFIC' => 'Adult Fiction',
				'SYSANFI' => 'Adult Non-fiction',
				'SYSATLA' => 'Atlas Stand',
				'SYSBBOO' => 'Board Books',
				'SYSBCKT' => 'Book Club Kit',
				'SYSBIOG' => 'Biography',
				'SYSBKNT' => 'Book Notes',
				'SYSBSRF' => 'Business Reference',
				'SYSBUSI' => 'Business',
				'SYSCARE' => 'Careers',
				'SYSCCRF' => 'College/Career Reference',
				'SYSCFLC' => 'Children\'s Foreign Language Collection',
				'SYSCOLC' => 'College/Career',
				'SYSCOMP' => 'Computers',
				'SYSCONR' => 'Consumer Reference Table',
				'SYSEASY' => 'Picture Books',
				'SYSEBKS' => 'eBooks',
				'SYSEDUC' => 'Educator\'s Resource Collection',
				'SYSEHOL' => 'Easy Holiday',
				'SYSEKIT' => 'Easy Book Club Kit',
				'SYSEREA' => 'Beginning Readers',
				'SYSFLCO' => 'Foreign Language Collection',
				'SYSGRAF' => 'Graphic Novels',
				'SYSINSP' => 'Inspirational Fiction',
				'SYSJBIO' => 'Childrens Biography',
				'SYSJFIC' => 'Childrens Fiction',
				'SYSJGRA' => 'Childrens Graphic Novels',
				'SYSJKIT' => 'Childrens Book Club Kit',
				'SYSJMAG' => 'Juvenile Magazines',
				'SYSJNFI' => 'Childrens Non-fiction',
				'SYSJREF' => 'Childrens Reference',
				'SYSJSPA' => 'Childrens Spanish Materials',
				'SYSLANG' => 'Language Instruction',
				'SYSLARP' => 'Large Print',
				'SYSLAWG' => 'Legal Reference Guides',
				'SYSLPNF' => 'Large Print Non Fiction',
				'SYSMDRF' => 'Medical Reference Table',
				'SYSMYST' => 'Mystery',
				'SYSNCRF' => 'Nc Reference',
				'SYSPARE' => 'Parenting',
				'SYSPERI' => 'Magazines',
				'SYSPROF' => 'Professional Collection',
				'SYSRADC' => 'Reader\'s Advisory Collection',
				'SYSRDSK' => 'Ask at Reference Desk',
				'SYSREFR' => 'Reference Section',
				'SYSROMA' => 'Romance',
				'SYSSFIC' => 'Science Fiction/Fantasy/Horror',
				'SYSSPAN' => 'Spanish Language Materials',
				'SYSTKIT' => 'Childrens Travel Kit',
				'SYSTRAV' => 'Travel',
				'SYSYAFI' => 'Young Adult',
				'SYSYANF' => 'Young Adult Non Fiction',
				'SYSYGRA' => 'YA Graphic Novels',
				'UNK'     => 'Unknown collection for item creation',
				'ZEBGENE' => 'Genealogy',
		];
		return $collectionMap[$collectionCode] ?? "Unknown $collectionCode";
	}

	public function translateStatus($statusCode){
		$statusCode = strtolower($statusCode);
		$statusMap = [
			"a"      => "Archived",
			"b"      => "Bindery",
			"c"      => "Credited as Returned",
			"csa"    => "Closed Stack",
			"dc"     => "Display",
			"dmg"    => "Damaged",
			"e"      => "Item hold expired",
			"ex"     => "Exception",
			"fd"     => "Featured Display",
			"fone"   => "Phone pickup",
			"h"      => "Item being held",
			"i"      => "Checked In",
			"ill"    => "ILL - Lending",
			"int"    => "Internet",
			"l"      => "Long Overdue",
			"lr"     => "Lost Recall",
			"m"      => "Item missing",
			"me"     => "Mending",
			"mi"     => "Missing Inventory",
			"n"      => "In Processing",
			"o"      => "Checked out",
			"os"     => "On Shelf",
			"r"      => "On Order",
			"rb"     => "Reserve Bookroom",
			"recall" => "Recall",
			"ref"    => "Does Not Circulate",
			"rs"     => "On Reserve Shelf",
			"rw"     => "Reserve withdrawal",
			"s"      => "Shelving Cart",
			"shaw"   => "Shaw University",
			"st"     => "Storage",
			"t"      => "In Cataloging",
			"tc"     => "Transit Recall",
			"th"     => "Transit Request",
			"tr"     => "Transit",
			"trace"  => "No Longer Avail.",
			"ufa"    => "user fast added item",
			"weed"   => "Items for deletion",
		];
		return $statusMap[$statusCode] ?? 'Unknown (' . $statusCode . ')';
	}

	function selfRegister(){
		//Start at My Account Page
		$curlUrl    = $this->hipUrl . "/ipac20/ipac.jsp?profile={$this->selfRegProfile}&menu=account";
		$curlResult = $this->_curlGetPage($curlUrl);

		//Extract the session id from the requestcopy javascript on the page
		if (preg_match('/\\?session=(.*?)&/s', $curlResult, $matches)) {
			$sessionId = $matches[1];
		} else {
			PEAR_Singleton::raiseError('Could not load session information from page.');
		}

		//Login by posting username and password
		$postData = [
      'aspect'         => 'overview',
      'button'         => 'New User',
      'login_prompt'   => 'true',
      'menu'           => 'account',
			'newuser_prompt' => 'true',
      'profile'        => $this->selfRegProfile,
      'ri'             => '',
      'sec1'           => '',
      'sec2'           => '',
      'session'        => $sessionId,
		];

		$curlUrl    = $this->hipUrl . "/ipac20/ipac.jsp";
		$curlResult = $this->_curlPostPage($curlUrl, $postData);

		$firstName     = strip_tags($_REQUEST['firstname']);
		$lastName      = strip_tags($_REQUEST['lastname']);
		$streetAddress = strip_tags($_REQUEST['address1']);
		$apartment     = strip_tags($_REQUEST['address2']);
		$citySt        = strip_tags($_REQUEST['city_st']);
		$zip           = strip_tags($_REQUEST['postal_code']);
		$email         = strip_tags($_REQUEST['email_address']);
		$sendNoticeBy  = strip_tags($_REQUEST['send_notice_by']);
		$pin           = strip_tags($_REQUEST['pin']);
		$confirmPin    = strip_tags($_REQUEST['pin1']);
		$phone         = strip_tags($_REQUEST['phone_no']);

		//Register the patron
		$postData = [
      'address1'       => $streetAddress,
		  'address2'       => $apartment,
			'aspect'         => 'basic',
			'pin#'           => $pin,
			'button'         => 'I accept',
			'city_st'        => $citySt,
			'confirmpin#'    => $confirmPin,
			'email_address'  => $email,
			'firstname'      => $firstName,
			'ipp'            => 20,
			'lastname'       => $lastName,
			'menu'           => 'account',
			'newuser_info'   => 'true',
			'npp'            => 30,
			'postal_code'    => $zip,
			'phone_no'       => $phone,
			'profile'        => $this->selfRegProfile,
			'ri'             => '',
			'send_notice_by' => $sendNoticeBy,
			'session'        => $sessionId,
			'spp'            => 20
		];

		$curlResult = $this->_curlPostPage($curlUrl, $postData);

		//Get the temporary barcode from the page
		if (preg_match('/Here is your temporary barcode\\. Use it for future authentication:&nbsp;([\\d-]+)/s', $curlResult, $regs)) {
			$tempBarcode = $regs[1];
			$tempBarcode = '22046' . $tempBarcode; //Append the library prefix to the card number
			return [
				'barcode' => $tempBarcode,
				'success' => true
			];
		}

		return [
		  'barcode' => null,
		  'success' => false
		];
	}

}
*/