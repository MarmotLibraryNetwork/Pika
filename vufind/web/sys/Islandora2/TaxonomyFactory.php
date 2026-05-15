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

require_once ROOT_DIR . '/sys/Islandora2/TaxonomyObjectInterface.php';
require_once ROOT_DIR . '/sys/Islandora2/I2Taxonomy.php';
require_once ROOT_DIR . '/sys/Islandora2/Request.php';
require_once ROOT_DIR . '/sys/Islandora2/PersonTaxonomy.php';
require_once ROOT_DIR . '/sys/Islandora2/EventTaxonomy.php';
require_once ROOT_DIR . '/sys/Islandora2/GeographicLocationTaxonomy.php';
require_once ROOT_DIR . '/sys/Islandora2/CorporateBodyTaxonomy.php';
require_once ROOT_DIR . '/sys/Islandora2/DefaultTaxonomy.php';

use Pika\Logger;

/**
 * Factory for creating the appropriate I2Taxonomy subclass from a term ID or raw term array.
 *
 * Uses the same registry pattern as I2ObjectFactory.
 */
class TaxonomyFactory
{
    /** @var array<string, class-string<TaxonomyObjectInterface>> */
    private static array $registry = [];

    private static bool $bootstrapped = false;

    private Logger $logger;

    /**
     * @param Logger|null $logger Optional logger override (useful in tests).
     */
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger(__CLASS__);
        self::bootstrap();
    }

    /**
     * Register built-in vocabulary types. Runs once per process.
     */
    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::registerType('person',                PersonTaxonomy::class);
        self::registerType('corporate_body',        CorporateBodyTaxonomy::class);
        self::registerType('geo_location',          GeographicLocationTaxonomy::class);
        self::registerType('event',                 EventTaxonomy::class);
        self::registerType('default',               DefaultTaxonomy::class);

        self::$bootstrapped = true;
    }

    /**
     * Fetch a taxonomy term by ID and return the appropriate object.
     *
     * @param int $tid
     * @return TaxonomyObjectInterface|null
     */
    public function fromTid(int $tid): ?TaxonomyObjectInterface
    {
        if ($tid <= 0) {
            $this->logger->warning('TaxonomyFactory::fromTid called with invalid tid.', ['tid' => $tid]);
            return null;
        }

        $request = new Request();
        $term    = $request->fetch('taxonomy', $tid);

        if ($term === null) {
            $this->logger->warning('TaxonomyFactory: failed to fetch term.', ['tid' => $tid]);
            return null;
        }

        return $this->fromTerm($term);
    }

    /**
     * Create the appropriate taxonomy object from a raw term array.
     *
     * @param array $term
     * @return TaxonomyObjectInterface|null
     */
    public function fromTerm(array $term): ?TaxonomyObjectInterface
    {
        $class = $this->resolveClass($term);

        if ($class === null) {
            $this->logger->notice('TaxonomyFactory: no matching class for vocabulary, using default.', [
                'vid' => $term['vid'] ?? 'unknown',
            ]);
            $class = self::$registry['default'] ?? DefaultTaxonomy::class;
        }

        return new $class($term);
    }

    /**
     * Register a vocabulary type in the factory.
     *
     * @param string                               $key   Vocabulary machine name or 'default'.
     * @param class-string<TaxonomyObjectInterface> $class
     * @throws \InvalidArgumentException
     */
    public static function registerType(string $key, string $class): void
    {
        if (!is_subclass_of($class, TaxonomyObjectInterface::class)) {
            throw new \InvalidArgumentException(
                "Class {$class} does not implement TaxonomyObjectInterface."
            );
        }
        self::$registry[$key] = $class;
    }

    /**
     * Remove a vocabulary type from the registry.
     *
     * @param string $key
     */
    public static function unregisterType(string $key): void
    {
        unset(self::$registry[$key]);
    }

    /**
     * Reset the registry and bootstrap flag (for testing).
     */
    public static function resetBootstrap(): void
    {
        self::$registry     = [];
        self::$bootstrapped = false;
    }

    /**
     * Find the first registered class whose supports() method accepts the term.
     *
     * @param array $term
     * @return class-string<TaxonomyObjectInterface>|null
     */
    private function resolveClass(array $term): ?string
    {
        foreach (self::$registry as $key => $class) {
            if ($key === 'default') {
                continue;
            }
            if ($class::supports($term)) {
                return $class;
            }
        }

        return null;
    }
}
