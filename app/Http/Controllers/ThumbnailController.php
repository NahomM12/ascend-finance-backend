<?php

namespace App\Http\Controllers;

use App\Models\PitchDeck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;

class ThumbnailController extends Controller
{
    /**
     * Upload thumbnail for a pitch deck.
     * Converts uploaded image to WebP format.
     */
    public function upload(Request $request, $pitchDeckId)
    {
        $validator = Validator::make($request->all(), [
            'thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif,svg,bmp|max:5120', // 5MB max
            'width' => 'nullable|integer|min:50|max:2000',
            'height' => 'nullable|integer|min:50|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $pitchDeck = PitchDeck::findOrFail($pitchDeckId);
        
        // Check permissions - only owner or admin can upload thumbnail
         $user = $request->user();
         if ($pitchDeck->uploaded_by !== $user->id && !in_array($user->role, ['admin', 'superadmin'])) {
             return response()->json(['message' => 'Unauthorized to update this pitch deck'], 403);
         }

        $imageFile = $request->file('thumbnail');
        $width = $request->input('width', 300);
        $height = $request->input('height', 200);
        
        try {
            // Generate unique filename
            $originalName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = 'thumbnail_' . $pitchDeck->id . '_' . time() . '_' . Str::slug($originalName) . '.webp';
            
            // Create Intervention Image instance
            $image = Image::read($imageFile);
            // Scale image to fit within dimensions while maintaining aspect ratio
             $image->scale(width: $width, height: $height);
            // Resize if needed (maintain aspect ratio)
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize(); // Prevent upsizing
            });
            
            // Convert to WebP and optimize quality
              $encodedImage = $image->encodeByExtension('webp', quality: 85);
            
            // Define storage path
            $thumbnailPath = 'pitch_decks/thumbnails/' . $fileName;
            
            // Store in public disk
             Storage::disk('public')->put($thumbnailPath, $encodedImage); 
            
            // Delete old thumbnail if exists
            if ($pitchDeck->thumbnail_path && Storage::disk('public')->exists($pitchDeck->thumbnail_path)) {
                Storage::disk('public')->delete($pitchDeck->thumbnail_path);
            }
            
            // Update pitch deck
            $pitchDeck->thumbnail_path = $thumbnailPath;
            $pitchDeck->save();
            
            return response()->json([
                'message' => 'Thumbnail uploaded successfully and converted to WebP',
                'thumbnail_url' => asset('storage/' . $thumbnailPath),
                'thumbnail_path' => $thumbnailPath,
                'file_size' => Storage::disk('public')->size($thumbnailPath),
                'dimensions' => ['width' => $image->width(), 'height' => $image->height()],
                'pitch_deck' => $pitchDeck->only(['id', 'title', 'status'])
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Failed to upload thumbnail for pitch deck ' . $pitchDeckId . ': ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to process thumbnail',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete thumbnail for a pitch deck.
     */
    public function delete(Request $request, $pitchDeckId)
    {
        $pitchDeck = PitchDeck::findOrFail($pitchDeckId);
        
        // Check permissions
        $user = $request->user();
        if ($pitchDeck->uploaded_by !== $user->id && !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized to update this pitch deck'], 403);
        }

        if ($pitchDeck->thumbnail_path && Storage::disk('public')->exists($pitchDeck->thumbnail_path)) {
            Storage::disk('public')->delete($pitchDeck->thumbnail_path);
            $pitchDeck->thumbnail_path = null;
            $pitchDeck->save();
            
            return response()->json([
                'message' => 'Thumbnail deleted successfully',
                'pitch_deck' => $pitchDeck->only(['id', 'title', 'status'])
            ], 200);
        }
        
        return response()->json([
            'message' => 'No thumbnail found to delete'
        ], 404);
    }
    
    /**
     * Get thumbnail URL for a pitch deck.
     * Returns default if no thumbnail exists.
     */
    public function show($pitchDeckId)
    {
        $pitchDeck = PitchDeck::findOrFail($pitchDeckId);
        
        $thumbnailUrl = $this->getThumbnailUrl($pitchDeck);
        
        return response()->json([
            'pitch_deck_id' => $pitchDeck->id,
            'pitch_deck_title' => $pitchDeck->title,
            'thumbnail_url' => $thumbnailUrl,
            'has_custom_thumbnail' => !empty($pitchDeck->thumbnail_path),
            'thumbnail_path' => $pitchDeck->thumbnail_path,
            'file_type' => $pitchDeck->file_type
        ]);
    }
    
    /**
     * Generate thumbnail URL (helper method).
     */
  public function getThumbnailUrl(PitchDeck $pitchDeck)
{
    $cacheKey = "thumbnail_url_{$pitchDeck->id}";
    
    return Cache::remember($cacheKey, 604800, function () use ($pitchDeck) { // 7 days
        if ($pitchDeck->thumbnail_path && Storage::disk('public')->exists($pitchDeck->thumbnail_path)) {
            return asset('storage/' . $pitchDeck->thumbnail_path);
        }
        
        // Return default thumbnail based on file type
        $defaults = [
            'pdf' => asset('images/default-pdf-thumbnail.jpg'),
            'ppt' => asset('images/default-ppt-thumbnail.jpg'),
            'pptx' => asset('images/default-pptx-thumbnail.jpg'),
        ];
        
        return $defaults[$pitchDeck->file_type] ?? asset('images/default-thumbnail.jpg');
    });
}
    
    /**
     * Get default thumbnail URL based on file type.
     */
    private function getDefaultThumbnail(PitchDeck $pitchDeck)
    {
        // You can create these default images in public/images/
        $defaults = [
            'pdf' => asset('images/default-pdf-thumbnail.jpg'),
            'ppt' => asset('images/default-ppt-thumbnail.jpg'),
            'pptx' => asset('images/default-pptx-thumbnail.jpg'),
        ];
        
        $defaultUrl = $defaults[$pitchDeck->file_type] ?? asset('images/default-thumbnail.jpg');
        
        // Or use a placeholder service
        if (!file_exists(public_path(parse_url($defaultUrl, PHP_URL_PATH)))) {
            // Generate dynamic placeholder
            $color = hash('crc32', $pitchDeck->title) % 360;
            $defaultUrl = "https://via.placeholder.com/300x200/{$color}/ffffff?text=" . 
                          urlencode(substr($pitchDeck->title, 0, 30));
        }
        
        return $defaultUrl;
    }
    
    /**
     * Bulk update thumbnails (admin only).
     * Useful for migrating old thumbnails to WebP.
     */
    public function bulkConvert(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Admin access required'], 403);
        }
        
        $pitchDecks = PitchDeck::whereNotNull('thumbnail_path')->get();
        $converted = 0;
        $failed = 0;
        
        foreach ($pitchDecks as $pitchDeck) {
            try {
                $oldPath = $pitchDeck->thumbnail_path;
                
                // Skip if already WebP
                if (str_ends_with($oldPath, '.webp')) {
                    continue;
                }
                
                // Read old image
                if (Storage::disk('public')->exists($oldPath)) {
                    $image = Image::make(Storage::disk('public')->path($oldPath));
                    
                    // Convert to WebP
                    $fileName = pathinfo($oldPath, PATHINFO_FILENAME) . '.webp';
                    $newPath = 'pitch_decks/thumbnails/' . $fileName;
                    
                   $webpImage = $image->encodeByExtension('webp', quality: 85);
                    Storage::disk('public')->put($newPath, $webpImage);
                    
                    // Update record
                    $pitchDeck->thumbnail_path = $newPath;
                    $pitchDeck->save();
                    
                    // Delete old file
                    Storage::disk('public')->delete($oldPath);
                    
                    $converted++;
                }
            } catch (\Exception $e) {
                \Log::error('Failed to convert thumbnail for pitch deck ' . $pitchDeck->id . ': ' . $e->getMessage());
                $failed++;
            }
        }
        
        return response()->json([
            'message' => 'Bulk conversion completed',
            'converted' => $converted,
            'failed' => $failed,
            'total_processed' => $pitchDecks->count()
        ]);
    }
}