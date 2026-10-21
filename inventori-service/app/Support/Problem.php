<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class Problem
{
    public static function make(int $status, string $title, string $detail, string $type = 'about:blank', array $extra = []): JsonResponse
    {
        $body = array_merge([
            'type' => $type === 'about:blank' ? $type : "https://sembako.test/problems/{$type}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => request()->getPathInfo(),
        ], $extra);

        return response()->json($body, $status, ['Content-Type' => 'application/problem+json']);
    }
}