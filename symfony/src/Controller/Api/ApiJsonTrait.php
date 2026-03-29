<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

trait ApiJsonTrait
{
    /**
     * @return array<string, mixed>
     */
    protected function parseJsonBody(Request $request): array
    {
        if ($request->getContent() === '') {
            return [];
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Invalid JSON body');
        }

        return \is_array($data) ? $data : [];
    }

    protected function jsonDetail(string $detail, string $code, int $status): JsonResponse
    {
        return new JsonResponse(['detail' => $detail, 'code' => $code], $status);
    }

    protected function uuidString(?Uuid $id): ?string
    {
        return $id === null ? null : (string) $id;
    }

    protected function requireUuid(string $value, string $label): Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s', $label));
        }
    }

    /**
     * @param array<string, mixed> $headers
     */
    protected function jsonData(array $data, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}
