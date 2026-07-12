<?php

namespace App\Actions\StoreSetting;

use App\Models\StoreSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateStoreSettingLogoAction
{
    public function execute(UploadedFile $file): StoreSetting
    {
        $settings = StoreSetting::current();

        // Nome de arquivo único por upload (não o nome original) para que o
        // Cache-Control imutável do nginx (ver docker/nginx/default.conf) seja
        // seguro: trocar o logo sempre gera uma URL nova, nunca sobrescreve
        // uma já cacheada pelo navegador.
        $path = $file->storeAs('logos', Str::uuid().'.'.$file->extension(), 'public');

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->update(['logo_path' => $path]);

        return $settings;
    }
}
