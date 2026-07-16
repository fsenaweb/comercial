<?php

namespace App\Actions\StoreSetting;

use App\Models\StoreSetting;

class UpdateLabelSettingsAction
{
    public function execute(array $data): StoreSetting
    {
        $settings = StoreSetting::current();

        $settings->update(['label_settings' => $data]);

        return $settings;
    }
}
