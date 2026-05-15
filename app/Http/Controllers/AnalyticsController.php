<?php

namespace App\Http\Controllers;

use App\Models\Founders;
use App\Models\PitchDeck;
use App\Models\PitchDeckDownload;
use App\Models\PitchDeckView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1. Summary Statistics
        $summary = [
            'total_pitch_decks' => PitchDeck::count(),
            'total_views' => PitchDeckView::count(),
            'total_downloads' => PitchDeckDownload::count(),
            'total_users' => User::count(),
            'total_founders' => Founders::count(),
        ];

        // 2. Top Viewed Pitch Decks
        $topViewed = PitchDeck::select('id', 'title')
            ->withCount('views')
            ->orderBy('views_count', 'desc')
            ->limit(5)
            ->get();

        // 3. Most Downloaded Pitch Decks
        $topDownloaded = PitchDeck::select('id', 'title')
            ->withCount('downloads')
            ->orderBy('downloads_count', 'desc')
            ->limit(5)
            ->get();

        // 4. Sector Distribution
        $sectorDistribution = Founders::select('sector', DB::raw('count(*) as count'))
            ->whereNotNull('sector')
            ->groupBy('sector')
            ->orderBy('count', 'desc')
            ->get();

        // 5. Operational Stage Distribution
        $stageDistribution = Founders::select('operational_stage', DB::raw('count(*) as count'))
            ->whereNotNull('operational_stage')
            ->groupBy('operational_stage')
            ->orderBy('count', 'desc')
            ->get();

        // 6. Activity Timeline (Last 30 days)
        $days = 30;
        $viewsTimeline = PitchDeckView::select(
                DB::raw('DATE(viewed_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('viewed_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $downloadsTimeline = PitchDeckDownload::select(
                DB::raw('DATE(downloaded_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('downloaded_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'summary' => $summary,
            'top_viewed' => $topViewed,
            'top_downloaded' => $topDownloaded,
            'sector_distribution' => $sectorDistribution,
            'stage_distribution' => $stageDistribution,
            'timeline' => [
                'views' => $viewsTimeline,
                'downloads' => $downloadsTimeline,
            ],
        ]);
    }
}
