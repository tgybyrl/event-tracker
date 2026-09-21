<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the `events` table that the Go service writes to.
 *
 * Eloquent normally generates the table it talks to, so it assumes an
 * auto-incrementing integer `id` plus `created_at`/`updated_at`. This table
 * has none of that, so every one of those assumptions is overridden below.
 * `db/schema.sql` stays the single source of truth — no migration here.
 */
class Event extends Model
{
    protected $table = 'events';

    protected $primaryKey = 'event_id';

    /** UUIDs come from the client, not from AUTO_INCREMENT. */
    public $incrementing = false;

    /** CHAR(36), so the key is a string and must not be cast to int. */
    protected $keyType = 'string';

    /** The table has no created_at / updated_at columns. */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_payload' => 'array',
            'event_timestamp' => 'datetime',
        ];
    }
}
