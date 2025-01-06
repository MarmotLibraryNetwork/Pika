From: {$name}
Email: {$email}
Home Library/Interface: {$libraryName}
User Barcode: {$libraryCardNumber}

Book Title/Author: {$title}
Device: {$deviceName}
Format: {$format}
Operating System: {$operatingSystem}

Problem Description:
{$problem}

{if !empty($overDriveErrorMessages)}
	Error information reported by the OverDrive content server:

	{$overDriveErrorMessages}
{/if}