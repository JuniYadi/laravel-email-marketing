<?php

namespace App\Support\Traffic;

use Illuminate\Http\Request;

class ClientIpResolver
{
    public function resolve(Request $request): string
    {
        $candidates = $this->candidateIps($request);

        foreach ($candidates as $candidateIp) {
            if ($this->isPublicIp($candidateIp)) {
                return $candidateIp;
            }
        }

        foreach ($candidates as $candidateIp) {
            if ($this->isValidIp($candidateIp)) {
                return $candidateIp;
            }
        }

        return (string) $request->ip();
    }

    /**
     * @return list<string>
     */
    protected function candidateIps(Request $request): array
    {
        $candidates = [];

        $xForwardedFor = $request->headers->get('X-Forwarded-For');

        if (is_string($xForwardedFor) && trim($xForwardedFor) !== '') {
            foreach (explode(',', $xForwardedFor) as $ipPart) {
                $candidates[] = trim($ipPart);
            }
        }

        foreach (['CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP'] as $headerName) {
            $headerValue = $request->headers->get($headerName);

            if (is_string($headerValue) && trim($headerValue) !== '') {
                $candidates[] = trim($headerValue);
            }
        }

        foreach ($request->ips() as $forwardedIp) {
            $candidates[] = (string) $forwardedIp;
        }

        $candidates[] = (string) $request->ip();

        return array_values(array_unique(array_filter($candidates, fn (string $value): bool => $value !== '')));
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
