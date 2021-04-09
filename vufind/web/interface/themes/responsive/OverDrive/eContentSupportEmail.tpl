From: {$name}
Email: {$email}
Home Library: {$homeLibrary}

{if $overDriveId}OverDrive Id : {$overDriveId}{/if}
Book Title/Author: {$bookAuthor}
Device: {$deviceName}
Format: {$format}
Operating System: {$operatingSystem}

Problem Description:
{$problem}

{if !empty($overDriveErrorMessages)}
Error information reported by the OverDrive content server:

{$overDriveErrorMessages}
{/if}