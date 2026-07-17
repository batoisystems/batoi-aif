<?php

declare(strict_types=1);

namespace Batoi\Aif\Laravel;

use Batoi\Aif\Api\AifApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AifController
{
    public function __construct(private AifApi $aif)
    {
    }

    public function infer(Request $request): JsonResponse
    {
        return response()->json($this->aif->infer(
            payload: $request->only(['input', 'prompt_code', 'prompt_version', 'provider', 'model', 'variables', 'metadata']),
            contextSource: $request,
        ));
    }
}
