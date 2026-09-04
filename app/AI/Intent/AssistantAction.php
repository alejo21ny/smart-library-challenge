<?php

namespace App\AI\Intent;

/**
 * Which read-only tool the Assistant is answering with. Always chosen
 * deterministically (see ActionClassifier) so tool selection works
 * identically with or without an AI provider configured — the AI, when
 * present, only enriches wording within whichever action was already
 * chosen server-side. See ARCHITECTURE.md "Assistant tool architecture".
 */
enum AssistantAction: string
{
    case SearchCatalog = 'search_catalog';
    case CheckAvailability = 'check_availability';
    case GetMyLoans = 'get_my_loans';
    case GetLibrarySummary = 'get_library_summary';
}
