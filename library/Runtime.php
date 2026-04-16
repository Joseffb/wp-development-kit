<?php
/**
 * Contains the Runtime class.
 *
 * @package WDK
 */


namespace WDK;

/**
 * Provides the Runtime component.
 */
class Runtime
{
    private static bool $booted = false;
    private static array $selected = [];
    private static array $bundles = [];
    private static array $bootstrappedBundles = [];

    public static function boot(array $selectedCandidate, array $bundles): bool
    {
        if (self::$booted) {
            return true;
        }

        self::$selected = $selectedCandidate;
        self::$bundles = self::sortBundles($bundles);

        self::bootstrapBundles();

        self::$booted = System::bootBundles(self::$bundles, self::$selected);
        self::syncLoaderState();

        return self::$booted;
    }

    /**
     * Attach a newly registered bundle after the shared runtime has already booted.
     */
    public static function attachBundle(array $bundle): bool
    {
        if (!self::$booted) {
            return false;
        }

        $previousBundles = self::$bundles;
        self::$bundles = self::upsertBundle(self::$bundles, $bundle);
        $attachedBundle = self::findBundle((string) ($bundle['id'] ?? ''));

        if ($attachedBundle === null) {
            self::$bundles = $previousBundles;
            self::syncLoaderState();

            return false;
        }

        self::bootstrapBundle($attachedBundle);

        if (!System::attachBundle($attachedBundle, self::$bundles, self::$selected)) {
            self::$bundles = $previousBundles;
            self::syncLoaderState();

            return false;
        }

        self::syncLoaderState();

        return true;
    }

    public static function info(): array
    {
        return [
            'booted' => self::$booted,
            'selected' => self::$selected,
            'bundles' => self::$bundles,
            'bundle_ids' => array_values(array_map(static fn (array $bundle): string => (string) ($bundle['id'] ?? ''), self::$bundles)),
        ];
    }

    public static function resetForTests(): void
    {
        self::$booted = false;
        self::$selected = [];
        self::$bundles = [];
        self::$bootstrappedBundles = [];
        self::syncLoaderState();
    }

    private static function sortBundles(array $bundles): array
    {
        $typePriority = [
            'theme' => 0,
            'plugin' => 10,
            'core-plugin' => 20,
        ];

        usort($bundles, static function (array $left, array $right) use ($typePriority): int {
            $leftPriority = $typePriority[$left['type'] ?? 'plugin'] ?? 100;
            $rightPriority = $typePriority[$right['type'] ?? 'plugin'] ?? 100;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0));
        });

        return $bundles;
    }

    private static function upsertBundle(array $bundles, array $bundle): array
    {
        $bundleId = (string) ($bundle['id'] ?? '');
        $filtered = array_values(array_filter($bundles, static fn (array $existing): bool => (string) ($existing['id'] ?? '') !== $bundleId));
        $filtered[] = $bundle;

        return self::sortBundles($filtered);
    }

    private static function findBundle(string $bundleId): ?array
    {
        foreach (self::$bundles as $bundle) {
            if ((string) ($bundle['id'] ?? '') === $bundleId) {
                return $bundle;
            }
        }

        return null;
    }

    private static function bootstrapBundles(): void
    {
        foreach (self::$bundles as $bundle) {
            self::bootstrapBundle($bundle);
        }
    }

    private static function bootstrapBundle(array $bundle): void
    {
        $bootstrapFile = $bundle['bootstrap_file'] ?? null;
        $bundleId = (string) ($bundle['id'] ?? '');

        if ($bundleId !== '' && isset(self::$bootstrappedBundles[$bundleId])) {
            return;
        }

        if (!is_string($bootstrapFile) || $bootstrapFile === '') {
            return;
        }

        if (!file_exists($bootstrapFile)) {
            if (function_exists('wdk_runtime_add_notice')) {
                wdk_runtime_add_notice(sprintf(
                    'WDK bundle "%s" declared a bootstrap file that does not exist: %s',
                    $bundle['id'] ?? 'unknown-bundle',
                    $bootstrapFile
                ), 'warning');
            }
            return;
        }

        require $bootstrapFile;

        if ($bundleId !== '') {
            self::$bootstrappedBundles[$bundleId] = true;
        }
    }

    private static function syncLoaderState(): void
    {
        if (function_exists('wdk_runtime_sync_info')) {
            wdk_runtime_sync_info(self::info());
        }
    }
}
