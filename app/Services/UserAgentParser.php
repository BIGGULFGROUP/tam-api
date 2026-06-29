<?php

namespace App\Services;

class UserAgentParser
{
    /**
     * Parse a User-Agent string into device_type, browser, and OS.
     *
     * @return array{device_type: string, browser: string, os: string}
     */
    public function parse(?string $ua): array
    {
        if (empty($ua)) {
            return ['device_type' => 'unknown', 'browser' => 'unknown', 'os' => 'unknown'];
        }

        $ua = mb_strtolower($ua);

        return [
            'device_type' => $this->detectDevice($ua),
            'browser'     => $this->detectBrowser($ua),
            'os'          => $this->detectOs($ua),
        ];
    }

    private function detectDevice(string $ua): string
    {
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad') || str_contains($ua, 'playbook') || (str_contains($ua, 'android') && !str_contains($ua, 'mobile'))) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod') || str_contains($ua, 'blackberry') || str_contains($ua, 'windows phone')) {
            return 'mobile';
        }
        return 'desktop';
    }

    private function detectBrowser(string $ua): string
    {
        $browsers = [
            'edge'    => 'edg',
            'opera'   => 'opr',
            'chrome'  => 'chrome',
            'firefox' => 'firefox',
            'safari'  => 'safari',
        ];

        foreach ($browsers as $name => $key) {
            if (str_contains($ua, $key)) {
                return $name;
            }
        }

        return 'other';
    }

    private function detectOs(string $ua): string
    {
        $systems = [
            'windows' => 'windows',
            'macos'   => 'mac os',
            'linux'   => 'linux',
            'android' => 'android',
            'ios'     => 'iphone os',
        ];

        foreach ($systems as $name => $key) {
            if (str_contains($ua, $key)) {
                // Normalize iOS naming
                return $name === 'ios' ? 'ios' : $name;
            }
        }

        return 'other';
    }
}
