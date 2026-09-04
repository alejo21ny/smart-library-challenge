<?php

namespace App\AI\Intent;

/**
 * Deterministic "which tool" classifier. Runs first, before any AI provider
 * call, on every query — this is what keeps tool selection reliable and
 * free with AI_PROVIDER=null, and it's also what an AI provider's own
 * wording enrichment sits on top of, never replaces. See ARCHITECTURE.md.
 */
class ActionClassifier
{
    private const MY_LOANS_PATTERNS = [
        '/\bmy\s+(current\s+)?(loans?|books?)\b/i',
        '/\bwhat\s+(books?|do i)\s+.*\b(borrow\w*|have (out|checked out))\b/i',
        '/\bwhat\s+do\s+i\s+(currently\s+)?have\s+(borrowed|out|checked out)\b/i',
        '/\bi\s+(currently\s+)?have\s+borrowed\b/i',
        '/\bam\s+i\s+borrowing\b/i',
    ];

    private const LIBRARY_SUMMARY_PATTERNS = [
        '/\b(circulation|library)\s+(summary|overview|snapshot)\b/i',
        '/\bquick\s+summary\b/i',
        '/\bhow many (books?|are overdue|are borrowed|are (currently\s+)?out)\b/i',
        '/\boverdue\s+(count|list|report)\b/i',
    ];

    private const CHECK_AVAILABILITY_PATTERNS = [
        '/\bdo (you|we) have\b/i',
        '/^\s*is\b.+\bavailable\b/i',
        '/\bavailable\s*\?\s*$/i',
    ];

    public static function classify(string $query, bool $isStaff): AssistantAction
    {
        if (self::matchesAny($query, self::MY_LOANS_PATTERNS)) {
            return AssistantAction::GetMyLoans;
        }

        if ($isStaff && self::matchesAny($query, self::LIBRARY_SUMMARY_PATTERNS)) {
            return AssistantAction::GetLibrarySummary;
        }

        if (self::matchesAny($query, self::CHECK_AVAILABILITY_PATTERNS)) {
            return AssistantAction::CheckAvailability;
        }

        return AssistantAction::SearchCatalog;
    }

    /**
     * Strips the question scaffolding ("do you have", "is ... available",
     * trailing "?") from a check_availability query, leaving just the
     * book title/author guess to search for.
     */
    public static function extractAvailabilitySubject(string $query): string
    {
        $subject = preg_replace('/\bdo (you|we) have\b/i', '', $query) ?? $query;
        $subject = preg_replace('/^\s*is\b/i', '', $subject) ?? $subject;
        $subject = preg_replace('/\bavailable\b/i', '', $subject) ?? $subject;
        $subject = preg_replace('/[?.!]+/', '', $subject) ?? $subject;

        return trim($subject);
    }

    /**
     * @param  string[]  $patterns
     */
    private static function matchesAny(string $query, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query) === 1) {
                return true;
            }
        }

        return false;
    }
}
