<?php
/**
 * Shared runtime bootstrap helpers for coordinating WDK bundles.
 *
 * @package WDK
 */


declare(strict_types=1);

if (!function_exists('wdk_runtime_default_state')) {
    function wdk_runtime_default_state(): array
    {
        return [
            'candidates' => [],
            'bundles' => [],
            'selected' => null,
            'booted' => false,
            'booting' => false,
            'boot_hook_registered' => false,
            'notice_hook_registered' => false,
            'notices' => [],
        ];
    }
}

if (!function_exists('wdk_runtime_state')) {
    function &wdk_runtime_state(): array
    {
        if (!isset($GLOBALS['wdk_runtime_state']) || !is_array($GLOBALS['wdk_runtime_state'])) {
            $GLOBALS['wdk_runtime_state'] = wdk_runtime_default_state();
        }

        return $GLOBALS['wdk_runtime_state'];
    }
}

if (!function_exists('wdk_runtime_normalize_paths')) {
    function wdk_runtime_normalize_paths(array|string|null $paths): array
    {
        $paths = is_array($paths) ? $paths : ($paths === null ? [] : [$paths]);
        $normalized = [];

        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }

            $trimmed = rtrim($path, DIRECTORY_SEPARATOR);
            if ($trimmed === '') {
                continue;
            }

            if (!in_array($trimmed, $normalized, true)) {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }
}

if (!function_exists('wdk_runtime_slugify')) {
    function wdk_runtime_slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'wdk-bundle';
    }
}

if (!function_exists('wdk_runtime_current_root')) {
    function wdk_runtime_current_root(): string
    {
        return rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('wdk_runtime_current_version')) {
    function wdk_runtime_current_version(): string
    {
        static $version = null;
        if (is_string($version)) {
            return $version;
        }

        if (defined('WDK_VERSION')) {
            $version = ltrim((string) WDK_VERSION, 'v');
            return $version;
        }

        $packageJson = wdk_runtime_current_root() . '/package.json';
        if (is_file($packageJson)) {
            $payload = json_decode((string) file_get_contents($packageJson), true);
            $candidate = $payload['version'] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                $version = ltrim($candidate, 'v');
                return $version;
            }
        }

        $version = '0.0.0';
        return $version;
    }
}

if (!function_exists('wdk_runtime_infer_caller_file')) {
    function wdk_runtime_infer_caller_file(?array $trace = null): string
    {
        $runtimeRoot = rtrim((string) (realpath(wdk_runtime_current_root()) ?: wdk_runtime_current_root()), DIRECTORY_SEPARATOR);
        $libraryPath = $runtimeRoot . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR;
        $vendorSegment = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;

        foreach (array_reverse(get_included_files()) as $includedFile) {
            if (!is_string($includedFile) || $includedFile === '' || !file_exists($includedFile)) {
                continue;
            }

            $resolved = (string) (realpath($includedFile) ?: $includedFile);
            if ($resolved === __FILE__) {
                continue;
            }

            if (str_contains($resolved, $vendorSegment) || str_starts_with($resolved, $libraryPath)) {
                continue;
            }

            return $resolved;
        }

        $trace = $trace ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file) || $file === '' || $file === 'Command line code') {
                continue;
            }

            $resolved = (string) (realpath($file) ?: $file);
            if ($resolved === __FILE__ || str_starts_with($resolved, $libraryPath)) {
                continue;
            }

            return $resolved;
        }

        return $runtimeRoot;
    }
}

if (!function_exists('wdk_runtime_infer_bundle_root')) {
    function wdk_runtime_infer_bundle_root(string $callerFile): string
    {
        $directory = is_dir($callerFile) ? $callerFile : dirname($callerFile);
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (basename($directory) === 'wdk') {
            return dirname($directory);
        }

        $candidates = array_values(array_unique([
            $directory,
            dirname($directory),
            dirname(dirname($directory)),
        ]));

        foreach ($candidates as $candidate) {
            $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
            if ($candidate === '' || $candidate === DIRECTORY_SEPARATOR) {
                continue;
            }

            if (
                is_dir($candidate . '/wdk/configs')
                || is_dir($candidate . '/wdk/views')
                || is_file($candidate . '/wdk/bootstrap.php')
                || is_file($candidate . '/vendor/autoload.php')
                || (is_file($candidate . '/style.css') && is_file($candidate . '/functions.php'))
                || is_file($candidate . '/' . basename($candidate) . '.php')
            ) {
                return $candidate;
            }
        }

        return $directory;
    }
}

