<?php

namespace App\Services;

class NetworkSetupService
{
    public function getLanInfo(): array
    {
        $port = (int) config('app.server_port', 8080);
        $host = config('app.server_host', '0.0.0.0');
        $ips = $this->detectLocalIps();

        $urls = [];
        foreach ($ips as $ip) {
            $urls[] = "http://{$ip}:{$port}";
        }

        return [
            'host'       => $host,
            'port'       => $port,
            'ips'        => $ips,
            'tablet_urls'=> $urls,
            'primary_url'=> $urls[0] ?? "http://127.0.0.1:{$port}",
        ];
    }

    public function detectLocalIps(): array
    {
        $ips = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('ipconfig');
            if ($output && preg_match_all('/IPv4[^\:]*:\s*(\d+\.\d+\.\d+\.\d+)/', $output, $m)) {
                foreach ($m[1] as $ip) {
                    if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254.')) {
                        $ips[] = $ip;
                    }
                }
            }
        } else {
            $hostname = gethostname();
            if ($hostname) {
                $records = @dns_get_record($hostname, DNS_A);
                if ($records) {
                    foreach ($records as $r) {
                        if (!empty($r['ip']) && $r['ip'] !== '127.0.0.1') {
                            $ips[] = $r['ip'];
                        }
                    }
                }
            }
        }

        if (empty($ips)) {
            $ips[] = '127.0.0.1';
        }

        return array_values(array_unique($ips));
    }
}
