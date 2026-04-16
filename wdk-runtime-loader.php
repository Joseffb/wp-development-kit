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