if (!function_exists('wdk_runtime_detect_bundle_type')) {
    function wdk_runtime_detect_bundle_type(string $bundleRoot): string
    {
        $bundleRealPath = realpath($bundleRoot) ?: $bundleRoot;
        $stylesheetRoot = function_exists('get_stylesheet_directory') ? realpath((string) get_stylesheet_directory()) : false;
        $templateRoot = function_exists('get_template_directory') ? realpath((string) get_template_directory()) : false;

        if (
            ($stylesheetRoot && $bundleRealPath === $stylesheetRoot)
            || ($templateRoot && $bundleRealPath === $templateRoot)
            || (is_file($bundleRoot . '/style.css') && is_file($bundleRoot . '/functions.php'))
        ) {
            return 'theme';
        }

        if (is_file($bundleRoot . '/wp-developer-kit.php')) {
            return 'core-plugin';
        }

        return 'plugin';
    }
}

if (!function_exists('wdk_runtime_discover_candidate')) {
    function wdk_runtime_discover_candidate(string $bundleRoot): array
    {
        $currentRoot = wdk_runtime_current_root();
        $candidate = [
            'root' => $currentRoot,
            'autoloader' => $currentRoot . '/vendor/autoload.php',
            'version' => wdk_runtime_current_version(),
        ];

        $installedPhp = $bundleRoot . '/vendor/composer/installed.php';
        if (!is_file($installedPhp)) {
            return $candidate;
        }

        $installed = include $installedPhp;
        if (!is_array($installed)) {
            return $candidate;
        }

        $package = $installed['versions']['joseffb/wp-developer-kit'] ?? null;
        if (!is_array($package)) {
            return $candidate;
        }

        $installPath = $package['install_path'] ?? null;
        if (is_string($installPath) && $installPath !== '') {
            $candidate['root'] = rtrim((string) (realpath($installPath) ?: $installPath), DIRECTORY_SEPARATOR);
            $candidate['autoloader'] = $bundleRoot . '/vendor/autoload.php';
        }

        $version = $package['pretty_version'] ?? $package['version'] ?? $candidate['version'];
        if (is_string($version) && $version !== '') {
            $candidate['version'] = ltrim($version, 'v');
        }

        return $candidate;
    }
}

if (!function_exists('wdk_runtime_infer_bundle_definition')) {
    function wdk_runtime_infer_bundle_definition(string $callerFile, array $overrides = [], array $locations = []): array
    {
        $bundleRoot = rtrim((string) ($overrides['root'] ?? wdk_runtime_infer_bundle_root($callerFile)), DIRECTORY_SEPARATOR);
        $candidateDefaults = wdk_runtime_discover_candidate($bundleRoot);
        $bundleType = (string) ($overrides['type'] ?? wdk_runtime_detect_bundle_type($bundleRoot));
        $defaultBundleId = $bundleType === 'core-plugin'
            ? 'wdk-core-plugin'
            : wdk_runtime_slugify(basename($bundleRoot));
        $bundleId = (string) ($overrides['id'] ?? $overrides['bundle_id'] ?? $defaultBundleId);
        $bootstrapFile = $overrides['bootstrap_file'] ?? ($bundleRoot . '/wdk/bootstrap.php');

        $bundle = [
            'id' => $bundleId,
            'type' => $bundleType,
            'root' => $bundleRoot,
            'version' => (string) ($overrides['version'] ?? $candidateDefaults['version']),
            'config_paths' => $overrides['config_paths']
                ?? $overrides['config_roots']
                ?? [
                    $bundleRoot . '/wdk/configs',
                    $bundleRoot . '/wdk/config',
                    $bundleRoot . '/configs',
                ],
            'template_paths' => $overrides['template_paths']
                ?? $overrides['template_roots']
                ?? ($locations !== [] ? $locations : [
                    $bundleRoot . '/wdk/views',
                    $bundleRoot . '/views',
                ]),
            'bootstrap_file' => is_string($bootstrapFile) && is_file($bootstrapFile) ? $bootstrapFile : null,
            'label' => (string) ($overrides['label'] ?? $bundleId),
        ];

        $candidate = [
            'id' => (string) (($overrides['runtime_id'] ?? null) ?: ($bundleId . '-runtime')),
            'bundle_id' => $bundleId,
            'version' => (string) ($overrides['version'] ?? $candidateDefaults['version']),
            'autoloader' => (string) ($overrides['autoloader'] ?? $candidateDefaults['autoloader']),
            'root' => (string) ($overrides['runtime_root'] ?? $candidateDefaults['root']),
            'label' => (string) ($overrides['runtime_label'] ?? ($bundleId . '-runtime')),
        ];

        return [
            'candidate' => $candidate,
            'bundle' => $bundle,
        ];
    }
}

