<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\HelpOffer;
use App\Models\Notification;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HelpMonitoringAnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::HELP_MATCH, PermissionAction::VIEW)), 403);

        $from = Carbon::parse((string) $request->query('from', now()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse((string) $request->query('to', now()->toDateString()))->endOfDay();
        $offers = HelpOffer::query()->whereBetween('created_at', [$from, $to]);
        $totalOffers = (clone $offers)->count();
        $statusRows = (clone $offers)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get();
        $statusCounts = $statusRows->pluck('total', 'status')->map(fn ($value) => (int) $value)->all();
        $completed = (int) ($statusCounts['completed'] ?? 0);
        $stale = HelpOffer::query()
            ->whereIn('status', ['pending', 'accepted', 'contacting'])
            ->where('updated_at', '<=', now()->subHours(24))
            ->count();

        $completedRows = (clone $offers)->whereNotNull('completed_at')->get(['created_at', 'completed_at']);
        $averageCompletionHours = $completedRows->isEmpty()
            ? 0
            : round((float) $completedRows->avg(fn (HelpOffer $offer) => $offer->created_at->diffInMinutes($offer->completed_at) / 60), 2);

        $notificationBase = Notification::query()
            ->whereNotNull('recipient_id')
            ->whereBetween('sent_at', [$from, $to])
            ->where(function ($query): void {
                $query->where('category', 'help')->orWhere('event_type', 'like', 'help_offer.%');
            });
        $sentNotifications = (clone $notificationBase)->count();
        $readNotifications = (clone $notificationBase)->whereNotNull('read_at')->count();
        $notificationBreakdown = (clone $notificationBase)
            ->select('event_type', DB::raw('COUNT(*) as sent'), DB::raw('SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count'))
            ->groupBy('event_type')
            ->orderByDesc('sent')
            ->get()
            ->map(fn ($row) => [
                'eventType' => $row->event_type ?: 'unknown',
                'sent' => (int) $row->sent,
                'read' => (int) $row->read_count,
                'readRate' => (int) $row->sent > 0 ? round(((int) $row->read_count / (int) $row->sent) * 100, 2) : 0,
            ])->values();

        $offerSeries = (clone $offers)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->pluck('total', 'day');
        $notificationSeries = (clone $notificationBase)
            ->selectRaw('DATE(sent_at) as day, COUNT(*) as sent, SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count')
            ->groupBy(DB::raw('DATE(sent_at)'))
            ->orderBy('day')
            ->get()
            ->keyBy('day');
        $days = collect($offerSeries->keys())->merge($notificationSeries->keys())->unique()->sort()->values();
        $series = $days->map(fn ($day) => [
            'day' => $day,
            'offers' => (int) ($offerSeries[$day] ?? 0),
            'notificationsSent' => (int) ($notificationSeries->get($day)?->sent ?? 0),
            'notificationsRead' => (int) ($notificationSeries->get($day)?->read_count ?? 0),
        ]);

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'totalOffers' => $totalOffers,
                'completedOffers' => $completed,
                'completionRate' => $totalOffers > 0 ? round(($completed / $totalOffers) * 100, 2) : 0,
                'staleOffers' => $stale,
                'averageCompletionHours' => $averageCompletionHours,
                'notificationsSent' => $sentNotifications,
                'notificationsRead' => $readNotifications,
                'notificationReadRate' => $sentNotifications > 0 ? round(($readNotifications / $sentNotifications) * 100, 2) : 0,
            ],
            'statusCounts' => $statusCounts,
            'notificationBreakdown' => $notificationBreakdown,
            'series' => $series,
        ]]);
    }
}
