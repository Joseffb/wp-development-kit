<?php
/**
 * Contains the System class.
 *
 * @package WDK
 */


namespace WDK;

use DirectoryIterator;
use JsonException;
use RuntimeException;

/**
 * Class Install
 */
class System
{
    /**
     * Start WDK for the calling theme or plugin.
     *
     * When the shared runtime loader is available, WDK infers the bundle root,
     * bundle type, standard config and template directories, and optional
     * `wdk/bootstrap.php` file from the caller and registers that bundle with
     * the shared runtime automatically.
     */
    public static function Start(array $locations = []): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $callerFile = function_exists('wdk_runtime_infer_caller_file')
            ? wdk_runtime_infer_caller_file($trace)
            : ($trace[1]['file'] ?? dirname(__DIR__));
        $callPathDir = dirname($callerFile);

        if (function_exists('wdk_register_inferred_runtime_bundle')) {
            wdk_register_inferred_runtime_bundle($callerFile, [], $locations);
            return true;
        }

        return self::bootBundles([
            self::legacyBundleDefinition($callPathDir, $locations),
        ], [
            'id' => 'legacy-runtime',
            'bundle_id' => 'legacy-runtime',
            'version' => defined('WDK_VERSION') ? WDK_VERSION : '0.0.0',
            'root' => dirname(__DIR__),
        ]);
    }

    public static function bootBundles(array $bundles, array $runtime = []): bool
    {
        if ($bundles === []) {
            return false;
        }

        try {
            self::defineLegacyPathConstants($bundles, $runtime);
            self::validateBundleConfigs($bundles);

            foreach ($bundles as $bundle) {
                foreach (($bundle['config_paths'] ?? []) as $configPath) {
                    if (is_string($configPath) && is_dir($configPath)) {
                        self::Setup($configPath);
                    }
                }
            }

            Template::Setup(self::templateLocationsForBundles($bundles, $runtime));

            return true;
        } catch (\Throwable $throwable) {
            Utility::Log('JSON ERROR, Please validate config files.');
            Utility::Log($throwable);
            if (function_exists('wdk_runtime_add_notice')) {
                wdk_runtime_add_notice('WDK shared runtime failed to boot: ' . $throwable->getMessage(), 'error');
            }
            return false;
        }
    }

    /**
     * Attach a newly registered bundle to an already booted shared runtime.
     */
    public static function attachBundle(array $bundle, array $bundles, array $runtime = []): bool
    {
        try {
            self::validateBundleConfigs($bundles);

            foreach (($bundle['config_paths'] ?? []) as $configPath) {
                if (is_string($configPath) && is_dir($configPath)) {
                    self::Setup($configPath);
                }
            }

            Template::Setup(self::templateLocationsForBundles($bundles, $runtime));

            return true;
        } catch (\Throwable $throwable) {
            Utility::Log('JSON ERROR, Please validate config files.');
            Utility::Log($throwable);
            if (function_exists('wdk_runtime_add_notice')) {
                wdk_runtime_add_notice(sprintf(
                    'WDK bundle "%s" could not attach to the shared runtime: %s',
                    $bundle['id'] ?? 'unknown-bundle',
                    $throwable->getMessage()
                ), 'error');
            }

            return false;
        }
    }

    /**
     * Install command for the customizations in the config files.
     *
     * @param null $dir
     *
     * @return bool|int|null
     * @throws JsonException
     */
    public static function Setup($dir = null)
    {
        $config_files = WDK_CONFIG_BASE;
        if (Utility::IsDirEmpty($config_files)) {
            $config_files = get_template_directory();
        }
        $dir = $dir ?: $config_files;
        if (Utility::DoesDirExist($dir)) {
            $dir = new DirectoryIterator($dir);
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot() && (bool)strpos($fileinfo->getFilename(), '.json')) {
                    $config_file = json_decode(file_get_contents($fileinfo->getRealPath()), true, 512, JSON_THROW_ON_ERROR);
                    if (!empty($config_file)) {
                        switch (strtolower($fileinfo->getFilename())) {
                            case 'posttypes.json':
                            case 'post_types.json':
                                self::ProcessPostTypes($config_file);
                                break;
                            case 'taxonomies.json':
                            case 'taxonomy.json':
                                self::ProcessTaxonomies($config_file);
                                break;
                            case 'shortcodes.json':
                            case 'shortcode.json':
                                self::ProcessShortcodes($config_file);
                                break;
                            case 'sidebars.json':
                            case 'sidebar.json':
                                self::ProcessSidebars($config_file);
                                break;
                            case 'menus.json':
                            case 'menu.json':
                                self::ProcessMenus($config_file);
                                break;
                            case 'widgets.json':
                            case 'widget.json':
                                self::ProcessWidgets($config_file);
                                break;
                            case 'fields.json':
                            case 'field.json':
                                self::ProcessFields($config_file);
                                break;
                            case 'pages.json':
                            case 'page.json':
                                self::ProcessPosts($config_file);
                                break;
                        }
                    }
                }
            }
        }
        return null; //makes IDE happy
    }

    private static function legacyBundleDefinition(string $callPathDir, array $locations = []): array
    {
        $configPaths = [];
        if (is_dir($callPathDir . '/wdk/configs')) {
            $configPaths[] = $callPathDir . '/wdk/configs';
        } elseif (is_dir($callPathDir . '/wdk/config')) {
            $configPaths[] = $callPathDir . '/wdk/config';
        } elseif ($configBase = get_option('WDK_CONFIG_BASE')) {
            $configPaths[] = $configBase;
        } elseif (is_dir(get_stylesheet_directory() . '/wdk/config')) {
            $configPaths[] = get_stylesheet_directory() . '/wdk/config';
        } else {
            $configPaths[] = dirname(__DIR__) . '/configs';
        }

        $templatePaths = [];
        if ($locations !== []) {
            $templatePaths = $locations;
        } elseif (is_dir($callPathDir . '/wdk/views')) {
            $templatePaths[] = $callPathDir . '/wdk/views';
        } elseif ($templateBase = get_option('WDK_TEMPLATE_LOCATIONS_BASE')) {
            $templatePaths = (array) $templateBase;
        } elseif (is_dir(get_stylesheet_directory() . '/wdk/views')) {
            $templatePaths[] = get_stylesheet_directory() . '/wdk/views';
        } elseif (is_dir(dirname(__DIR__) . '/views')) {
            $templatePaths[] = dirname(__DIR__) . '/views';
        }

        return [
            'id' => 'legacy-bundle',
            'type' => 'plugin',
            'root' => $callPathDir,
            'config_paths' => self::filterExistingPaths($configPaths),
            'template_paths' => self::filterExistingPaths($templatePaths),
            'bootstrap_file' => null,
            'version' => defined('WDK_VERSION') ? WDK_VERSION : '0.0.0',
            'order' => 0,
        ];
    }

    private static function defineLegacyPathConstants(array $bundles, array $runtime = []): void
    {
        $configBase = null;
        $templateBase = null;

        foreach ($bundles as $bundle) {
            if ($configBase === null && !empty($bundle['config_paths'][0])) {
                $configBase = $bundle['config_paths'][0];
            }

            if ($templateBase === null && !empty($bundle['template_paths'])) {
                $templateBase = self::templateLocationsForBundles($bundles, $runtime);
            }
        }

        if (!defined('WDK_CONFIG_BASE')) {
            define('WDK_CONFIG_BASE', $configBase ?? dirname(__DIR__) . '/configs');
        }

        if (!defined('WDK_TEMPLATE_LOCATIONS_BASE')) {
            define('WDK_TEMPLATE_LOCATIONS_BASE', $templateBase ?? []);
        }
    }

    private static function templateLocationsForBundles(array $bundles, array $runtime = []): array
    {
        $locations = [];

        foreach ($bundles as $bundle) {
            foreach (($bundle['template_paths'] ?? []) as $templatePath) {
                if (is_string($templatePath) && is_dir($templatePath) && !in_array($templatePath, $locations, true)) {
                    $locations[] = $templatePath;
                }
            }
        }

        $winnerRoot = rtrim((string) ($runtime['root'] ?? dirname(__DIR__)), DIRECTORY_SEPARATOR);
        $internalViews = $winnerRoot . '/views';
        if (is_dir($internalViews) && !in_array($internalViews, $locations, true)) {
            $locations[] = $internalViews;
        }

        return $locations;
    }

    private static function filterExistingPaths(array $paths): array
    {
        $filtered = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $trimmed = rtrim($path, DIRECTORY_SEPARATOR);
            if (is_dir($trimmed) && !in_array($trimmed, $filtered, true)) {
                $filtered[] = $trimmed;
            }
        }

        return $filtered;
    }

    private static function validateBundleConfigs(array $bundles): void
    {
        $catalog = [
            'post type' => [
                'files' => ['posttypes.json', 'post_types.json'],
                'key' => static fn (array $config): ?string => isset($config['name']) ? sanitize_key((string) $config['name']) : null,
            ],
            'taxonomy' => [
                'files' => ['taxonomies.json', 'taxonomy.json'],
                'key' => static fn (array $config): ?string => isset($config['name']) ? sanitize_key((string) $config['name']) : null,
            ],
            'shortcode' => [
                'files' => ['shortcodes.json', 'shortcode.json'],
                'key' => static fn (array $config): ?string => isset($config['tag']) ? sanitize_key((string) $config['tag']) : null,
            ],
            'page' => [
                'files' => ['pages.json', 'page.json'],
                'key' => static function (array $config): ?string {
                    $slug = Compatibility::getArrayValue((array) ($config['post_meta'] ?? []), ['slug']);
                    $slug = $slug ?: ($config['post_title'] ?? null);
                    return is_string($slug) && $slug !== '' ? sanitize_title($slug) : null;
                },
            ],
        ];

        $seen = [];

        foreach ($bundles as $bundle) {
            foreach (($bundle['config_paths'] ?? []) as $configPath) {
                if (!is_dir($configPath)) {
                    continue;
                }

                foreach (new DirectoryIterator($configPath) as $fileInfo) {
                    if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                        continue;
                    }

                    $fileName = strtolower($fileInfo->getFilename());
                    foreach ($catalog as $label => $descriptor) {
                        if (!in_array($fileName, $descriptor['files'], true)) {
                            continue;
                        }

                        $payload = json_decode(file_get_contents($fileInfo->getPathname()), true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($payload)) {
                            continue;
                        }

                        foreach ($payload as $config) {
                            if (!is_array($config)) {
                                continue;
                            }

                            $key = $descriptor['key']($config);
                            if ($key === null || $key === '') {
                                continue;
                            }

                            if (isset($seen[$label][$key]) && $seen[$label][$key] !== ($bundle['id'] ?? '')) {
                                throw new RuntimeException(sprintf(
                                    'WDK %s collision for "%s" between bundles "%s" and "%s".',
                                    $label,
                                    $key,
                                    $seen[$label][$key],
                                    $bundle['id'] ?? 'unknown-bundle'
                                ));
                            }

                            $seen[$label][$key] = $bundle['id'] ?? 'unknown-bundle';
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $config_file
     *
     * @return void
     */
    public static function ProcessPostTypes($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                PostType::CreateCustomPostType($config['name'], $config['args']);
            }
        }
    }

    /**
     * @param $config_array
     *
     * @return void
     */
    public static function ProcessTaxonomies($config_array): void
    {
        foreach ($config_array as $config) {
            if (!empty($config)) {
                $name = $term_name = $config['name'];
                if (!empty($config['rewrite']['slug'])) {
                    $name = ['human' => $name, 'slug' => $config['rewrite']['slug']];
                }
                $post_types = !empty($config['post_types']) ? $config['post_types'] : array("post");
                $labels = !empty($config['labels']) ? $config['labels'] : array();
                $options = !empty($config['options']) ? $config['options'] : array();
                // Taxonomy crate must happen on every load
                Taxonomy::CreateCustomTaxonomy($name, $post_types, $labels, $options);
                update_option('tax_' . $name . '_installed', true);
                if (!empty($config['defaults']) && (bool)get_option('tax_term_' . $name . '_installed') === false) {
                    // Default values need to only be seeded once, or you won't be able to delete or rename them later.
                    Taxonomy::CreateTerm($term_name, $config['defaults']);
                    update_option('tax_term_' . $name . '_installed', true);
                }
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessShortcodes($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                $buttons = !empty($config['tinyMCE_buttons']) ? $config['tinyMCE_buttons'] : false;
                Shortcode::CreateCustomShortcode($config['tag'], $config['callback']['ns'], $config['callback']['method'], $buttons);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessSidebars($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Sidebar::CreateCustomSidebar($config['config']);
                if (!empty($config['defaults'])) {
                    Sidebar::SetCustomSidebarDefaults($config['config']['id'], $config['defaults']);
                }
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessMenus($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Menu::CreateCustomMenu($config);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessWidgets($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Widget::CreateCustomWidget($config['callback']);
            }
        }
    }

    /**
     * @param $config_file
     *
     * @return void
     */
    public static function ProcessFields($config_file): void
    {
        foreach ($config_file as $k => $field) {
            $field = !empty($field['field']) ? $field['field'] : $field;
            if ($k === "global") {
                break;
            }
            if (!empty($field['post_types'])) {
                foreach ($field['post_types'] as $pt) {
                    $post_ty = str_replace(" ", "_", $pt);

                    Field::AddCustomFieldToPost(
                        $post_ty,
                        $field['id'],
                        $field['label'],
                        $field['type'],
                        $field['options'],
                        $field
                    );
                    if (!empty($field['admin_column_header'])) {
                        Field::AddFieldToPostAdminColumns($field, $pt);
                    }
                }
            }
        }

    }

    /**
     * @param $page_config_file
     * @return void
     */
    public static function ProcessPosts($page_config_file): void
    {
        foreach ($page_config_file as $config) {
            if (!empty($config)) {
                $post_type = !empty($config['post_type']) ? $config['post_type'] : [];
                $post_title = !empty($config['post_title']) ? $config['post_title'] : [];
                $post_content = !empty($config['post_content']) ? $config['post_content'] : [];
                $post_meta = !empty($config['post_meta']) ? $config['post_meta'] : [];
                $creator = static function () use ($post_type, $post_title, $post_content, $post_meta): void {
                    Post::CreatePost($post_type, $post_title, $post_content, $post_meta);
                };

                if (function_exists('did_action') && did_action('init') > 0) {
                    $creator();
                    continue;
                }

                add_action('init', $creator, 10000);
            }
        }
    }
}
