<?php

namespace App\Http\Controllers;

use App\Models\Founders;
use App\Models\PitchDeck;
use App\Models\AdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
//use Debugbar;

class FounderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
     $startTime = microtime(true);
    Log::info('=== FounderController::index() Called ===', [
        'query' => $request->query(),
    ]);

    // Create a unique cache key based on ALL request parameters
    // This ensures different filters get different cached results
    $cacheKey = 'founders:' . md5(json_encode($request->query()));
    
    // Set cache duration (in seconds) - adjust based on your needs
    // 1800 seconds = 30 minutes
    // $cacheDuration = 1800;
    
    // // Use Cache::remember to either retrieve cached data or store new data
    // $founders = Cache::remember($cacheKey, $cacheDuration, function () use ($request) {
        
    //     Log::info('Cache MISS - executing database query for founders');

         // Flexible cache: fresh for 5 minutes, stale for 10 more minutes
    // During stale period, old data is served while refreshing in background
    $founders = Cache::flexible($cacheKey, [300, 600], function () use ($request) {
        
        \Log::info('Cache refresh in background - executing query');
        
        $query = Founders::query();

        if ($request->filled('company_name')) {
            $query->where('company_name', 'like', '%' . $request->input('company_name') . '%');
        }

        if ($request->filled('sector')) {
            $query->where('sector', $request->input('sector'));
        }

        if ($request->filled('location')) {
            $query->where('location', $request->input('location'));
        }

        if ($request->filled('operational_stage')) {
            $query->where('operational_stage', $request->input('operational_stage'));
        }

        if ($request->filled('valuation')) {
            $query->where('valuation', $request->input('valuation'));
        }

        if ($request->filled('years_of_establishment')) {
            $query->where('years_of_establishment', $request->input('years_of_establishment'));
        }

        if ($request->filled('min_investment_size')) {
            $query->where('investment_size', '>=', (float) $request->input('min_investment_size'));
        }

        if ($request->filled('max_investment_size')) {
            $query->where('investment_size', '<=', (float) $request->input('max_investment_size'));
        }

        if ($request->filled('investment_size')) {
            $query->where('investment_size', (float) $request->input('investment_size'));
        }

        if ($request->filled('description')) {
            $query->where('description', 'like', '%' . $request->input('description') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('number_of_employees')) {
            $query->where('number_of_employees', $request->input('number_of_employees'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', '%' . $search . '%')
                    ->orWhere('sector', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $sortableFields = [
            'id',
            'company_name',
            'sector',
            'location',
            'operational_stage',
            'valuation',
            'years_of_establishment',
            'investment_size',
            'status',
            'number_of_employees',
            'created_at',
            'updated_at',
        ];

        $sortBy = $request->input('sort_by', 'created_at');
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->get();
    });

    Log::info('FounderController::index() result', [
        'count' => $founders->count(),
        'cache_key' => $cacheKey,
        'cache_hit' => Cache::has($cacheKey) ? 'HIT' : 'MISS'
    ]);

    return response()->json($founders);
}
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'sector' => 'required|string|max:255',
            'location' => 'required|string|in:addis ababa,diredawa,hawassa,bahirdar,gondar,mekele',
            'operational_stage' => 'required|string|in:pre-operational,early-operations,revenue-generating,profitable/cash-flow positive',
            'valuation' => 'required|string|in:pre seed under 1M$,seed 1M$ - 5M$,series A 5M$ - 10M$,series B 10M$ - 50M$,series C 50M$ - 100M$,IPO 100M$+',
            'years_of_establishment' => 'required|integer|min:1900|max:' . date('Y'),
            'investment_size' => 'required|numeric',
            'description' => 'required|string|max:10000',
            'number_of_employees' => 'required|string|in:1-10,11-50,51-200,201-500,501-1000,1001+',
            'pitch_deck_title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,ppt,pptx|max:20480',
        ]);

        $founder = Founders::create([
            'company_name' => $validated['company_name'],
            'sector' => $validated['sector'],
            'location' => $validated['location'],
            'operational_stage' => $validated['operational_stage'],
            'valuation' => $validated['valuation'],
            'years_of_establishment' => $validated['years_of_establishment'],
            'investment_size' => $validated['investment_size'],
            'description' => $validated['description'],
            'number_of_employees' => $validated['number_of_employees'],
        ]);

        $file = $request->file('file');

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'ppt', 'pptx'])) {
            return response()->json([
                'file' => ['Invalid file type. Only PDF, PPT, and PPTX files are allowed.'],
            ], 422);
        }

        $originalName = $file->getClientOriginalName();
        $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

        $filePath = $file->storeAs('pitch_decks', $fileName, 'public');

        if (!$filePath) {
            return response()->json([
                'error' => 'Failed to store file',
            ], 500);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'You must be logged in to upload a pitch deck'
            ], 401);
        }
        $pitchDeck = PitchDeck::create([
            'founder_id' => $founder->id,
            'title' => $validated['pitch_deck_title'],
            'file_path' => $filePath,
            'file_type' => $extension,
            'thumbnail_path' => null,
            'status' => 'draft',
            'uploaded_by' => $user ? $user->id : null,
        ]);

        // try {
        //     if (in_array($extension, ['pdf', 'ppt', 'pptx'])) {
        //         $originalPath = Storage::disk('public')->path($filePath);
        //         $image = Image::make($originalPath . '[0]');
        //         $image->resize(300, 200, function ($constraint) {
        //             $constraint->aspectRatio();
        //             $constraint->upsize();
        //         });
        //         $thumbnailFileName = 'thumbnail_' . $pitchDeck->id . '_' . time() . '.webp';
        //         $thumbnailPath = 'pitch_decks/thumbnails/' . $thumbnailFileName;
        //         Storage::disk('public')->put($thumbnailPath, $image->encode('webp', 85));
        //         $pitchDeck->thumbnail_path = $thumbnailPath;
        //         $pitchDeck->save();
        //     }
        // } catch (\Throwable $e) {
        //     \Log::warning('Failed to generate thumbnail from first page in FounderController@store: ' . $e->getMessage());
        // }

        try {
            AdminActivity::create([
                'admin_user_id' => $user->id,
                'action' => 'create_founder',
                'subject_type' => 'Founder',
                'subject_id' => $founder->id,
                'data' => [
                    'company_name' => $founder->company_name,
                    'sector' => $founder->sector,
                    'location' => $founder->location,
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to log admin activity for founder create: ' . $e->getMessage());
        }
        // Clear cache for the new founder
        //Cache::forget("founder_{$founder->id}");
        Cache::flush();
        return response()->json([
            'message' => 'Founder profile and pitch deck created successfully',
            'founder' => $founder,
            'pitch_deck' => $pitchDeck,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Founders $founder)
    {
        return $founder;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Founders $founder)
    {
        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'sector' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|required|string|in:addis ababa,diredawa,hawassa,bahirdar,gondar,mekele',
            'operational_stage' => 'sometimes|required|string|in:pre-operational,early-operations,revenue-generating,profitable/cash-flow positive',
            'valuation' => 'sometimes|required|string|in:pre seed under 1M$,seed 1M$ - 5M$,series A 5M$ - 10M$,series B 10M$ - 50M$,series C 50M$ - 100M$,IPO 100M$+',
            'years_of_establishment' => 'sometimes|required|integer|min:1900|max:' . date('Y'),
            'investment_size' => 'sometimes|required|numeric',
            'description' => 'sometimes|required|string|max:10000',
            'number_of_employees' => 'sometimes|required|string|in:1-10,11-50,51-200,201-500,501-1000,1001+',
            'status' => 'sometimes|required|string|in:pending,active,funded,archived',
        ]);

        $founder->update($validated);

        try {
            $user = $request->user();
            if ($user) {
                AdminActivity::create([
                    'admin_user_id' => $user->id,
                    'action' => 'update_founder',
                    'subject_type' => 'Founder',
                    'subject_id' => $founder->id,
                    'data' => $validated,
                    'ip_address' => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to log admin activity for founder update: ' . $e->getMessage());
        }

        return response()->json($founder);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Founders $founder)
    {
        $user = request()->user();

        try {
            if ($user) {
                AdminActivity::create([
                    'admin_user_id' => $user->id,
                    'action' => 'delete_founder',
                    'subject_type' => 'Founder',
                    'subject_id' => $founder->id,
                    'data' => [
                        'company_name' => $founder->company_name,
                        'sector' => $founder->sector,
                        'location' => $founder->location,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to log admin activity for founder delete: ' . $e->getMessage());
        }

        $founder->delete();

        return response()->json(null, 204);
    }
    
public function sectorAnalytics()
{
    $cacheKey = 'sector_analytics';
    
    $analytics = Cache::flexible($cacheKey, [3600, 7200], function () {
        return [
            'by_sector' => Founders::select('sector', \DB::raw('count(*) as total'))
                ->groupBy('sector')
                ->orderBy('total', 'desc')
                ->get(),
            'avg_investment_by_sector' => Founders::select('sector', \DB::raw('AVG(investment_size) as avg_investment'))
                ->whereNotNull('investment_size')
                ->groupBy('sector')
                ->get(),
            'operational_stage_distribution' => Founders::select('operational_stage', \DB::raw('count(*) as total'))
                ->groupBy('operational_stage')
                ->get(),
        ];
    });
    
    return response()->json($analytics);
}
}
