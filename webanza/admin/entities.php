<?php
/**
 * Content types editable from the admin panel. Adding a field here is all
 * it takes to make it editable (the column must exist in the database).
 *
 * Field types: text, textarea, rich, lines, image, number, price, checkbox,
 *              select, icon, url, email, date
 */
if (!defined('ROOT_PATH')) {
    exit;
}

function entities(): array
{
    $active = ['type' => 'checkbox', 'label' => 'Visible on website', 'default' => 1];
    $sort   = ['type' => 'number', 'label' => 'Sort order', 'default' => 0, 'help' => 'Lower numbers show first. You can also drag rows in the list.'];

    return [
        'services' => [
            'label' => 'Services', 'singular' => 'Service', 'icon' => 'fa-solid fa-layer-group',
            'title' => 'title', 'slug' => 'title', 'list' => ['icon' => 'Icon', 'title' => 'Title', 'short_desc' => 'Summary'],
            'fields' => [
                'title'       => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'slug'        => ['type' => 'text', 'label' => 'URL slug', 'help' => 'Leave empty to generate from the title.'],
                'icon'        => ['type' => 'icon', 'label' => 'Icon', 'default' => 'fa-solid fa-code'],
                'short_desc'  => ['type' => 'textarea', 'label' => 'Short description', 'help' => 'Shown on service cards and menus.'],
                'description' => ['type' => 'rich', 'label' => 'Full description'],
                'features'    => ['type' => 'lines', 'label' => "What's included", 'help' => 'One item per line.'],
                'image'       => ['type' => 'image', 'label' => 'Cover image'],
                'sort_order'  => $sort,
                'is_active'   => $active,
            ],
        ],
        'package_categories' => [
            'label' => 'Package Categories', 'singular' => 'Package Category', 'icon' => 'fa-solid fa-folder-tree',
            'title' => 'name', 'slug' => 'name', 'list' => ['icon' => 'Icon', 'name' => 'Name', 'service_id' => 'Linked service'],
            'fields' => [
                'name'        => ['type' => 'text', 'label' => 'Name (tab title)', 'required' => true],
                'slug'        => ['type' => 'text', 'label' => 'URL slug', 'help' => 'Used in links like packages.php?cat=slug'],
                'icon'        => ['type' => 'icon', 'label' => 'Icon', 'default' => 'fa-solid fa-box'],
                'service_id'  => ['type' => 'select', 'label' => 'Linked service', 'source' => 'services', 'help' => 'These packages also appear on that service page.'],
                'description' => ['type' => 'textarea', 'label' => 'Short description'],
                'sort_order'  => $sort,
                'is_active'   => $active,
            ],
        ],
        'packages' => [
            'label' => 'Packages & Pricing', 'singular' => 'Package', 'icon' => 'fa-solid fa-tags',
            'title' => 'name', 'list' => ['name' => 'Package', 'category_id' => 'Category', 'price' => 'Price', 'is_featured' => 'Featured'],
            'filter' => 'category_id', 'order' => 'category_id, sort_order, id', 'drag' => true,
            'fields' => [
                'category_id'   => ['type' => 'select', 'label' => 'Category', 'source' => 'package_categories', 'required' => true],
                'name'          => ['type' => 'text', 'label' => 'Package name', 'required' => true],
                'tagline'       => ['type' => 'text', 'label' => 'Tagline'],
                'price'         => ['type' => 'price', 'label' => 'Price', 'help' => 'Set 0 to show "Custom".', 'default' => 0],
                'old_price'     => ['type' => 'price', 'label' => 'Old price (crossed out)', 'help' => 'Optional — shows a "Save X%" badge.'],
                'price_suffix'  => ['type' => 'text', 'label' => 'Price note', 'help' => 'e.g. one-time, /month, starting at, custom quote'],
                'delivery_time' => ['type' => 'text', 'label' => 'Delivery time'],
                'features'      => ['type' => 'lines', 'label' => 'Features', 'help' => 'One feature per line.'],
                'badge'         => ['type' => 'text', 'label' => 'Badge', 'help' => 'e.g. Most Popular, Best Value'],
                'cta_text'      => ['type' => 'text', 'label' => 'Button text', 'default' => 'Get Started'],
                'is_featured'   => ['type' => 'checkbox', 'label' => 'Highlight this package (dark card)'],
                'sort_order'    => $sort,
                'is_active'     => $active,
            ],
        ],
        'portfolio' => [
            'label' => 'Portfolio', 'singular' => 'Project', 'icon' => 'fa-solid fa-briefcase',
            'title' => 'title', 'slug' => 'title', 'list' => ['image' => 'Image', 'title' => 'Project', 'category' => 'Category', 'is_featured' => 'Featured'],
            'fields' => [
                'title'       => ['type' => 'text', 'label' => 'Project title', 'required' => true],
                'slug'        => ['type' => 'text', 'label' => 'URL slug'],
                'category'    => ['type' => 'text', 'label' => 'Category', 'help' => 'e.g. E-commerce, Mobile App, SaaS, Graphic Design — used for filters.'],
                'client'      => ['type' => 'text', 'label' => 'Client'],
                'image'       => ['type' => 'image', 'label' => 'Cover image'],
                'summary'     => ['type' => 'textarea', 'label' => 'Summary'],
                'description' => ['type' => 'rich', 'label' => 'Case study'],
                'tech'        => ['type' => 'text', 'label' => 'Technologies', 'help' => 'Comma separated.'],
                'project_url' => ['type' => 'url', 'label' => 'Live URL'],
                'is_featured' => ['type' => 'checkbox', 'label' => 'Show on home page first'],
                'sort_order'  => $sort,
                'is_active'   => $active,
            ],
        ],
        'team' => [
            'label' => 'Team', 'singular' => 'Team Member', 'icon' => 'fa-solid fa-users',
            'title' => 'name', 'list' => ['photo' => 'Photo', 'name' => 'Name', 'role' => 'Role'],
            'fields' => [
                'name'       => ['type' => 'text', 'label' => 'Name', 'required' => true],
                'role'       => ['type' => 'text', 'label' => 'Role'],
                'photo'      => ['type' => 'image', 'label' => 'Photo'],
                'bio'        => ['type' => 'textarea', 'label' => 'Short bio'],
                'linkedin'   => ['type' => 'url', 'label' => 'LinkedIn URL'],
                'facebook'   => ['type' => 'url', 'label' => 'Facebook URL'],
                'email'      => ['type' => 'email', 'label' => 'Email'],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'testimonials' => [
            'label' => 'Testimonials', 'singular' => 'Testimonial', 'icon' => 'fa-solid fa-comment-dots',
            'title' => 'name', 'list' => ['name' => 'Client', 'role' => 'Role', 'rating' => 'Rating'],
            'fields' => [
                'name'       => ['type' => 'text', 'label' => 'Client name', 'required' => true],
                'role'       => ['type' => 'text', 'label' => 'Role / company'],
                'photo'      => ['type' => 'image', 'label' => 'Photo'],
                'content'    => ['type' => 'textarea', 'label' => 'Review', 'required' => true],
                'rating'     => ['type' => 'number', 'label' => 'Rating (1–5)', 'default' => 5, 'min' => 1, 'max' => 5],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'faqs' => [
            'label' => 'FAQs', 'singular' => 'FAQ', 'icon' => 'fa-solid fa-circle-question',
            'title' => 'question', 'list' => ['question' => 'Question'],
            'fields' => [
                'question'   => ['type' => 'text', 'label' => 'Question', 'required' => true],
                'answer'     => ['type' => 'textarea', 'label' => 'Answer', 'required' => true],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'posts' => [
            'label' => 'Blog Posts', 'singular' => 'Post', 'icon' => 'fa-solid fa-newspaper',
            'title' => 'title', 'slug' => 'title', 'order' => 'published_at DESC, id DESC',
            'list' => ['image' => 'Image', 'title' => 'Title', 'category' => 'Category', 'published_at' => 'Published'],
            'fields' => [
                'title'        => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'slug'         => ['type' => 'text', 'label' => 'URL slug'],
                'category'     => ['type' => 'text', 'label' => 'Category'],
                'image'        => ['type' => 'image', 'label' => 'Featured image'],
                'excerpt'      => ['type' => 'textarea', 'label' => 'Excerpt'],
                'content'      => ['type' => 'rich', 'label' => 'Content'],
                'author'       => ['type' => 'text', 'label' => 'Author', 'default' => 'Webanza Tech'],
                'published_at' => ['type' => 'date', 'label' => 'Publish date', 'default' => date('Y-m-d')],
                'is_active'    => $active,
            ],
        ],
        'pages' => [
            'label' => 'Pages', 'singular' => 'Page', 'icon' => 'fa-solid fa-file-lines',
            'title' => 'title', 'slug' => 'title', 'list' => ['title' => 'Title', 'slug' => 'Slug'],
            'fields' => [
                'title'      => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'slug'       => ['type' => 'text', 'label' => 'URL slug'],
                'subtitle'   => ['type' => 'text', 'label' => 'Subtitle'],
                'content'    => ['type' => 'rich', 'label' => 'Content'],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'clients' => [
            'label' => 'Technologies', 'singular' => 'Technology / Partner', 'icon' => 'fa-solid fa-microchip',
            'title' => 'name', 'list' => ['icon' => 'Icon', 'name' => 'Name'],
            'fields' => [
                'name'       => ['type' => 'text', 'label' => 'Name', 'required' => true],
                'icon'       => ['type' => 'icon', 'label' => 'Icon', 'help' => 'Used when no logo image is uploaded.'],
                'logo'       => ['type' => 'image', 'label' => 'Logo image (optional)'],
                'link'       => ['type' => 'url', 'label' => 'Link'],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'stats' => [
            'label' => 'Counters', 'singular' => 'Counter', 'icon' => 'fa-solid fa-chart-simple',
            'title' => 'label', 'list' => ['icon' => 'Icon', 'label' => 'Label', 'value' => 'Value'],
            'fields' => [
                'label'      => ['type' => 'text', 'label' => 'Label', 'required' => true],
                'value'      => ['type' => 'number', 'label' => 'Number', 'default' => 0],
                'suffix'     => ['type' => 'text', 'label' => 'Suffix', 'help' => 'e.g. + or %'],
                'icon'       => ['type' => 'icon', 'label' => 'Icon'],
                'sort_order' => $sort,
                'is_active'  => $active,
            ],
        ],
        'process_steps' => [
            'label' => 'Process Steps', 'singular' => 'Step', 'icon' => 'fa-solid fa-route',
            'title' => 'title', 'list' => ['icon' => 'Icon', 'title' => 'Step', 'description' => 'Description'],
            'fields' => [
                'title'       => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'description' => ['type' => 'textarea', 'label' => 'Description'],
                'icon'        => ['type' => 'icon', 'label' => 'Icon'],
                'sort_order'  => $sort,
                'is_active'   => $active,
            ],
        ],
    ];
}

/** Options for select fields that point to another table. */
function entity_options(string $table): array
{
    $label = ['services' => 'title', 'package_categories' => 'name'][$table] ?? null;
    if (!$label) {
        return [];
    }
    $out = [];
    foreach (rows("SELECT id, `$label` AS l FROM `$table` ORDER BY sort_order, id") as $r) {
        $out[$r['id']] = $r['l'];
    }
    return $out;
}

/** Common Font Awesome icons offered in the icon picker. */
function icon_choices(): array
{
    return [
        'fa-solid fa-cart-shopping', 'fa-solid fa-bag-shopping', 'fa-solid fa-store', 'fa-solid fa-cloud', 'fa-solid fa-code',
        'fa-solid fa-laptop-code', 'fa-solid fa-globe', 'fa-solid fa-mobile-screen-button', 'fa-brands fa-apple', 'fa-brands fa-android',
        'fa-brands fa-app-store-ios', 'fa-brands fa-google-play', 'fa-solid fa-pen-nib', 'fa-solid fa-palette', 'fa-solid fa-pen-ruler',
        'fa-solid fa-film', 'fa-solid fa-video', 'fa-solid fa-clapperboard', 'fa-solid fa-magnifying-glass-chart', 'fa-solid fa-ranking-star',
        'fa-brands fa-google', 'fa-solid fa-chart-line', 'fa-solid fa-chart-pie', 'fa-solid fa-bullhorn', 'fa-solid fa-rocket',
        'fa-solid fa-lightbulb', 'fa-solid fa-gears', 'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-shield-halved',
        'fa-solid fa-lock', 'fa-solid fa-headset', 'fa-solid fa-envelope', 'fa-solid fa-users', 'fa-solid fa-user-tie',
        'fa-solid fa-star', 'fa-solid fa-face-smile', 'fa-solid fa-earth-asia', 'fa-solid fa-trophy', 'fa-solid fa-box',
        'fa-solid fa-folder-tree', 'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-camera', 'fa-solid fa-hashtag', 'fa-brands fa-wordpress',
        'fa-brands fa-shopify', 'fa-brands fa-laravel', 'fa-brands fa-react', 'fa-brands fa-node-js', 'fa-brands fa-php',
        'fa-brands fa-figma', 'fa-brands fa-aws', 'fa-brands fa-stripe', 'fa-brands fa-meta', 'fa-brands fa-python',
        'fa-brands fa-facebook-f', 'fa-brands fa-instagram', 'fa-brands fa-youtube', 'fa-brands fa-tiktok', 'fa-brands fa-linkedin-in',
    ];
}
