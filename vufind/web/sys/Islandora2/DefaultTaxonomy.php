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

require_once ROOT_DIR . '/sys/Islandora2/I2Taxonomy.php';

/**
 * Fallback taxonomy term object used when no specific vocabulary class matches.
 *
 * supports() always returns false so this class is never selected by the factory's
 * resolveClass() loop — it is only instantiated directly as the 'default' registry entry.
 */
class DefaultTaxonomy extends I2Taxonomy
{
    /**
     * Always returns false — this class is only instantiated directly as the fallback.
     *
     * @inheritDoc
     */
    public static function supports(array $term): bool
    {
        return false;
    }
}
