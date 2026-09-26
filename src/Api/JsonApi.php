<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Request/response helpers shared by the API controllers.
 */
trait JsonApi
{
    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        try {
            $data = json_decode($request->getContent() ?: '{}', true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('The request body is not valid JSON.');
        }
        if (!\is_array($data)) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        return $data;
    }

    /** @param array<string, string> $errors field => message */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, string> */
    private function violations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath() ?: '_'] ??= $violation->getMessage();
        }

        return $errors;
    }

    /** Accepts numbers or numeric strings; "" and null mean "no value". */
    private function decimalOrNull(mixed $value, int $scale): string|false|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            return false;
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
