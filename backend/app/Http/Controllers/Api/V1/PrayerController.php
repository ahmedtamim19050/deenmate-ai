<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SalahRecordResource;
use App\Models\PrayerNotificationPreference;
use App\Models\SalahRecord;
use App\Models\UserPrayerSetting;
use App\Models\UserPrayerTemplate;
use App\Services\Prayer\PrayerTemplateDefaults;
use App\Services\Prayer\PrayerTimeService;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrayerController extends Controller
{
    public function methods(): JsonResponse
    {
        return response()->json(['data' => [
            'calculation_methods' => PrayerTimeService::METHODS,
            'asr_methods' => ['standard' => 'Standard (Shafi\'i, Maliki, Hanbali)', 'hanafi' => 'Hanafi'],
            'high_latitude_rules' => array_keys(PrayerTimeService::HIGH_LATITUDE_RULES),
        ]]);
    }

    public function today(Request $request, PrayerTimeService $times): JsonResponse
    {
        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $setting = UserPrayerSetting::query()->where('user_id', $request->user()->id)->first();

        if ($setting === null || $setting->latitude === null || $setting->longitude === null) {
            return response()->json(['data' => ['configured' => false]]);
        }

        $date = $data['date'] ?? CarbonImmutable::now($setting->timezone)->toDateString();
        $schedule = $times->scheduleFor($request->user(), $setting, $date);
        $records = SalahRecord::query()->with('items')
            ->where('user_id', $request->user()->id)
            ->whereDate('prayer_date', $date)
            ->where('status', 'active')
            ->get();

        $iso = fn ($value) => CarbonImmutable::parse($value->format('Y-m-d H:i:s'), 'UTC')->toIso8601ZuluString();

        return response()->json(['data' => [
            'configured' => true,
            'date' => $date,
            'timezone' => $setting->timezone,
            'method' => $setting->calculation_method,
            'times' => [
                'fajr' => $iso($schedule->fajr_at),
                'sunrise' => $iso($schedule->sunrise_at),
                'dhuhr' => $iso($schedule->dhuhr_at),
                'asr' => $iso($schedule->asr_at),
                'maghrib' => $iso($schedule->maghrib_at),
                'isha' => $iso($schedule->isha_at),
            ],
            'records' => SalahRecordResource::collection($records)->resolve(),
        ]]);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->settingsPayload($request)]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $offset = ['sometimes', 'integer', 'min:-120', 'max:120'];
        $data = $request->validate([
            'location_mode' => ['sometimes', 'in:gps,city,manual'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'calculation_method' => ['sometimes', Rule::in(array_keys(PrayerTimeService::METHODS))],
            'asr_method' => ['sometimes', 'in:standard,hanafi'],
            'high_latitude_rule' => ['sometimes', Rule::in(array_keys(PrayerTimeService::HIGH_LATITUDE_RULES))],
            'fajr_offset' => $offset, 'sunrise_offset' => $offset, 'dhuhr_offset' => $offset,
            'asr_offset' => $offset, 'maghrib_offset' => $offset, 'isha_offset' => $offset,
            'notifications' => ['sometimes', 'array'],
            'notifications.*.prayer' => ['required', Rule::in(PrayerTemplateDefaults::PRAYERS)],
            'notifications.*.enabled' => ['sometimes', 'boolean'],
            'notifications.*.offset_minutes' => ['sometimes', 'integer', 'min:-60', 'max:120'],
            'notifications.*.sound' => ['sometimes', 'string', 'max:32'],
            'notifications.*.vibration' => ['sometimes', 'boolean'],
            'notifications.*.private_preview' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->user()->id;
        DB::transaction(function () use ($data, $userId) {
            UserPrayerSetting::query()->updateOrCreate(['user_id' => $userId], collect($data)->except('notifications')->all());
            foreach ($data['notifications'] ?? [] as $row) {
                PrayerNotificationPreference::query()->updateOrCreate(
                    ['user_id' => $userId, 'prayer' => $row['prayer']],
                    collect($row)->except('prayer')->all(),
                );
            }
        });

        return response()->json(['data' => $this->settingsPayload($request)]);
    }

    public function templates(Request $request, PrayerTemplateDefaults $defaults): JsonResponse
    {
        $defaults->ensureFor($request->user());

        return response()->json(['data' => $this->templatesPayload($request)]);
    }

    public function updateTemplates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'templates' => ['required', 'array', 'min:1', 'max:60'],
            'templates.*.prayer' => ['required', Rule::in(PrayerTemplateDefaults::PRAYERS)],
            'templates.*.template_key' => ['required', 'string', 'max:48', 'alpha_dash'],
            'templates.*.label' => ['required', 'string', 'max:60'],
            'templates.*.category' => ['required', Rule::in(PrayerTemplateDefaults::CATEGORIES)],
            'templates.*.rakats' => ['required', 'integer', 'min:1', 'max:20'],
            'templates.*.is_enabled' => ['required', 'boolean'],
        ]);

        $keys = array_map(fn ($t) => $t['prayer'].'|'.$t['template_key'], $data['templates']);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['templates' => 'Each category key must be unique within a prayer.']);
        }

        DB::transaction(function () use ($data, $request) {
            UserPrayerTemplate::query()->where('user_id', $request->user()->id)->delete();
            foreach ($data['templates'] as $i => $row) {
                UserPrayerTemplate::query()->create($row + ['user_id' => $request->user()->id, 'sort_order' => $i]);
            }
        });

        return response()->json(['data' => $this->templatesPayload($request)]);
    }

    public function restoreTemplates(Request $request, PrayerTemplateDefaults $defaults): JsonResponse
    {
        DB::transaction(fn () => $defaults->restore($request->user()));

        return response()->json(['data' => $this->templatesPayload($request)]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'prayer' => ['sometimes', Rule::in(PrayerTemplateDefaults::PRAYERS)],
        ]);

        return SalahRecordResource::collection(
            SalahRecord::query()->with('items')
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->when($data['from'] ?? null, fn ($q, $v) => $q->where('prayer_date', '>=', $v))
                ->when($data['to'] ?? null, fn ($q, $v) => $q->where('prayer_date', '<=', $v))
                ->when($data['prayer'] ?? null, fn ($q, $v) => $q->where('prayer', $v))
                ->orderByDesc('prayer_date')->orderByDesc('confirmed_at')
                ->paginate(30)
        );
    }

    private function settingsPayload(Request $request): array
    {
        $setting = UserPrayerSetting::query()->firstOrCreate(['user_id' => $request->user()->id])->refresh();

        return $setting->makeHidden(['id', 'user_id', 'created_at', 'updated_at'])->toArray() + [
            'notifications' => PrayerNotificationPreference::query()
                ->where('user_id', $request->user()->id)->get()
                ->map->only(['prayer', 'enabled', 'offset_minutes', 'sound', 'vibration', 'private_preview'])->values(),
        ];
    }

    private function templatesPayload(Request $request): array
    {
        return UserPrayerTemplate::query()->where('user_id', $request->user()->id)
            ->orderBy('sort_order')->get()
            ->map->only(['prayer', 'template_key', 'label', 'category', 'rakats', 'is_enabled', 'sort_order'])->values()->all();
    }
}
