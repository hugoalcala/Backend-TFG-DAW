<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $this->cloudinary = new Cloudinary(
            "cloudinary://{$apiKey}:{$apiSecret}@{$cloudName}"
        );
    }

    public function upload(UploadedFile $file, string $folder = 'uploads'): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $resourceType = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) ? 'image'
            : (in_array($extension, ['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv']) ? 'video' : 'raw');

        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder'           => $folder,
            'resource_type'    => $resourceType,
            'use_filename'     => true,
            'unique_filename'  => true,
        ]);

        $url = $result['secure_url'];
        // Para archivos raw, Cloudinary no incluye la extensión en la URL — añadirla manualmente
        if ($resourceType === 'raw' && !str_ends_with($url, '.' . $extension)) {
            $url .= '.' . $extension;
        }

        return [
            'url'       => $url,
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(string $publicId, string $resourceType = 'image'): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId, ['resource_type' => $resourceType]);
    }

    public static function extractPublicId(string $url): ?string
    {
        if (!str_contains($url, 'cloudinary.com')) {
            return null;
        }
        // Extract public_id from Cloudinary URL
        // Format: https://res.cloudinary.com/{cloud}/image/upload/v{version}/{folder}/{filename}
        if (preg_match('/\/upload\/(?:v\d+\/)?(.+?)(?:\.[^.]+)?$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
