<?php

namespace App\Actions\StoreSetting;

use App\Models\StoreSetting;

class UpdateStoreSettingAction
{
    public function execute(array $data): StoreSetting
    {
        $settings = StoreSetting::current();
        $settings->update($data);

        return $settings;
    }
}
