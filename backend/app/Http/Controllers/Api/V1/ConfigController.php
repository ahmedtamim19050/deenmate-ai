<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppSetting;
use App\Services\AI\Contracts\LlmProvider;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /** Only settings explicitly marked public are ever served here. */
    public function show(LlmProvider $provider): JsonResponse
    {
        return response()->json(['data' => [
            'ai_configured' => $provider->isConfigured(),
            'settings' => AppSetting::query()->where('is_public', true)->pluck('value', 'key'),
        ]]);
    }
}
