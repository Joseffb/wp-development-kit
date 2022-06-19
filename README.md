# WP Development Kit

Welcome the WP Development Kit, or WDK for short. WDK allows a WordPress Developer to create sites easily and efficiently.

Some features that WDK makes available for developers are as follows:

- JSON based configuration files: Create custom post types, custom taxonomies, menus, pages, posts, shadow taxonomy relationships, shortcodes and widgets, all from a set of config files.
- Shadow Taxonomies allow you to link and use multiple post, as well as properties of those post (i.e. images, metadata, taxonomies, etc) in a related post. Conditional Shadow Taxonomies can also be defined manually allowing you to split a single taxonomy representation into multiple (i.e. a source post can be divided into multiple taxonomies in same post based off of specific values.)
- Utility functions for logging, debugging, and troubleshooting.
- Twig based templates for use in plugins, themes and child themes, and admin pages. This allows you to pull your data layer out of your template layer. Great vor MVC workflows.

## Getting Started

WDK can be installed as a plugin (download and place into plugins folder) or installed via composer.

### Plugin install
- Download the source code from https://github.com/Joseffb/wp-development-kit
- Place the files into a folder called wp-development-kit in your /plugins or /mu-plugins folder.
- Run composer install within that directory to install dependency files.
- Activate the plugin from within that WordPress site.

### Composer install
- run the following composer command withing your app:
- `composer require joseffb/wp-development-kit`

### Initialize the library
- Inside your start file place the following command to initiate the composer autoloader:
- `require_once __DIR__ . '/vendor/autoload.php'`;
- Place teh following command to call and initiate WDK:
`WDK\System::Start();`

## File structure
WDK by default expects you to have your WDK files in the following structure from the root of where you ran System::Start():
```   
        root  
        │
        └─── wdk
            │
            └─── configs
            │       │
            │       └─── json files
            │
            └─── views
                    │
                    └─── twig files
```
Location to these files can be changed by defining the path in 'WDK_CONFIG_BASE' and 'WDK_TEMPLATE_LOCATIONS_BASE' accordingly.
If you create multiple plugins and themes in the same site with WDK this may be a good option in terms of maintenance.

## Working with config files
### 8 Types of config files
WDK has 8 different config files that you can use to scaffold your WordPress site:
- Fields.json - Used to create custom fields on CPT.
- Menus.json - Used to create menus in the WP Menu system
- Posts.json - Used to load pages and posts into the system.
- Post_types.json - Used to create custom post types, as well as additional features such as shadow taxonomy relationships
- Shortcodes.json - Used to define shortcodes
- Sidebars.json - Used to define sidebars (as well as which widgets load into sidebars) 
- Taxonomies.json - Used to define custom taxonomies as well as extra options such as admin columns and WP-GraphQL access.
- Widgets.json - Used to define widgets as well as custom fields for said widget.

**_Important_**: Config files must be valid and meet JSON file specifications. If they do not validate, WP will have a critical fail.

### Post_types.json & Taxonomy.json
The two of the most used config files that you will use are probably Post_types.json and Taxonomy.json. An entire site can be setup within minutes with just these two files.
Each file is made to mimic the parameters of the WordPress CPT and Taxonomy command.

Most of the arguments are pretty straight forward and with some being extra and explained below.
#### Post Types
Extra Options:
- use_twig: used to tell WDK to use a Twig based template for that CPT type. The template will be looking in the /wdk/views folder of your root directory for 'single-CPTNAME.twig'. Any WordPress template (such as archive) can be replaced using an update_option(wdk_template_TEMPLATENAME); in your functions file.;
- related_cpt: an array identifying the name singular name of the CPT that should be linked to this CPT record. Linked CPT will show up as taxonomies for this CPT.

Example json file:
```json
[
  {
    "name": "Events",
    "args": {
        "use_twig": true,
        "related_cpt": [],
        "label": "Events",
        "labels": {
            "name": "Events",
            "singular_name": "Event",
            "menu_name": "Events",
            "parent_item_colon": "Parent Events:",
            "all_items": "All Events",
            "view_item": "View Events",
            "add_new_item": "Select Events:",
            "add_new": "Add New",
            "edit_item": "Edit Events",
            "update_item": "Update Events",
            "search_items": "Search Events",
            "not_found": "Event Not Found",
            "not_found_in_trash": "Event not found in Trash"
        },
        "description": "Conference Events",
        "supports": [
            "title",
            "thumbnail",
            "editor",
            "custom-fields",
            "comments"
        ],
        "hierarchical": false,
        "public": true,
        "show_ui": true,
        "show_in_menu": true,
        "show_in_nav_menus": true,
        "show_in_admin_bar": true,
        "menu_position": 4,
        "can_export": true,
        "has_archive": true,
        "exclude_from_search": true,
        "publicly_queryable": true
    }
  }
]
```
#### Taxonomy
Extra Options:
- show_as_admin_filter: defaults to true. Will add a filter to the top of the standard WP CPT page for you.
- show_admin_column: defualts to true. Will add a column to the affected CPT
- show_in_graphql: (not shown) defaults true. is needed to show up a CPT in WP-GraphQL plugin
- graphql_single_name: (not shown) defaults to inflector class' computed singular name.
- graphql_plural_name: (not shown) defaults to inflector class' computed plural name.

```json
[
  {
    "name": "Event Day",
    "post_types": [
      "event"
    ],
    "labels": [],
    "options": {
      "show_in_admin_bar": "true",
      "show_admin_column": "true",
      "show_as_admin_filter": "true"
    },
    "defaults": [
      "6/1",
      "6/2",
      "6/3",
      "6/4"
    ]
  },
  {
    "name": "Event Slot",
    "post_types": [
      "event"
    ],
    "labels": [],
    "options": {
      "show_in_admin_bar": "true",
      "show_admin_column": "true",
      "show_as_admin_filter": "true"
    },
    "defaults": [
      "9am - 10am",
      "11am - 12pm",
      "12pm - 1pm",
      "1pm - 2pm",
      "2pm - 3pm"
    ]
  }
]

```
## Using Twig Templates
WDK uses Timber plugin as a nifty way of implementing Twig on WordPress as such, WDK also uses The Timber Starter Theme as it's default backup theme (located at https://github.com/timber/starter-theme/ under MIT license).

Twig templates load when two conditions are met. 
1) process_template_TEMPLATENAME option or post_meta is set to true (use_twig does this for you). 
2) And When Timber finds an adequate matching template in it's location paths. 

On Twig load a context hook (wdk_context_TEMPLATENAME) is fired which you can use to add data to your template. 

```php
add_filter('wdk_context_templatename', function($context) {
    $context['my new data'] = "something here";
    return $context;
});
```