<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAppUpdateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $currentVersionCode = max(0, $request->integer('version_code'));
        $update = $this->updateManifest();
        $latestVersionCode = max(1, (int) ($update['latest_version_code'] ?? 1));
        $minSupportedVersionCode = max(1, (int) ($update['min_supported_version_code'] ?? 1));

        $apkUrl = (string) ($update['apk_url'] ?? '/downloads/traklo.apk');

        if (! str_starts_with($apkUrl, 'http://') && ! str_starts_with($apkUrl, 'https://')) {
            $apkUrl = url($apkUrl);
        }

        return response()->json([
            'app_name' => 'Traklo',
            'current_version_code' => $currentVersionCode,
            'latest_version_code' => $latestVersionCode,
            'latest_version_name' => (string) ($update['latest_version_name'] ?? '1.0'),
            'min_supported_version_code' => $minSupportedVersionCode,
            'update_available' => $currentVersionCode > 0 && $currentVersionCode < $latestVersionCode,
            'required' => $currentVersionCode > 0 && $currentVersionCode < $minSupportedVersionCode,
            'apk_url' => $apkUrl,
            'changelog' => (string) ($update['changelog'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function updateManifest(): array
    {
        $fallback = [
            'latest_version_code' => (int) config('mobile_app.latest_version_code', 1),
            'latest_version_name' => (string) config('mobile_app.latest_version_name', '1.0'),
            'min_supported_version_code' => (int) config('mobile_app.min_supported_version_code', 1),
            'apk_url' => (string) config('mobile_app.apk_url', '/downloads/traklo.apk'),
            'changelog' => (string) config('mobile_app.changelog', 'Обновление Traklo доступно для установки.'),
        ];

        if ((bool) config('mobile_app.force_config', false)) {
            return $fallback;
        }

        $manifestPath = (string) config('mobile_app.manifest_path');
        if ($manifestPath === '' || ! is_file($manifestPath)) {
            return $fallback;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($decoded)) {
            return $fallback;
        }

        return array_merge($fallback, array_intersect_key($decoded, $fallback));
    }
}
