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
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key'    => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    public function upload(UploadedFile $file, string $folder = 'uploads'): array
    {
        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder'           => $folder,
            'resource_type'    => 'auto',
            'use_filename'     => true,
            'unique_filename'  => true,
        ]);

        return [
            'url'       => $result['secure_url'],
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
