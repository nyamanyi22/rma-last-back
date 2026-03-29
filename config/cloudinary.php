<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Webhook Notification
    |--------------------------------------------------------------------------
    */
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Cloudinary URL / Credentials
    |--------------------------------------------------------------------------
    | You can provide the full cloudinary:// key or use env variables
    */
    'cloud_url' => env('CLOUDINARY_URL', 'cloudinary://'.env('CLOUDINARY_API_KEY').':'.env('CLOUDINARY_API_SECRET').'@'.env('CLOUDINARY_CLOUD_NAME')),

    /*
    |--------------------------------------------------------------------------
    | Optional Upload Preset
    |--------------------------------------------------------------------------
    */
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),

    /*
    |--------------------------------------------------------------------------
    | Optional Blade Upload Widget Routes
    |--------------------------------------------------------------------------
    */
    'upload_route'  => env('CLOUDINARY_UPLOAD_ROUTE'),
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),

];
