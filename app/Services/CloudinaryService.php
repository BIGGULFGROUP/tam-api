<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name', '');
        $this->apiKey = config('services.cloudinary.api_key', '');
        $this->apiSecret = config('services.cloudinary.api_secret', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret);
    }

    /**
     * Upload a file to Cloudinary using their REST API.
     */
    public function upload(UploadedFile $file, array $options = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Cloudinary: upload skipped (not configured)');
            return null;
        }

        try {
            $timestamp = time();
            $defaultOptions = [
                'folder' => 'tam-uploads',
                'timestamp' => $timestamp,
                'api_key' => $this->apiKey,
            ];

            $params = array_merge($defaultOptions, $options);
            if (isset($params['public_id'])) {
                $params['public_id'] = $params['public_id'];
            }

            // Generate signature
            $params['signature'] = $this->generateSignature($params, $timestamp);

            // Build multipart request
            $response = Http::attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/auto/upload", $params);

            if (!$response->successful()) {
                Log::error('Cloudinary upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $result = $response->json();

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
            Log::error('Cloudinary upload exception', [
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
            $timestamp = time();
            $params = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => $this->apiKey,
            ];
            $params['signature'] = $this->generateSignature($params, $timestamp);

            $response = Http::post(
                "https://api.cloudinary.com/v1_1/{$this->cloudName}/{$resourceType}/destroy",
                $params
            );

            return $response->successful() && ($response->json()['result'] ?? '') === 'ok';
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
            'q' => 'auto',
            'f' => 'auto',
            'c' => 'limit',
        ];

        $transforms = array_merge($defaults, $transformations);
        $transformStr = implode(',', array_map(
            fn ($k, $v) => "{$k}_{$v}",
            array_keys($transforms),
            array_values($transforms)
        ));

        return "https://res.cloudinary.com/{$this->cloudName}/image/upload/{$transformStr}/{$publicId}";
    }

    /**
     * Generate a responsive width URL.
     */
    public function responsiveUrl(string $publicId, int $width, array $extra = []): string
    {
        return $this->url($publicId, array_merge([
            'w' => $width,
            'c' => 'fill',
        ], $extra));
    }

    public function getCloudName(): string
    {
        return $this->cloudName;
    }

    /**
     * Extract public_id from a Cloudinary URL.
     */
    public function extractPublicId(string $url): ?string
    {
        if (!$this->cloudName) return null;

        $pattern = "#res\.cloudinary\.com/{$this->cloudName}/(image|video|raw)/upload/(?:v\d+/)?(.+?)(?:\.[a-z]+)?$#";
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

    /**
     * Generate Cloudinary API signature.
     */
    private function generateSignature(array $params, int $timestamp): string
    {
        // Sort params alphabetically by key
        ksort($params);

        $toSign = '';
        foreach ($params as $key => $value) {
            if ($key === 'file' || $key === 'signature' || $key === 'resource_type') continue;
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $toSign .= "{$key}={$value}&";
        }
        $toSign = rtrim($toSign, '&');

        return sha1($toSign . $this->apiSecret);
    }
}
