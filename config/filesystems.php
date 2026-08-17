<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Uploads Disk
    |--------------------------------------------------------------------------
    |
    | Where product photos, hero banners, "What's Hot" images and department photos are
    | written, and the disk App\Support\ImageUrl builds their URLs from. Deliberately its
    | own setting rather than reusing 'default' above: that one is what unqualified
    | Storage:: calls fall back to, and repointing it would silently move anything else
    | that ever writes a file.
    |
    | 'public' is this machine's disk behind the public/storage symlink — right for a
    | laptop and for any host with a real filesystem. Set UPLOADS_DISK=s3 on a host with
    | an ephemeral one (Render, Laravel Cloud, a Codespace) and fill in the AWS_* values
    | below. Any S3-compatible service works, including Cloudflare R2 and Supabase
    | Storage, through AWS_ENDPOINT with AWS_USE_PATH_STYLE_ENDPOINT=true.
    |
    | Changing this does NOT move files that are already stored. See docs/DEPLOY.md.
    |
    | THE DEFAULT FOLLOWS AWS_BUCKET ON PURPOSE. Laravel Cloud injects the whole AWS_* set
    | the moment a bucket is attached to an environment — including FILESYSTEM_DISK — but
    | it knows nothing about a setting of ours. Defaulting to 'public' regardless would
    | mean a Cloud deployment with a bucket sitting right there still writing uploads to a
    | filesystem that is wiped on the next deploy, and nothing would look wrong until the
    | images vanished. So: a bucket configured means uploads belong in it. UPLOADS_DISK
    | still overrides, either way.
    |
    */

    'uploads_disk' => env('UPLOADS_DISK', env('AWS_BUCKET') ? 's3' : 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
