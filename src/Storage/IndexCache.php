<?php

declare(strict_types=1);

namespace Pagenode\Storage;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

final class IndexCache
{
    private FilesystemAdapter $cache;

    public function __construct(?string $directory = null)
    {
        $cacheDirectory = $directory ?: null;
        $this->cache = new FilesystemAdapter(
            namespace: 'pagenode_index',
            defaultLifetime: 0,
            directory: $cacheDirectory ?: null
        );
    }

    /**
     * @param callable(): array<string, array<string, mixed>> $builder
     * @return array{data: array<string, array<string, mixed>>, rebuilt: bool}
     */
    public function get(string $path, string $version, callable $builder): array
    {
        $cacheKey = $this->cacheKey($path);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $payload = $item->get();
            if (
                is_array($payload) &&
                ($payload['version'] ?? null) === $version &&
                isset($payload['data']) &&
                is_array($payload['data'])
            ) {
                /** @var array<string, array<string, mixed>> $data */
                $data = $payload['data'];
                return ['data' => $data, 'rebuilt' => false];
            }
        }

        $data = $builder();
        $this->save($item, $version, $data);

        return ['data' => $data, 'rebuilt' => true];
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function save(ItemInterface $item, string $version, array $data): void
    {
        $item->set([
            'version' => $version,
            'data' => $data
        ]);
        $this->cache->save($item);
    }

    private function cacheKey(string $path): string
    {
        return 'index_' . md5($path);
    }
}