if (!function_exists('wdk_register_inferred_runtime_bundle')) {
    function wdk_register_inferred_runtime_bundle(string $callerFile, array $overrides = [], array $locations = []): array
    {
        $definition = wdk_runtime_infer_bundle_definition($callerFile, $overrides, $locations);
        wdk_register_runtime_bundle($definition['candidate'], $definition['bundle']);

        return $definition;
    }
}

if (!function_exists('wdk_normalize_runtime_candidate')) {
    function wdk_normalize_runtime_candidate(array $candidate): array
    {
        $root = rtrim((string) ($candidate['root'] ?? dirname(__FILE__)), DIRECTORY_SEPARATOR);
        $id = (string) ($candidate['id'] ?? ($candidate['bundle_id'] ?? md5($root)));

        return [
            'id' => $id,
            'bundle_id' => (string) ($candidate['bundle_id'] ?? $id),
            'version' => ltrim((string) ($candidate['version'] ?? '0.0.0'), 'v'),
            'autoloader' => isset($candidate['autoloader']) ? (string) $candidate['autoloader'] : $root . '/vendor/autoload.php',
            'root' => $root,
            'label' => (string) ($candidate['label'] ?? $id),
            'order' => (int) ($candidate['order'] ?? count(wdk_runtime_state()['candidates'])),
        ];
    }
}

if (!function_exists('wdk_normalize_runtime_bundle')) {
    function wdk_normalize_runtime_bundle(array $bundle): array
    {
        $root = rtrim((string) ($bundle['root'] ?? dirname(__FILE__)), DIRECTORY_SEPARATOR);
        $id = (string) ($bundle['id'] ?? md5($root));

        $configPaths = $bundle['config_paths']
            ?? $bundle['config_roots']
            ?? [
                $root . '/wdk/configs',
                $root . '/wdk/config',
                $root . '/configs',
            ];

        $templatePaths = $bundle['template_paths']
            ?? $bundle['template_roots']
            ?? [
                $root . '/wdk/views',
                $root . '/views',
            ];

        return [
            'id' => $id,
            'type' => (string) ($bundle['type'] ?? 'plugin'),
            'root' => $root,
            'version' => ltrim((string) ($bundle['version'] ?? '0.0.0'), 'v'),
            'config_paths' => wdk_runtime_normalize_paths($configPaths),
            'template_paths' => wdk_runtime_normalize_paths($templatePaths),
            'bootstrap_file' => !empty($bundle['bootstrap_file']) ? (string) $bundle['bootstrap_file'] : null,
            'label' => (string) ($bundle['label'] ?? $id),
            'order' => (int) ($bundle['order'] ?? count(wdk_runtime_state()['bundles'])),
        ];
    }
}

if (!function_exists('wdk_runtime_add_notice')) {
    function wdk_runtime_add_notice(string $message, string $level = 'warning'): void
    {
        $state = &wdk_runtime_state();
        $notice = [
            'message' => $message,
            'level' => $level,
        ];

        foreach ($state['notices'] as $existing) {
            if (($existing['message'] ?? '') === $message && ($existing['level'] ?? '') === $level) {
                return;
            }
        }

        $state['notices'][] = $notice;

        if (!$state['notice_hook_registered'] && function_exists('add_action')) {
            add_action('admin_notices', 'wdk_render_runtime_notices', 5);
            $state['notice_hook_registered'] = true;
        }
    }
}

