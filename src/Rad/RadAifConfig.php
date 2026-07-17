<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

final class RadAifConfig
{
    /**
     * @param array<string, mixed> $radConfig
     * @return array<string, mixed>
     */
    public static function load(array $radConfig): array
    {
        $siteRoot = (string) ($radConfig['dir']['site'] ?? dirname(__DIR__, 6));
        $configPath = $siteRoot . '/rad/config/ai-config.php';
        $config = [];
        if (is_file($configPath)) {
            $loaded = require $configPath;
            $config = is_array($loaded) ? $loaded : [];
        } elseif (is_array($radConfig['ai'] ?? null)) {
            $config = self::fromLegacy($radConfig['ai']);
        }

        $config['default_provider'] = strtolower(trim((string) ($config['default_provider'] ?? 'openai')));
        $config['default_profile'] = self::profileKey((string) ($config['default_profile'] ?? 'general'));
        $config['default_quality'] = self::qualityKey((string) ($config['default_quality'] ?? 'mini'));
        $config['fallback_quality'] = self::qualityKey((string) ($config['fallback_quality'] ?? 'full'));
        $config['providers'] = is_array($config['providers'] ?? null) ? $config['providers'] : [];
        $config['providers']['openai'] = array_replace([
            'api_key' => '',
            'endpoint' => 'https://api.openai.com/v1/responses',
            'model' => 'gpt-5.4-mini',
            'embedding_model' => 'text-embedding-3-small',
            'timeout' => 60,
        ], is_array($config['providers']['openai'] ?? null) ? $config['providers']['openai'] : []);
        $config['profiles'] = self::profiles($config);
        return $config;
    }

    /**
     * @param array<string, mixed> $radConfig
     * @return array<string, mixed>
     */
    public static function resolve(array $radConfig, string $profile = 'general', ?string $quality = null): array
    {
        $config = self::load($radConfig);
        $profileKey = self::profileKey($profile !== '' ? $profile : (string) $config['default_profile']);
        $profileConfig = is_array($config['profiles'][$profileKey] ?? null) ? $config['profiles'][$profileKey] : [];
        $providerKey = strtolower(trim((string) ($profileConfig['provider'] ?? $config['default_provider'])));
        $providerConfig = is_array($config['providers'][$providerKey] ?? null) ? $config['providers'][$providerKey] : [];
        $qualityKey = self::qualityKey((string) ($quality ?? $profileConfig['default_quality'] ?? $config['default_quality']));
        $qualityModels = is_array($profileConfig['quality_models'][$qualityKey] ?? null) ? $profileConfig['quality_models'][$qualityKey] : [];
        return [
            'provider' => $providerKey,
            'api_key' => (string) ($providerConfig['api_key'] ?? ''),
            'endpoint' => (string) ($profileConfig['endpoint'] ?? $providerConfig['endpoint'] ?? ''),
            'model' => (string) ($qualityModels['model'] ?? $profileConfig['model'] ?? $providerConfig['model'] ?? ''),
            'fallback_model' => (string) ($qualityModels['fallback_model'] ?? $profileConfig['fallback_model'] ?? ''),
            'embedding_model' => (string) ($providerConfig['embedding_model'] ?? 'text-embedding-3-small'),
            'max_tokens' => (int) ($profileConfig['max_tokens'] ?? 256),
            'timeout' => (int) ($profileConfig['timeout'] ?? $providerConfig['timeout'] ?? 60),
            'profile' => $profileKey,
            'quality' => $qualityKey,
        ];
    }

    /**
     * @param array<string, mixed> $legacy
     * @return array<string, mixed>
     */
    private static function fromLegacy(array $legacy): array
    {
        return [
            'default_provider' => 'openai',
            'default_profile' => 'general',
            'default_quality' => $legacy['default_quality'] ?? 'mini',
            'fallback_quality' => $legacy['fallback_quality'] ?? 'full',
            'providers' => [
                'openai' => [
                    'api_key' => $legacy['api_key'] ?? '',
                    'endpoint' => $legacy['endpoint'] ?? 'https://api.openai.com/v1/responses',
                    'model' => $legacy['model'] ?? $legacy['ai_model'] ?? 'gpt-5.4-mini',
                    'embedding_model' => $legacy['embedding_model'] ?? 'text-embedding-3-small',
                    'timeout' => $legacy['timeout'] ?? 60,
                ],
            ],
            'profiles' => is_array($legacy['profiles'] ?? null) ? $legacy['profiles'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private static function profiles(array $config): array
    {
        $profiles = is_array($config['profiles'] ?? null) ? $config['profiles'] : [];
        foreach (['general' => 256, 'coding' => 1800] as $name => $maxTokens) {
            $existing = is_array($profiles[$name] ?? null) ? $profiles[$name] : [];
            $profiles[$name] = array_replace([
                'provider' => $config['default_provider'],
                'default_quality' => $config['default_quality'],
                'fallback_quality' => $config['fallback_quality'],
                'max_tokens' => $maxTokens,
                'timeout' => 45,
                'quality_models' => [
                    'mini' => ['model' => 'gpt-5.4-mini', 'fallback_model' => 'gpt-5.4'],
                    'full' => ['model' => 'gpt-5.4', 'fallback_model' => 'gpt-5.4-mini'],
                ],
            ], $existing);
        }
        return $profiles;
    }

    private static function profileKey(string $profile): string
    {
        return strtolower(trim($profile)) === 'coding' ? 'coding' : 'general';
    }

    private static function qualityKey(string $quality): string
    {
        return strtolower(trim($quality)) === 'full' ? 'full' : 'mini';
    }
}
