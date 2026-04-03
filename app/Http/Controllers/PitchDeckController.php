<?php

namespace App\Http\Controllers;

use App\Models\PitchDeck;
use App\Models\PitchDeckDownload;
use App\Models\AdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Cache;

class PitchDeckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  public function index(Request $request)
{
    $pitchDecks = PitchDeck::with('founder')
        ->when($request->filled('sector'), function ($query) use ($request) {
            $query->whereHas('founder', function ($q) use ($request) {
                $q->where('sector', $request->sector);
            });
        })
        ->orderBy($request->input('sort_by', 'created_at'), 
                 $request->input('sort_direction', 'desc'))
        ->get();
    
    return response()->json($pitchDecks);
}
    // public function index()
    // {
    //     $pitchDecks = PitchDeck::with('founder')
    //         ->get();
    //     return response()->json($pitchDecks);
    // }
    
   


    /**
     * Store a newly created resource in storage.
     * If a pitch deck already exists for the given founder_id,
     * replace the existing file and metadata instead of creating a new record.
     */
  public function store(Request $request)
{
    try {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'founder_id' => 'required|exists:founders,id',
            'file' => 'required|file|mimes:pdf,ppt,pptx|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

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
            throw new \Exception('Failed to store file');
        }

        // REMOVED THE REPLACEMENT LOGIC - Now we always create a new pitch deck
        $pitchDeck = PitchDeck::create([
            'founder_id' => $request->founder_id,
            'title' => $request->title,
            'file_path' => $filePath,
            'file_type' => $extension,
            'thumbnail_path' => null,
            'status' => 'draft',
            'uploaded_by' => $user ? $user->id : null,
        ]);

        // Generate thumbnail
        try {
            \Log::info('Starting thumbnail generation', [
                'file_extension' => $extension,
                'file_path' => $filePath
            ]);
            
            if (in_array($extension, ['pdf', 'ppt', 'pptx'])) {
                $originalPath = Storage::disk('public')->path($filePath);
                \Log::info('Original file path', ['path' => $originalPath]);
                
                // Check if file exists before processing
                if (!file_exists($originalPath)) {
                    \Log::error('Original file not found for thumbnail', ['path' => $originalPath]);
                    throw new \Exception('File not found: ' . $originalPath);
                }
                
                $image = Image::make($originalPath . '[0]');
                $image->resize(300, 200, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $thumbnailFileName = 'thumbnail_' . $pitchDeck->id . '_' . time() . '.webp';
                $thumbnailPath = 'pitch_decks/thumbnails/' . $thumbnailFileName;
                
                \Log::info('Saving thumbnail', ['thumbnail_path' => $thumbnailPath]);
                Storage::disk('public')->put($thumbnailPath, $image->encode('webp', 85));
                
                // Verify thumbnail was created
                $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);
                \Log::info('Thumbnail saved', ['full_path' => $fullThumbnailPath, 'exists' => file_exists($fullThumbnailPath)]);
                
                $pitchDeck->thumbnail_path = $thumbnailPath;
                $pitchDeck->save();
                
                \Log::info('Thumbnail generated successfully', [
                    'pitch_deck_id' => $pitchDeck->id,
                    'thumbnail_path' => $thumbnailPath
                ]);
            } else {
                \Log::info('Skipping thumbnail generation for unsupported file type', ['extension' => $extension]);
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to generate thumbnail: ' . $e->getMessage(), [
                'pitch_deck_id' => $pitchDeck->id,
                'file_path' => $filePath,
                'extension' => $extension,
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Log activity
        try {
            $this->logAdminActivity($request, 'create_pitchdeck', 'PitchDeck', $pitchDeck->id, [
                'title' => $pitchDeck->title,
                'founder_id' => $pitchDeck->founder_id,
                'file_path' => $pitchDeck->file_path,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to log admin activity: ' . $e->getMessage());
        }

        $fileUrl = asset('storage/' . $filePath);

        return response()->json([
            'message' => 'Pitch deck uploaded successfully',
            'pitch_deck' => $pitchDeck,
            'file_url' => $fileUrl,
            'total_founder_decks' => PitchDeck::where('founder_id', $request->founder_id)->count()
        ], 201);
        
    } catch (\Exception $e) {
        \Log::error('Error in PitchDeckController@store', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Replace the file for an existing pitch deck.
     */
    public function updateFile(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:pdf,ppt,pptx|max:20480',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $pitchDeck = PitchDeck::findOrFail($id);

            if ($pitchDeck->file_path && Storage::disk('public')->exists($pitchDeck->file_path)) {
                Storage::disk('public')->delete($pitchDeck->file_path);
            }
            if ($pitchDeck->thumbnail_path && Storage::disk('public')->exists($pitchDeck->thumbnail_path)) {
                Storage::disk('public')->delete($pitchDeck->thumbnail_path);
            }

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
                throw new \Exception('Failed to store file');
            }

            $pitchDeck->file_path = $filePath;
            $pitchDeck->file_type = $extension;
            $pitchDeck->status = 'draft';
            $pitchDeck->thumbnail_path = null;
            $pitchDeck->uploaded_by = $user->id;
            $pitchDeck->save();

            try {
                if (in_array($extension, ['pdf', 'ppt', 'pptx'])) {
                    $originalPath = Storage::disk('public')->path($filePath);
                    $image = Image::make($originalPath . '[0]');
                    $image->resize(300, 200, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    $thumbnailFileName = 'thumbnail_' . $pitchDeck->id . '_' . time() . '.webp';
                    $thumbnailPath = 'pitch_decks/thumbnails/' . $thumbnailFileName;
                    Storage::disk('public')->put($thumbnailPath, $image->encode('webp', 85));
                    $pitchDeck->thumbnail_path = $thumbnailPath;
                    $pitchDeck->save();
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to generate thumbnail in updateFile: ' . $e->getMessage());
            }

            try {
                $this->logAdminActivity($request, 'replace_pitchdeck_file', 'PitchDeck', $pitchDeck->id, [
                    'title' => $pitchDeck->title,
                    'founder_id' => $pitchDeck->founder_id,
                    'file_path' => $pitchDeck->file_path,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Failed to log admin activity in updateFile: ' . $e->getMessage());
            }

            $fileUrl = asset('storage/' . $filePath);

            return response()->json([
                'message' => 'Pitch deck file updated successfully',
                'pitch_deck' => $pitchDeck,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in PitchDeckController@updateFile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
   public function show($id)
{
    $pitchDeck = PitchDeck::with('founder')->findOrFail($id);
    return response()->json($pitchDeck);
}
    /**
     * Public listing of published pitch decks.
     */
    public function publicIndex()
    {
        $cacheKey = 'public_pitch_decks_' . md5(json_encode(request()->query()));
        
        $pitchDecks = Cache::flexible($cacheKey, [300, 600], function () {
            return PitchDeck::with('founder')
                ->where('status', 'published')
                ->get();
        });

        return response()->json($pitchDecks);
    }

    /**
     * Public detail for a published pitch deck.
     */
    public function publicShow($id)
    {
        $cacheKey = "public_pitch_deck_{$id}";
        
        $pitchDeck = Cache::remember($cacheKey, 3600, function () use ($id) {
            return PitchDeck::with('founder')
                ->where('status', 'published')
                ->findOrFail($id);
        });

        return response()->json($pitchDeck);
    }

    /**
     * Update the specified resource in storage.
     */
  public function update(Request $request, $id)
{
    $pitchDeck = PitchDeck::findOrFail($id);
    
    // Get the authenticated user
    $user = $request->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    
    // Store old values before update
    $oldStatus = $pitchDeck->status;
    $oldTitle = $pitchDeck->title;
    
    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|required|string|max:255',
        'status' => 'sometimes|required|string|in:draft,published,archived',
        'notes' => 'nullable|string|max:1000',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Get the new values from request
    $newTitle = $request->input('title', $oldTitle);
    $newStatus = $request->input('status', $oldStatus);
    
    // Update the pitch deck
    $pitchDeck->update($request->only('title', 'status'));
    
    // Log admin activity for editing a pitch deck
    try {
        $this->logAdminActivity($request, 'edit_pitchdeck', 'PitchDeck', $pitchDeck->id, [
            'old_title' => $oldTitle,
            'new_title' => $newTitle,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $request->input('notes', '')
        ]);
    } catch (\Throwable $e) {
        \Log::warning('Failed to log admin activity: ' . $e->getMessage());
    }
    
    // Log the action
    \Log::info('Pitch deck updated', [
        'pitch_deck_id' => $pitchDeck->id,
        'title' => $pitchDeck->title,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'old_title' => $oldTitle,
        'new_title' => $newTitle,
        'changed_by' => $user->id,
        'changed_by_email' => $user->email,
        'notes' => $request->input('notes', '')
    ]);
    
    return response()->json([
        'message' => 'Pitch deck updated successfully',
        'pitch_deck' => $pitchDeck,
        'changes' => [
            'title' => $oldTitle !== $newTitle ? ['old' => $oldTitle, 'new' => $newTitle] : null,
            'status' => $oldStatus !== $newStatus ? ['old' => $oldStatus, 'new' => $newStatus] : null,
        ]
    ]);
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $pitchDeck = PitchDeck::findOrFail($id);
       // $this->authorize('delete', $pitchDeck);

        // Log admin activity for deleting a pitch deck (before delete so we capture data)
        try {
            $this->logAdminActivity($request, 'delete_pitchdeck', 'PitchDeck', $pitchDeck->id, [
                'title' => $pitchDeck->title,
                'file_path' => $pitchDeck->file_path,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to log admin activity: ' . $e->getMessage());
        }

        Storage::disk('public')->delete($pitchDeck->file_path);
        $pitchDeck->delete();

        return response()->json(null, 204);
    }

    /**
     * Helper to persist admin activity logs.
     */
    protected function logAdminActivity(Request $request, string $action, ?string $subjectType = null, $subjectId = null, array $data = [])
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return;
        }

        AdminActivity::create([
            'admin_user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data' => $data ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
  /**
     * Secure file access endpoint for pitch decks.
     */
    public function accessFile(Request $request, $id)
    {
        // Find pitch deck
        $pitchDeck = PitchDeck::findOrFail($id);

        // // Authorization check - admins can access all, users can access their own
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this file'
            ], 403);
        }

        // Check if file exists
        if (!$pitchDeck->file_path || !Storage::disk('public')->exists($pitchDeck->file_path)) {
            return response()->json([
                'error' => 'File not found',
                'message' => 'The requested file does not exist'
            ], 404);
        }

        // Get file information
        $filePath = Storage::disk('public')->path($pitchDeck->file_path);
        $mimeType = $this->getMimeType($pitchDeck->file_type);

        // Serve file securely
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $this->getContentDisposition($mimeType, $pitchDeck->title),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff'
        ]);
    }

    /**
     * Get appropriate MIME type for file.
     */
    private function getMimeType($fileType)
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return $mimeTypes[strtolower($fileType)] ?? 'application/octet-stream';
    }

    /**
     * Get appropriate Content-Disposition header.
     */
    private function getContentDisposition($mimeType, $title)
    {
        $safeFileName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $title);
        
        // PDFs can be displayed inline, others should be downloaded
        if ($mimeType === 'application/pdf') {
            return 'inline; filename="' . $safeFileName . '.pdf"';
        }
        
        return 'attachment; filename="' . $safeFileName . '.' . pathinfo($safeFileName, PATHINFO_EXTENSION) . '"';
    }
    /**
     * Download the specified pitch deck.
     */
    public function download(Request $request, $id)
    {
    \Log::info('=== DOWNLOAD METHOD STARTED ===');
    \Log::info('Download requested for pitch deck ID: ' . $id);
    
        // Check authentication
        $user = $request->user();
    \Log::info('User authenticated:', [
        'is_authenticated' => $user ? 'YES' : 'NO',
        'user_id' => $user ? $user->id : 'NULL'
    ]);
    
        if (! $user) {
            \Log::error('Download attempted without authentication');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $key = 'downloads:' . $user->id;
        $count = cache()->increment($key);
        if ($count === 1) {
            cache()->put($key, $count, now()->addMinutes(1));
        }
        if ($count > 20) {
            \Log::warning('Rate limit exceeded for user ' . $user->id);
            return response()->json([
                'message' => 'Too many download attempts. Please try again later.',
            ], 429);
        }

        try {
        $pitchDeck = PitchDeck::with('founder')->findOrFail($id);
        //where('status', 'published')->findOrFail($id);
        \Log::info('Pitch deck found:', [
            'id' => $pitchDeck->id,
            'title' => $pitchDeck->title,
            'file_path' => $pitchDeck->file_path,
            'file_type' => $pitchDeck->file_type,
            'status' => $pitchDeck->status
        ]);
        
        // DEBUG 1: Check what disk we're using
        \Log::info('Storage disk: public');
        \Log::info('Storage path: ' . storage_path('app/public'));
        
        // DEBUG 2: Check if file exists at the stored path
        $fullPath = Storage::disk('public')->path($pitchDeck->file_path);
        \Log::info('Full file path: ' . $fullPath);
        
        $fileExists = Storage::disk('public')->exists($pitchDeck->file_path);
        \Log::info('File exists check: ' . ($fileExists ? 'YES' : 'NO'));
        
        // DEBUG 3: List all files in the pitch_decks directory
        $allFiles = Storage::disk('public')->files('pitch_decks');
        \Log::info('All files in pitch_decks directory:', $allFiles);
        
        // DEBUG 4: Check if the specific file is in the list
        $fileInList = in_array($pitchDeck->file_path, $allFiles);
        \Log::info('File found in directory listing: ' . ($fileInList ? 'YES' : 'NO'));
        
        // DEBUG 5: Check file permissions if file exists
        if ($fileExists && file_exists($fullPath)) {
            $permissions = substr(sprintf('%o', fileperms($fullPath)), -4);
            \Log::info('File permissions: ' . $permissions);
            \Log::info('File owner: ' . fileowner($fullPath));
            \Log::info('File size: ' . filesize($fullPath) . ' bytes');
        }
        
        // DEBUG 6: Check storage link
        $publicStoragePath = public_path('storage');
        \Log::info('Public storage path: ' . $publicStoragePath);
        \Log::info('Public storage exists: ' . (file_exists($publicStoragePath) ? 'YES' : 'NO'));
        \Log::info('Public storage is link: ' . (is_link($publicStoragePath) ? 'YES' : 'NO'));
        
        if (is_link($publicStoragePath)) {
            $target = readlink($publicStoragePath);
            \Log::info('Public storage link target: ' . $target);
        }
        
        if (!$fileExists) {
            \Log::error('FILE NOT FOUND at path: ' . $pitchDeck->file_path);
            
            // Try alternative paths
            $alternativePaths = [
                $pitchDeck->file_path,
                'public/' . $pitchDeck->file_path,
                'storage/' . $pitchDeck->file_path,
                'app/public/' . $pitchDeck->file_path,
                str_replace('pitch_decks/', '', $pitchDeck->file_path)
            ];
            
            \Log::info('Checking alternative paths:');
            foreach ($alternativePaths as $altPath) {
                $exists = Storage::disk('public')->exists($altPath);
                \Log::info("  Path: $altPath - Exists: " . ($exists ? 'YES' : 'NO'));
            }
            
            return response()->json([
                'error' => 'File not found',
                'message' => 'The requested file could not be found on the server',
                'file_path' => $pitchDeck->file_path
            ], 404);
        }
        
        // Log the download
        try {
            $downloadLog = PitchDeckDownload::create([
                'user_id' => $user->id,
                'pitch_deck_id' => $pitchDeck->id,
                'downloaded_at' => now(),
                'ip_address' => $request->ip(),
            ]);
            \Log::info('Download logged successfully', ['log_id' => $downloadLog->id]);
        } catch (\Exception $e) {
            \Log::error('Failed to log download: ' . $e->getMessage());
            // Continue with download even if logging fails
        }
        
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $pitchDeck->title);
        $downloadFileName = $safeTitle . '.' . $pitchDeck->file_type;
        \Log::info('Attempting to download file as: ' . $downloadFileName);
        
        return Storage::disk('public')->download($pitchDeck->file_path, $downloadFileName);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Pitch deck not found with ID: ' . $id);
            return response()->json(['message' => 'Pitch deck not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Download failed: ' . $e->getMessage());
            \Log::error('Error trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Download failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
