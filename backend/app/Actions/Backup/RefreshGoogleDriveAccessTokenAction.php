<?php

namespace App\Actions\Backup;

use Illuminate\Support\Facades\Http;

class RefreshGoogleDriveAccessTokenAction
{
    public function execute(string $refreshToken): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_oauth.client_id'),
            'client_secret' => config('services.google_oauth.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ])->throw();

        return $response->json('access_token');
    }
}
