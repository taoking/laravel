<?php

namespace App\Modules\DataSource\Services;

use Illuminate\Support\Facades\Crypt;

class DataSourcePasswordEncryptor
{
    public function encrypt(?string $password): ?string
    {
        if ($password === null || $password === '') {
            return null;
        }

        return Crypt::encryptString($password);
    }

    public function decrypt(?string $encryptedPassword): ?string
    {
        if ($encryptedPassword === null || $encryptedPassword === '') {
            return null;
        }

        return Crypt::decryptString($encryptedPassword);
    }
}
