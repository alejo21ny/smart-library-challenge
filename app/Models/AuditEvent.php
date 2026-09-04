<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only. Rows here are never updated, only ever created and read. */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->created_at ??= now();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A human-readable one-line summary for the dashboard's "Recent activity"
     * feed, e.g. "Ada Admin borrowed Clean Architecture" instead of a bare
     * event-type label. Built only from this row's own metadata (captured
     * authoritatively at event time — see RecordAuditEvent) plus, for the two
     * event types that didn't capture a display name, a best-effort current
     * lookup with a graceful fallback if the related row is gone. Never
     * invents anything not backed by a real record.
     */
    public function describe(): string
    {
        /** @var User|null $relatedUser */
        $relatedUser = $this->user;
        $actor = $relatedUser !== null ? $relatedUser->name : 'Someone';
        $meta = $this->metadata ?? [];

        return match ($this->event_type) {
            'BOOK_CREATED' => sprintf('%s added "%s" to the catalog', $actor, $meta['title'] ?? 'a book'),
            'BOOK_UPDATED' => sprintf('%s updated "%s"', $actor, $this->currentBookTitle()),
            'BOOK_DELETED' => sprintf('%s removed "%s" from the catalog', $actor, $meta['title'] ?? 'a book'),
            'BOOK_BORROWED' => sprintf('%s borrowed "%s"', $meta['borrower_name'] ?? $actor, $meta['book_title'] ?? 'a book'),
            'BOOK_RETURNED' => sprintf('%s returned "%s"', $meta['borrower_name'] ?? $actor, $meta['book_title'] ?? 'a book'),
            'USER_ROLE_CHANGED' => sprintf(
                '%s changed %s\'s role from %s to %s',
                $actor,
                $this->currentUserName(),
                $meta['old_role'] ?? '?',
                $meta['new_role'] ?? '?',
            ),
            default => $actor.' '.strtolower(str_replace('_', ' ', (string) $this->event_type)),
        };
    }

    private function currentBookTitle(): string
    {
        $book = Book::find($this->subject_id);

        return $book !== null ? $book->title : 'a book';
    }

    private function currentUserName(): string
    {
        $user = User::find($this->subject_id);

        return $user !== null ? $user->name : 'a user';
    }
}
