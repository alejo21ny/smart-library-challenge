<?php

namespace App\Http\Controllers;

use App\AI\LibraryAssistant;
use App\Http\Requests\AssistantQueryRequest;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    public function query(AssistantQueryRequest $request, LibraryAssistant $assistant): JsonResponse
    {
        $result = $assistant->query($request->validated('query'), $request->user());

        return response()->json([
            'action' => $result['action'],
            'message' => $result['message'],
            'books' => $result['books']->values(),
            'loans' => $result['loans']->values(),
            'summary' => $result['summary'],
            'intent' => $result['intent']?->toArray(),
            'whyMatched' => $result['whyMatched'],
            'suggestion' => $result['suggestion'],
            'usedFuzzy' => $result['usedFuzzy'],
            'degraded' => $result['degraded'],
        ]);
    }
}
