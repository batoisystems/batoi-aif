<?php

declare(strict_types=1);

namespace App\Controller;

use Batoi\Aif\Api\AifApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AifController
{
    public function __construct(private AifApi $aif)
    {
    }

    #[Route('/aif/infer', name: 'aif_infer', methods: ['POST'])]
    public function infer(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        return new JsonResponse($this->aif->infer(
            payload: is_array($payload) ? $payload : [],
            contextSource: $request,
        ));
    }
}

