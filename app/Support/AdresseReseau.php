<?php

namespace App\Support;

/**
 * Adresses de cet ordinateur sur le réseau local (Wi-Fi / câble).
 * C'est l'une d'elles qu'on tape dans l'application du téléphone : http://192.168.1.20:8200
 */
class AdresseReseau
{
    /** @return list<string> */
    public static function ips(): array
    {
        $ips = gethostbynamel(gethostname()) ?: [];

        // Adresse utilisée pour sortir du réseau : aucun paquet n'est envoyé (UDP sans données)
        $socket = function_exists('socket_create') ? @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP) : false;
        if ($socket !== false) {
            if (@socket_connect($socket, '8.8.8.8', 53) && @socket_getsockname($socket, $ip)) {
                array_unshift($ips, $ip);
            }
            socket_close($socket);
        }

        return array_values(array_unique(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! str_starts_with($ip, '127.')
            && ! str_starts_with($ip, '169.254.'))));
    }

    /** @return list<string> */
    public static function urls(int $port = 8200): array
    {
        return array_map(fn ($ip) => "http://{$ip}:{$port}", self::ips());
    }
}
