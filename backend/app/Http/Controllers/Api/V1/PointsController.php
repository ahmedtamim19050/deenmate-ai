<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PointTransaction;
use App\Models\UserPointBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $b = UserPointBalance::query()->find($request->user()->id);

        return response()->json(['data' => [
            'balance' => (int) ($b->balance ?? 0),
            'lifetime_earned' => (int) ($b->lifetime_earned ?? 0),
            'note' => 'Deen Points are personal motivational app points. They do not measure faith or reward from Allah.',
        ]]);
    }

    public function history(Request $request): JsonResponse
    {
        $page = PointTransaction::query()
            ->with('activityLog.type')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (PointTransaction $t) => [
                'id' => $t->uuid,
                'type' => $t->type,
                'points' => $t->points,
                'balance_after' => $t->balance_after,
                'activity' => $t->activityLog?->type?->name,
                'rule_version' => $t->rule_version,
                'reason' => $t->reason,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:365']]);
        $days = (int) ($data['days'] ?? 30);
        $since = now()->subDays($days);

        $byCategory = PointTransaction::query()
            ->join('activity_logs', 'activity_logs.id', '=', 'point_transactions.activity_log_id')
            ->join('activity_types', 'activity_types.id', '=', 'activity_logs.activity_type_id')
            ->where('point_transactions.user_id', $request->user()->id)
            ->where('point_transactions.created_at', '>=', $since)
            ->selectRaw('activity_types.category as category, SUM(point_transactions.points) as points')
            ->groupBy('activity_types.category')
            ->pluck('points', 'category')
            ->map(fn ($p) => (int) $p);

        return response()->json(['data' => ['days' => $days, 'total' => $byCategory->sum(), 'by_category' => $byCategory]]);
    }
}
