<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private Cloudinary $cloudinary;
    private string $cloudName;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name', '');
        $apiKey = config('services.cloudinary.api_key', '');
        $apiSecret = config('services.cloudinary.api_secret', '');

        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $this->cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->cloudName);
    }

    /**
     * Upload a file to Cloudinary.
     */
    public function upload(UploadedFile $file, array $options = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Cloudinary: upload skipped (not configured)');
            return null;
        }

        try {
            $uploadApi = new UploadApi();
            $tempPath = $file->getRealPath();

            $defaultOptions = [
                'folder' => 'tam-uploads',
                'resource_type' => 'auto',
                'overwrite' => false,
                'unique_filename' => true,
                'use_filename' => false,
            ];

            $result = $uploadApi->upload($tempPath, array_merge($defaultOptions, $options));

            return [
                'public_id' => $result['public_id'] ?? null,
                'url' => $result['secure_url'] ?? $result['url'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'size_bytes' => $result['bytes'] ?? $file->getSize(),
                'resource_type' => $result['resource_type'] ?? 'image',
                'original_filename' => $file->getClientOriginalName(),
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            return null;
        }
    }

    /**
     * Delete a file from Cloudinary by public_id.
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $uploadApi = new UploadApi();
            $uploadApi->destroy($publicId, ['resource_type' => $resourceType]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);
            return false;
        }
    }

    /**
     * Generate an optimized transformation URL.
     */
    public function url(string $publicId, array $transformations = []): string
    {
        $defaults = [
            'quality' => 'auto',
            'fetch_format' => 'auto',
            'crop' => 'limit',
        ];

        $options = array_merge($defaults, $transformations);

        return $this->cloudinary->image($publicId)
            ->addTransformation(implode(',', array_map(
                fn ($k, $v) => "{$k}_{$v}",
                array_keys($options),
                array_values($options)
            )))
            ->toUrl();
    }

    /**
     * Generate a responsive srcset URL.
     */
    public function responsiveUrl(string $publicId, int $width, array $extra = []): string
    {
        return $this->url($publicId, array_merge([
            'width' => $width,
            'crop' => 'fill',
        ], $extra));
    }

    /**
     * Get the Cloudinary cloud name for building frontend URLs.
     */
    public function getCloudName(): string
    {
        return $this->cloudName;
    }

    /**
     * Extract public_id from a Cloudinary URL.
     * Example: https://res.cloudinary.com/dkswydvru/image/upload/v1234567/tam-uploads/abc123.jpg → tam-uploads/abc123
     */
    public function extractPublicId(string $url): ?string
    {
        $cloudName = $this->cloudName;
        if (!$cloudName) return null;

        $pattern = "#res\.cloudinary\.com/{$cloudName}/(image|video|raw)/upload/(?:v\d+/)?(.+?)(?:\.[a-z]+)?$#";
        if (preg_match($pattern, $url, $matches)) {
            return $matches[2];
        }

        return null;
    }

    /**
     * Determine resource type from Cloudinary URL.
     */
    public function extractResourceType(string $url): string
    {
        if (str_contains($url, '/video/upload/')) return 'video';
        if (str_contains($url, '/raw/upload/')) return 'raw';
        return 'image';
    }
}
