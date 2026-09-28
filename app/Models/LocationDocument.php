<?php

namespace App\Models;

/**
 * A document attached to a customer location (Objekt-Dokumentation).
 *
 * This is the name the client specification uses. It is backed by the
 * pre-existing `location_files` table so there is exactly one place where
 * location documents live — see {@see LocationFile} for the field contract.
 *
 * Fields are inherited unchanged from {@see LocationFile} and are stored on
 * that single table; this class adds no columns of its own:
 *
 *   - `visibility` (intern | extern, cast to {@see FileVisibilityEnum})
 *       intern → administration only, never leaves the back office
 *       extern → also reachable by Vorarbeiter and Mitarbeiter
 *   - `category`   (cast to {@see FileCategoryEnum})
 *   - `title`      — the human readable name; backfilled from `original_name`
 *
 * Enforcing the intern/extern boundary is NOT this model's job: read access
 * goes through {@see \App\Services\LocationDocumentService::visibleQuery()},
 * which filters by the caller's clearance. Never read `$document` straight
 * from a controller and expose it — that skips the check.
 */
class LocationDocument extends LocationFile
{
    protected $table = 'location_files';
}
