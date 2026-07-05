<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrakloDownloadController extends Controller
{
    public function show(): Response
    {
        $apkFileUrl = route('downloads.traklo.file');

        return response()->view('downloads.traklo', [
            'apkFileUrl' => $apkFileUrl,
            'iconUrl' => asset('downloads/traklo-icon.png'),
            'appName' => (string) config('mobile_app.name', 'Traklo'),
            'versionName' => $this->latestVersionName(),
        ]);
    }

    public function file(): BinaryFileResponse
    {
        $path = public_path('downloads/traklo.apk');

        abort_unless(is_file($path), 404, 'APK ещё не опубликован. Обратитесь к администратору.');

        return response()->file($path, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="Traklo.apk"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function latestVersionName(): string
    {
        $manifestPath = (string) config('mobile_app.manifest_path', public_path('downloads/traklo-update.json'));

        if (! is_file($manifestPath)) {
            return '';
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) ? (string) ($manifest['latest_version_name'] ?? '') : '';
    }
}