if (!function_exists('wdk_render_runtime_notices')) {
    function wdk_render_runtime_notices(): void
    {
        $state = &wdk_runtime_state();
        foreach ($state['notices'] as $notice) {
            $level = $notice['level'] ?? 'warning';
            $message = $notice['message'] ?? '';
            if ($message === '') {
                continue;
            }
            ?>
            <div class="notice notice-<?php echo esc_attr($level); ?>">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }
    }
}

if (!function_exists('wdk_runtime_schedule_boot')) {
    function wdk_runtime_schedule_boot(): void
    {
        $state = &wdk_runtime_state();
        if ($state['boot_hook_registered']) {
            return;
        }

        if (function_exists('add_action')) {
            add_action('after_setup_theme', 'wdk_boot_registered_runtime', PHP_INT_MAX);
            $state['boot_hook_registered'] = true;
        }
    }
}

if (!function_exists('wdk_runtime_maybe_boot_immediately')) {
    function wdk_runtime_maybe_boot_immediately(): void
    {
        if (function_exists('did_action') && did_action('after_setup_theme') > 0) {
            wdk_boot_registered_runtime();
        }
    }
}

if (!function_exists('wdk_register_runtime_candidate')) {
    function wdk_register_runtime_candidate(array $candidate): array
    {
        $candidate = wdk_normalize_runtime_candidate($candidate);
        $state = &wdk_runtime_state();
        $state['candidates'][$candidate['id']] = $candidate;

        if ($state['booted'] && !empty($state['selected']) && ($candidate['id'] !== ($state['selected']['id'] ?? null))) {
            $selectedVersion = (string) ($state['selected']['version'] ?? '0.0.0');
            if (version_compare($candidate['version'], $selectedVersion, '>')) {
                wdk_runtime_add_notice(sprintf(
                    'WDK runtime candidate "%s" (%s) registered after the shared runtime already selected "%s" (%s). Late higher-version candidates cannot replace a booted runtime.',
                    $candidate['bundle_id'],
                    $candidate['version'],
                    $state['selected']['bundle_id'] ?? $state['selected']['id'] ?? 'unknown-runtime',
                    $selectedVersion
                ), 'warning');
            }
        }

        wdk_runtime_schedule_boot();
        wdk_runtime_maybe_boot_immediately();

        return $candidate;
    }
}

if (!function_exists('wdk_register_bundle')) {
    function wdk_register_bundle(array $bundle): array
    {
        $bundle = wdk_normalize_runtime_bundle($bundle);
        $state = &wdk_runtime_state();
        $state['bundles'][$bundle['id']] = $bundle;

        if ($state['booted'] && class_exists('\\WDK\\Runtime', false)) {
            try {
                \WDK\Runtime::attachBundle($bundle);
                wdk_runtime_sync_info(\WDK\Runtime::info());
            } catch (\Throwable $throwable) {
                wdk_runtime_add_notice(sprintf(
                    'WDK bundle "%s" failed to attach after the shared runtime booted: %s',
                    $bundle['id'],
                    $throwable->getMessage()
                ), 'error');
            }

            return $bundle;
        }

        wdk_runtime_schedule_boot();
        wdk_runtime_maybe_boot_immediately();

        return $bundle;
    }
}

if (!function_exists('wdk_register_runtime_bundle')) {
    function wdk_register_runtime_bundle(array $candidate, array $bundle): void
    {
        $registeredCandidate = wdk_register_runtime_candidate($candidate);
        $bundle['version'] = $bundle['version'] ?? $registeredCandidate['version'];
        $bundle['id'] = $bundle['id'] ?? $registeredCandidate['bundle_id'];
        wdk_register_bundle($bundle);
    }
}

if (!function_exists('wdk_runtime_select_candidate')) {
    function wdk_runtime_select_candidate(): ?array
    {
        $candidates = array_values(wdk_runtime_state()['candidates']);
        if ($candidates === []) {
            return null;
        }

        $winner = $candidates[0];
        foreach (array_slice($candidates, 1) as $candidate) {
            if (version_compare($candidate['version'], $winner['version'], '>')) {
                $winner = $candidate;
            }
        }

        return $winner;
    }
}

