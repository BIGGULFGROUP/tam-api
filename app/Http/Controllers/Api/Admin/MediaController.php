<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const MAX_SIZE_BYTES  = 200 * 1024 * 1024; // 200 MB
    private const IMG_MAX_BYTES   = 25  * 1024 * 1024; // 25 MB
    private const ALLOWED_MIMES   = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'image/svg+xml', 'image/avif',
        'video/mp4', 'video/webm', 'video/quicktime',
        'application/pdf', 'application/zip', 'text/plain',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Media::orderByDesc('created_at');
        $limit = min(200, max(1, (int) $request->query('limit', 200)));

        if ($q = $request->query('q')) {
            $query->where(fn ($b) =>
                $b->where('file_name', 'like', "%$q%")
                  ->orWhere('alt_text', 'like', "%$q%")
                  ->orWhere('caption', 'like', "%$q%")
            );
        }

        match ($request->query('type')) {
            'images' => $query->where('mime_type', 'like', 'image/%'),
            'videos' => $query->where('mime_type', 'like', 'video/%'),
            'docs'   => $query->where('mime_type', 'not like', 'image/%')->where('mime_type', 'not like', 'video/%'),
            default  => null,
        };

        return response()->json($query->limit($limit)->get([
            'id', 'file_name', 'public_url', 'mime_type',
            'size_bytes', 'width', 'height', 'alt_text', 'caption', 'created_at',
        ]));
    }

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file) {
            return response()->json(['message' => 'File is required'], 400);
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return response()->json(['message' => "Unsupported file type: $mime"], 415);
        }
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return response()->json(['message' => 'File exceeds maximum upload size (200MB)'], 413);
        }
        if (str_starts_with($mime, 'image/') && $file->getSize() > self::IMG_MAX_BYTES) {
            return response()->json(['message' => 'Image exceeds maximum upload size (25MB)'], 413);
        }

        $disk      = config('filesystems.default', 'public');
        $now       = now();
        $safeName  = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                     .'-'.$now->timestamp.'-'.Str::random(6)
                     .'.'.$file->getClientOriginalExtension();
        $path      = $now->format('Y/m').'/'.$safeName;

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        $publicUrl = Storage::disk($disk)->url($path);

        [$width, $height] = $this->imageDimensions($file->getRealPath(), $mime);

        $media = Media::create([
            'id'           => Str::uuid(),
            'file_name'    => $file->getClientOriginalName(),
            'storage_path' => $path,
            'public_url'   => $publicUrl,
            'mime_type'    => $mime,
            'size_bytes'   => $file->getSize(),
            'width'        => $width,
            'height'       => $height,
            'alt_text'     => '',
            'caption'      => '',
            'uploaded_by'  => $request->user()?->id,
        ]);

        return response()->json($media->only([
            'id', 'file_name', 'public_url', 'mime_type',
            'size_bytes', 'width', 'height', 'alt_text', 'caption', 'created_at',
        ]), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $media->update($request->only(['alt_text', 'caption']));
        return response()->json($media->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $disk  = config('filesystems.default', 'public');
        Storage::disk($disk)->delete($media->storage_path);
        $media->delete();
        return response()->json(null, 204);
    }

    private function imageDimensions(string $path, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }
        try {
            [$w, $h] = getimagesize($path);
            return [$w ?: null, $h ?: null];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
