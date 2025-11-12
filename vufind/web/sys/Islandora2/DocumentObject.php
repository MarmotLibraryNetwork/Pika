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

namespace Islandora2;

require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';

class DocumentObject extends I2Object
{
    private const MIME_PREFIXES = [
        'application/msword',
        'application/vnd',
        'text/',
    ];

    public static function supports(array $node): bool
    {
        if (self::bundleMatches($node, ['document', 'text'])) {
            return true;
        }

        foreach (self::MIME_PREFIXES as $prefix) {
            if (self::mimeStartsWith($node, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function getMediaType(): string
    {
        return 'document';
    }
}