if (!function_exists('wdk_runtime_sync_info')) {
    function wdk_runtime_sync_info(array $info): void
    {
        $state = &wdk_runtime_state();
        if (array_key_exists('selected', $info)) {
            $state['selected'] = $info['selected'];
        }
        if (array_key_exists('bundles', $info)) {
            $state['bundles'] = [];
            foreach ((array) $info['bundles'] as $bundle) {
                if (!is_array($bundle) || empty($bundle['id'])) {
                    continue;
                }
                $state['bundles'][$bundle['id']] = $bundle;
            }
        }
        if (array_key_exists('booted', $info)) {
            $state['booted'] = (bool) $info['booted'];
        }
    }
}

if (!function_exists('wdk_boot_registered_runtime')) {
    function wdk_boot_registered_runtime(): bool
    {
        $state = &wdk_runtime_state();

        if ($state['booted'] || $state['booting']) {
            return (bool) $state['booted'];
        }

        $winner = wdk_runtime_select_candidate();
        if ($winner === null) {
            return false;
        }

        $state['selected'] = $winner;
        $state['booting'] = true;

        foreach ($state['candidates'] as $candidate) {
            if ($candidate['id'] === $winner['id']) {
                continue;
            }

            if (version_compare($candidate['version'], $winner['version'], '<')) {
                wdk_runtime_add_notice(sprintf(
                    'WDK bundle "%s" (%s) is using the shared runtime from "%s" (%s).',
                    $candidate['bundle_id'],
                    $candidate['version'],
                    $winner['bundle_id'],
                    $winner['version']
                ), 'warning');
            }
        }

        if (!class_exists('\\WDK\\Runtime', false)) {
            $autoloader = $winner['autoloader'] ?? '';
            if ($autoloader === '' || !file_exists($autoloader)) {
                $state['booting'] = false;
                wdk_runtime_add_notice(sprintf(
                    'WDK runtime candidate "%s" is missing its Composer autoloader at %s.',
                    $winner['id'],
                    $autoloader ?: '[unknown]'
                ), 'error');
                return false;
            }

            require_once $autoloader;
        }

        if (!class_exists('\\WDK\\Runtime')) {
            $state['booting'] = false;
            wdk_runtime_add_notice('WDK shared runtime could not be loaded after selecting a runtime candidate.', 'error');
            return false;
        }

        try {
            $booted = \WDK\Runtime::boot($winner, array_values($state['bundles']));
            $state['booted'] = $booted;
            $state['selected'] = \WDK\Runtime::info()['selected'] ?? $winner;
            wdk_runtime_sync_info(\WDK\Runtime::info());
        } catch (\Throwable $throwable) {
            $state['booted'] = false;
            wdk_runtime_add_notice('WDK shared runtime boot failed: ' . $throwable->getMessage(), 'error');
        }

        $state['booting'] = false;

        return (bool) $state['booted'];
    }
}

if (!function_exists('wdk_runtime_info')) {
    function wdk_runtime_info(): array
    {
        $state = wdk_runtime_state();
        $runtimeInfo = null;
        if (!empty($state['booted']) && class_exists('\\WDK\\Runtime', false)) {
            $runtimeInfo = \WDK\Runtime::info();
        }

        $bundles = array_values($runtimeInfo['bundles'] ?? $state['bundles']);
        $candidates = array_values($state['candidates']);

        return [
            'booted' => (bool) ($runtimeInfo['booted'] ?? $state['booted']),
            'selected' => $runtimeInfo['selected'] ?? $state['selected'],
            'bundles' => $bundles,
            'bundle_ids' => array_values(array_map(static fn (array $bundle): string => (string) ($bundle['id'] ?? ''), $bundles)),
            'candidates' => $candidates,
            'candidate_ids' => array_values(array_map(static fn (array $candidate): string => (string) ($candidate['id'] ?? ''), $candidates)),
            'notices' => $state['notices'],
            'notice_count' => count($state['notices']),
        ];
    }
}

if (!function_exists('wdk_reset_runtime_state_for_tests')) {
    function wdk_reset_runtime_state_for_tests(): void
    {
        if (function_exists('remove_action')) {
            remove_action('after_setup_theme', 'wdk_boot_registered_runtime', PHP_INT_MAX);
            remove_action('admin_notices', 'wdk_render_runtime_notices', 5);
        }

        $GLOBALS['wdk_runtime_state'] = wdk_runtime_default_state();
    }
}
