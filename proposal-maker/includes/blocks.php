<?php
/**
 * WIDGETS (content blocks) — the things you drag onto a page.
 *
 * Part 1: pm_blocks()  the list of all widgets. For each widget:
 *           'defaults' = starting content
 *           'fields'   = the settings form shown in the editor's right panel
 *                        (t = field type: text, textarea, rich, select, toggle, image, icon, repeater...)
 *
 * Part 2: pm_render_<name>()  one function per widget that returns its HTML,
 *           e.g. pm_render_text(), pm_render_pricing_table().
 *
 * Note: we use <table> for columns instead of CSS flexbox/grid, because the
 * PDF engine (Dompdf) does not understand flexbox/grid.
 */

function pm_blocks()
{
    static $b = null;
    if ($b !== null) return $b;

    $align = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'];
    $cols  = ['1' => '1 column', '2' => '2 columns', '3' => '3 columns', '4' => '4 columns'];
    $colorRef = ['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'heading' => 'Heading', 'text' => 'Text', 'white' => 'White'];

    $b = [
        'heading' => [
            'label' => 'Heading', 'icon' => 'bi-type-h1', 'group' => 'Basic',
            'defaults' => ['eyebrow' => '', 'text' => 'Section Heading', 'subtitle' => '', 'level' => 'h2', 'style' => 'template', 'number' => ''],
            'fields' => [
                ['k' => 'eyebrow', 't' => 'text', 'l' => 'Small label above (eyebrow)'],
                ['k' => 'text', 't' => 'text', 'l' => 'Heading text'],
                ['k' => 'subtitle', 't' => 'textarea', 'l' => 'Subtitle / intro'],
                ['k' => 'level', 't' => 'select', 'l' => 'Size', 'o' => ['h1' => 'Extra large (H1)', 'h2' => 'Large (H2)', 'h3' => 'Medium (H3)', 'h4' => 'Small (H4)']],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['template' => 'Template style', 'plain' => 'Plain', 'numbered' => 'Big number', 'boxed' => 'Highlighted box']],
                ['k' => 'number', 't' => 'text', 'l' => 'Number (for "Big number" style)'],
            ],
        ],
        'text' => [
            'label' => 'Text', 'icon' => 'bi-text-paragraph', 'group' => 'Basic',
            'defaults' => ['html' => '<p>Start writing your content here. Use <b>{{client_name}}</b> and other variables to personalise the proposal.</p>', 'size' => 0, 'line_height' => 0, 'columns' => '1'],
            'fields' => [
                ['k' => 'html', 't' => 'rich', 'l' => 'Content'],
                ['k' => 'size', 't' => 'number', 'l' => 'Base font size (px, 0 = default)', 'min' => 0, 'max' => 30],
                ['k' => 'line_height', 't' => 'number', 'l' => 'Line height (0 = default)', 'min' => 0, 'max' => 3, 'step' => 0.1],
            ],
        ],
        'image' => [
            'label' => 'Image', 'icon' => 'bi-image', 'group' => 'Media',
            'defaults' => ['src' => '', 'alt' => '', 'caption' => '', 'width' => 100, 'height' => 0, 'radius' => 8, 'align' => 'center', 'border' => false, 'fit' => 'crop'],
            'fields' => [
                ['k' => 'src', 't' => 'image', 'l' => 'Image (upload or URL)'],
                ['k' => 'alt', 't' => 'text', 'l' => 'Alt text'],
                ['k' => 'caption', 't' => 'text', 'l' => 'Caption'],
                ['k' => 'width', 't' => 'range', 'l' => 'Width (%)', 'min' => 10, 'max' => 100],
                ['k' => 'height', 't' => 'number', 'l' => 'Height (px, 0 = auto)', 'min' => 0, 'max' => 900],
                ['k' => 'radius', 't' => 'range', 'l' => 'Border radius', 'min' => 0, 'max' => 60],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
                ['k' => 'border', 't' => 'toggle', 'l' => 'Show border'],
            ],
        ],
        'image_text' => [
            'label' => 'Image + Text', 'icon' => 'bi-layout-sidebar-inset', 'group' => 'Media',
            'defaults' => ['src' => '', 'side' => 'left', 'img_width' => 42, 'img_height' => 0, 'radius' => 12, 'title' => 'About Our Company', 'html' => '<p>We are a passionate team of designers, developers and marketers helping businesses grow online.</p>'],
            'fields' => [
                ['k' => 'src', 't' => 'image', 'l' => 'Image'],
                ['k' => 'side', 't' => 'select', 'l' => 'Image position', 'o' => ['left' => 'Left', 'right' => 'Right']],
                ['k' => 'img_width', 't' => 'range', 'l' => 'Image column width (%)', 'min' => 25, 'max' => 60],
                ['k' => 'img_height', 't' => 'number', 'l' => 'Image height (px, 0 = auto)', 'min' => 0, 'max' => 700],
                ['k' => 'radius', 't' => 'range', 'l' => 'Image radius', 'min' => 0, 'max' => 60],
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'html', 't' => 'rich', 'l' => 'Text'],
            ],
        ],
        'logo' => [
            'label' => 'Logo', 'icon' => 'bi-badge-tm', 'group' => 'Media',
            'defaults' => ['height' => 56, 'align' => 'left', 'variant' => 'auto'],
            'fields' => [
                ['k' => 'height', 't' => 'range', 'l' => 'Logo height (px)', 'min' => 20, 'max' => 200],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
                ['k' => 'variant', 't' => 'select', 'l' => 'Version', 'o' => ['auto' => 'Automatic', 'dark' => 'Normal logo', 'light' => 'Light logo']],
            ],
        ],
        'button' => [
            'label' => 'Button', 'icon' => 'bi-hand-index', 'group' => 'Basic',
            'defaults' => ['text' => 'Visit Our Website', 'url' => '{{company_website}}', 'align' => 'left', 'style' => 'solid', 'size' => 'md'],
            'fields' => [
                ['k' => 'text', 't' => 'text', 'l' => 'Button text'],
                ['k' => 'url', 't' => 'text', 'l' => 'Link URL'],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['solid' => 'Solid', 'outline' => 'Outline', 'accent' => 'Accent']],
                ['k' => 'size', 't' => 'select', 'l' => 'Size', 'o' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
            ],
        ],
        'divider' => [
            'label' => 'Divider', 'icon' => 'bi-hr', 'group' => 'Layout',
            'defaults' => ['style' => 'solid', 'thickness' => 1, 'width' => 100, 'color' => 'border', 'align' => 'center'],
            'fields' => [
                ['k' => 'style', 't' => 'select', 'l' => 'Line style', 'o' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'accent' => 'Accent bar']],
                ['k' => 'thickness', 't' => 'range', 'l' => 'Thickness', 'min' => 1, 'max' => 12],
                ['k' => 'width', 't' => 'range', 'l' => 'Width (%)', 'min' => 5, 'max' => 100],
                ['k' => 'color', 't' => 'select', 'l' => 'Colour', 'o' => ['border' => 'Border', 'primary' => 'Primary', 'accent' => 'Accent', 'heading' => 'Heading']],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
            ],
        ],
        'spacer' => [
            'label' => 'Spacer', 'icon' => 'bi-arrows-vertical', 'group' => 'Layout',
            'defaults' => ['height' => 32],
            'fields' => [['k' => 'height', 't' => 'range', 'l' => 'Height (px)', 'min' => 4, 'max' => 400]],
        ],
        'icon' => [
            'label' => 'Icon', 'icon' => 'bi-star', 'group' => 'Basic',
            'defaults' => ['icon' => 'rocket-takeoff', 'size' => 40, 'color' => 'primary', 'align' => 'left', 'circle' => true, 'label' => '', 'text' => ''],
            'fields' => [
                ['k' => 'icon', 't' => 'icon', 'l' => 'Icon'],
                ['k' => 'size', 't' => 'range', 'l' => 'Size', 'min' => 16, 'max' => 120],
                ['k' => 'color', 't' => 'select', 'l' => 'Colour', 'o' => $colorRef],
                ['k' => 'circle', 't' => 'toggle', 'l' => 'Soft background circle'],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
                ['k' => 'label', 't' => 'text', 'l' => 'Title (optional)'],
                ['k' => 'text', 't' => 'textarea', 'l' => 'Text (optional)'],
            ],
        ],
        'list' => [
            'label' => 'Bullet List', 'icon' => 'bi-list-check', 'group' => 'Content',
            'defaults' => ['title' => '', 'marker' => 'check', 'columns' => '1', 'items' => [['text' => 'Responsive design for every device'], ['text' => 'SEO-friendly structure'], ['text' => 'Fast loading pages']]],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title (optional)'],
                ['k' => 'marker', 't' => 'select', 'l' => 'Bullet style', 'o' => ['check' => 'Check', 'circle-check' => 'Check in circle', 'arrow' => 'Arrow', 'dot' => 'Dot', 'star' => 'Star']],
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => ['1' => '1 column', '2' => '2 columns', '3' => '3 columns']],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Items', 'add' => 'Add item', 'title' => 'text', 'fields' => [['k' => 'text', 't' => 'textarea', 'l' => 'Text']]],
            ],
        ],
        'numbered_list' => [
            'label' => 'Numbered List', 'icon' => 'bi-list-ol', 'group' => 'Content',
            'defaults' => ['title' => '', 'style' => 'circle', 'items' => [['text' => 'Discovery & requirement gathering'], ['text' => 'Design & prototyping'], ['text' => 'Development & testing']]],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title (optional)'],
                ['k' => 'style', 't' => 'select', 'l' => 'Number style', 'o' => ['circle' => 'Filled circle', 'plain' => 'Plain numbers', 'square' => 'Outlined square']],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Items', 'add' => 'Add item', 'title' => 'text', 'fields' => [['k' => 'text', 't' => 'textarea', 'l' => 'Text']]],
            ],
        ],
        'quote' => [
            'label' => 'Quote', 'icon' => 'bi-quote', 'group' => 'Content',
            'defaults' => ['text' => 'Great design is not just what it looks like — it is how it works.', 'author' => '', 'role' => '', 'style' => 'bar'],
            'fields' => [
                ['k' => 'text', 't' => 'textarea', 'l' => 'Quote'],
                ['k' => 'author', 't' => 'text', 'l' => 'Author'],
                ['k' => 'role', 't' => 'text', 'l' => 'Author role / company'],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['bar' => 'Side bar', 'big' => 'Large centered', 'card' => 'Card']],
            ],
        ],
        'feature_cards' => [
            'label' => 'Feature Cards', 'icon' => 'bi-grid-3x2-gap', 'group' => 'Cards',
            'defaults' => ['columns' => '3', 'style' => 'card', 'align' => 'left', 'items' => [
                ['icon' => 'speedometer2', 'title' => 'Fast Performance', 'text' => 'Optimised code and assets for lightning-fast load times.'],
                ['icon' => 'phone', 'title' => 'Mobile First', 'text' => 'Pixel-perfect layouts on phones, tablets and desktops.'],
                ['icon' => 'shield-check', 'title' => 'Secure', 'text' => 'Industry best practices to keep your data safe.'],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => $cols],
                ['k' => 'style', 't' => 'select', 'l' => 'Card style', 'o' => ['card' => 'Soft card', 'bordered' => 'Bordered', 'filled' => 'Filled (primary)', 'plain' => 'Plain']],
                ['k' => 'align', 't' => 'select', 'l' => 'Text alignment', 'o' => $align],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Cards', 'add' => 'Add card', 'title' => 'title', 'fields' => [
                    ['k' => 'icon', 't' => 'icon', 'l' => 'Icon'],
                    ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                    ['k' => 'text', 't' => 'textarea', 'l' => 'Text'],
                ]],
            ],
        ],
        'service_cards' => [
            'label' => 'Service Cards', 'icon' => 'bi-briefcase', 'group' => 'Cards',
            'defaults' => ['columns' => '2', 'show_price' => true, 'items' => [
                ['icon' => 'code-slash', 'title' => 'Web Development', 'description' => 'Modern responsive websites built for performance, SEO and conversions.', 'features' => "Responsive Design\nCMS Integration\nSEO Friendly\nPerformance Optimization", 'price' => 'From $1,500', 'bg' => '', 'color' => ''],
                ['icon' => 'graph-up-arrow', 'title' => 'SEO Services', 'description' => 'Rank higher on Google and attract qualified organic traffic.', 'features' => "Keyword Research\nTechnical SEO\nContent Strategy\nMonthly Reports", 'price' => '$400 / month', 'bg' => '', 'color' => ''],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => $cols],
                ['k' => 'show_price', 't' => 'toggle', 'l' => 'Show price'],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Services', 'add' => 'Add service', 'title' => 'title', 'fields' => [
                    ['k' => 'icon', 't' => 'icon', 'l' => 'Icon'],
                    ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                    ['k' => 'description', 't' => 'textarea', 'l' => 'Description'],
                    ['k' => 'features', 't' => 'textarea', 'l' => 'Features (one per line)'],
                    ['k' => 'price', 't' => 'text', 'l' => 'Price'],
                    ['k' => 'bg', 't' => 'color', 'l' => 'Card background (optional)'],
                    ['k' => 'color', 't' => 'color', 'l' => 'Accent colour (optional)'],
                ]],
            ],
        ],
        'pricing_table' => [
            'label' => 'Pricing Table', 'icon' => 'bi-receipt', 'group' => 'Business',
            'defaults' => ['title' => 'Investment', 'show_description' => true, 'show_qty' => true, 'discount_type' => 'percent', 'discount' => 0, 'tax_rate' => 0, 'tax_label' => 'Tax', 'note' => 'All prices are in {{currency}}.', 'rows' => [
                ['service' => 'Website Design', 'description' => '5 custom designed pages', 'qty' => 1, 'price' => 500],
                ['service' => 'SEO Setup', 'description' => 'On-page SEO & analytics', 'qty' => 1, 'price' => 300],
            ]],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'rows', 't' => 'repeater', 'l' => 'Line items', 'add' => 'Add line item', 'title' => 'service', 'fields' => [
                    ['k' => 'service', 't' => 'text', 'l' => 'Service'],
                    ['k' => 'description', 't' => 'text', 'l' => 'Description'],
                    ['k' => 'qty', 't' => 'number', 'l' => 'Qty', 'min' => 0, 'step' => 1],
                    ['k' => 'price', 't' => 'number', 'l' => 'Unit price', 'min' => 0, 'step' => 0.01],
                ]],
                ['k' => 'show_description', 't' => 'toggle', 'l' => 'Show description column'],
                ['k' => 'show_qty', 't' => 'toggle', 'l' => 'Show quantity column'],
                ['k' => 'discount_type', 't' => 'select', 'l' => 'Discount type', 'o' => ['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount']],
                ['k' => 'discount', 't' => 'number', 'l' => 'Discount', 'min' => 0, 'step' => 0.01],
                ['k' => 'tax_label', 't' => 'text', 'l' => 'Tax label'],
                ['k' => 'tax_rate', 't' => 'number', 'l' => 'Tax rate (%)', 'min' => 0, 'step' => 0.01],
                ['k' => 'note', 't' => 'textarea', 'l' => 'Note below table'],
            ],
        ],
        'comparison_table' => [
            'label' => 'Comparison Table', 'icon' => 'bi-layout-three-columns', 'group' => 'Business',
            'defaults' => ['title' => 'Choose Your Package', 'headers' => 'Basic | Standard | Premium', 'prices' => '$499 | $999 | $1,999', 'highlight' => 2, 'rows' => [
                ['feature' => 'Number of pages', 'values' => '5 | 10 | Unlimited'],
                ['feature' => 'Responsive design', 'values' => 'yes | yes | yes'],
                ['feature' => 'SEO optimisation', 'values' => 'no | yes | yes'],
                ['feature' => 'E-commerce', 'values' => 'no | no | yes'],
            ]],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'headers', 't' => 'text', 'l' => 'Package names (separated by |)'],
                ['k' => 'prices', 't' => 'text', 'l' => 'Package prices (separated by |, optional)'],
                ['k' => 'highlight', 't' => 'number', 'l' => 'Highlight column (0 = none)', 'min' => 0, 'max' => 6],
                ['k' => 'rows', 't' => 'repeater', 'l' => 'Rows', 'add' => 'Add row', 'title' => 'feature', 'fields' => [
                    ['k' => 'feature', 't' => 'text', 'l' => 'Feature'],
                    ['k' => 'values', 't' => 'text', 'l' => 'Values separated by | (use yes / no for icons)'],
                ]],
            ],
        ],
        'table' => [
            'label' => 'Table', 'icon' => 'bi-table', 'group' => 'Business',
            'defaults' => ['title' => '', 'headers' => 'Item, Details, Notes', 'striped' => true, 'rows' => [['cells' => 'Domain | yourcompany.com | Provided by client'], ['cells' => 'Hosting | Cloud VPS | Managed by us']]],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title (optional)'],
                ['k' => 'headers', 't' => 'text', 'l' => 'Column headers (comma separated)'],
                ['k' => 'striped', 't' => 'toggle', 'l' => 'Striped rows'],
                ['k' => 'rows', 't' => 'repeater', 'l' => 'Rows', 'add' => 'Add row', 'title' => 'cells', 'fields' => [['k' => 'cells', 't' => 'text', 'l' => 'Cells separated by |']]],
            ],
        ],
        'stats' => [
            'label' => 'Statistics', 'icon' => 'bi-bar-chart-line', 'group' => 'Cards',
            'defaults' => ['columns' => '4', 'style' => 'card', 'items' => [
                ['value' => '250+', 'label' => 'Projects Delivered', 'icon' => 'trophy'],
                ['value' => '98%', 'label' => 'Client Satisfaction', 'icon' => 'emoji-smile'],
                ['value' => '10+', 'label' => 'Years Experience', 'icon' => 'award'],
                ['value' => '24/7', 'label' => 'Support', 'icon' => 'chat-dots'],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => $cols],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['card' => 'Cards', 'filled' => 'Filled (primary)', 'plain' => 'Plain numbers', 'divided' => 'Divided']],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Statistics', 'add' => 'Add statistic', 'title' => 'label', 'fields' => [
                    ['k' => 'value', 't' => 'text', 'l' => 'Value'],
                    ['k' => 'label', 't' => 'text', 'l' => 'Label'],
                    ['k' => 'icon', 't' => 'icon', 'l' => 'Icon (optional)'],
                ]],
            ],
        ],
        'testimonial' => [
            'label' => 'Testimonial', 'icon' => 'bi-chat-quote', 'group' => 'Cards',
            'defaults' => ['columns' => '2', 'items' => [
                ['quote' => 'They delivered our website ahead of schedule and our leads doubled within three months.', 'name' => 'Sarah Johnson', 'role' => 'CEO, Bright Co.', 'photo' => '', 'rating' => 5],
                ['quote' => 'Professional, responsive and incredibly talented. Highly recommended!', 'name' => 'Ahmed Khan', 'role' => 'Founder, Nova Labs', 'photo' => '', 'rating' => 5],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => ['1' => '1 column', '2' => '2 columns', '3' => '3 columns']],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Testimonials', 'add' => 'Add testimonial', 'title' => 'name', 'fields' => [
                    ['k' => 'quote', 't' => 'textarea', 'l' => 'Quote'],
                    ['k' => 'name', 't' => 'text', 'l' => 'Name'],
                    ['k' => 'role', 't' => 'text', 'l' => 'Role / company'],
                    ['k' => 'photo', 't' => 'image', 'l' => 'Photo'],
                    ['k' => 'rating', 't' => 'number', 'l' => 'Rating (0-5)', 'min' => 0, 'max' => 5],
                ]],
            ],
        ],
        'team' => [
            'label' => 'Team Member', 'icon' => 'bi-people', 'group' => 'Cards',
            'defaults' => ['columns' => '3', 'items' => [
                ['photo' => '', 'name' => 'Alex Morgan', 'role' => 'Project Manager', 'bio' => '10+ years leading digital projects.'],
                ['photo' => '', 'name' => 'Sam Lee', 'role' => 'Lead Designer', 'bio' => 'Crafts beautiful, user-centred interfaces.'],
                ['photo' => '', 'name' => 'Jordan Ray', 'role' => 'Senior Developer', 'bio' => 'Full-stack engineer and performance nerd.'],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Columns', 'o' => $cols],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Team members', 'add' => 'Add member', 'title' => 'name', 'fields' => [
                    ['k' => 'photo', 't' => 'image', 'l' => 'Photo'],
                    ['k' => 'name', 't' => 'text', 'l' => 'Name'],
                    ['k' => 'role', 't' => 'text', 'l' => 'Role'],
                    ['k' => 'bio', 't' => 'textarea', 'l' => 'Short bio'],
                ]],
            ],
        ],
        'timeline' => [
            'label' => 'Timeline', 'icon' => 'bi-calendar-range', 'group' => 'Business',
            'defaults' => ['items' => [
                ['date' => 'Week 1', 'title' => 'Discovery', 'text' => 'Requirements, research and project planning.'],
                ['date' => 'Week 2-3', 'title' => 'Design', 'text' => 'Wireframes, UI design and approvals.'],
                ['date' => 'Week 4-6', 'title' => 'Development', 'text' => 'Front-end and back-end development.'],
                ['date' => 'Week 7', 'title' => 'Launch', 'text' => 'Testing, deployment and handover.'],
            ]],
            'fields' => [
                ['k' => 'items', 't' => 'repeater', 'l' => 'Milestones', 'add' => 'Add milestone', 'title' => 'title', 'fields' => [
                    ['k' => 'date', 't' => 'text', 'l' => 'Date / phase'],
                    ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                    ['k' => 'text', 't' => 'textarea', 'l' => 'Description'],
                ]],
            ],
        ],
        'process' => [
            'label' => 'Process Steps', 'icon' => 'bi-diagram-3', 'group' => 'Business',
            'defaults' => ['columns' => '4', 'items' => [
                ['icon' => 'search', 'title' => 'Discover', 'text' => 'We learn about your goals and audience.'],
                ['icon' => 'palette', 'title' => 'Design', 'text' => 'We craft the look and experience.'],
                ['icon' => 'code-slash', 'title' => 'Develop', 'text' => 'We build, test and optimise.'],
                ['icon' => 'rocket-takeoff', 'title' => 'Launch', 'text' => 'We go live and support growth.'],
            ]],
            'fields' => [
                ['k' => 'columns', 't' => 'select', 'l' => 'Steps per row', 'o' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5']],
                ['k' => 'items', 't' => 'repeater', 'l' => 'Steps', 'add' => 'Add step', 'title' => 'title', 'fields' => [
                    ['k' => 'icon', 't' => 'icon', 'l' => 'Icon'],
                    ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                    ['k' => 'text', 't' => 'textarea', 'l' => 'Text'],
                ]],
            ],
        ],
        'faq' => [
            'label' => 'FAQ', 'icon' => 'bi-question-circle', 'group' => 'Content',
            'defaults' => ['items' => [
                ['q' => 'How long will the project take?', 'a' => 'Most projects are completed within 4–8 weeks depending on scope.'],
                ['q' => 'Do you provide support after launch?', 'a' => 'Yes, every project includes 30 days of free support.'],
            ]],
            'fields' => [
                ['k' => 'items', 't' => 'repeater', 'l' => 'Questions', 'add' => 'Add question', 'title' => 'q', 'fields' => [
                    ['k' => 'q', 't' => 'text', 'l' => 'Question'],
                    ['k' => 'a', 't' => 'textarea', 'l' => 'Answer'],
                ]],
            ],
        ],
        'signature' => [
            'label' => 'Signature', 'icon' => 'bi-pen', 'group' => 'Business',
            'defaults' => ['title' => 'Agreement & Signatures', 'text' => 'By signing below, both parties agree to the scope, pricing and terms described in this proposal.', 'left_label' => 'For {{company_name}}', 'left_name' => '', 'left_role' => '', 'left_signature' => '', 'right_label' => 'For {{client_company}}', 'show_client' => true],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'text', 't' => 'textarea', 'l' => 'Intro text'],
                ['k' => 'left_label', 't' => 'text', 'l' => 'Company side label'],
                ['k' => 'left_name', 't' => 'text', 'l' => 'Signatory name'],
                ['k' => 'left_role', 't' => 'text', 'l' => 'Signatory title'],
                ['k' => 'left_signature', 't' => 'image', 'l' => 'Your signature image (optional)'],
                ['k' => 'show_client', 't' => 'toggle', 'l' => 'Show client signature area'],
                ['k' => 'right_label', 't' => 'text', 'l' => 'Client side label'],
            ],
        ],
        'terms' => [
            'label' => 'Terms & Conditions', 'icon' => 'bi-file-earmark-text', 'group' => 'Business',
            'defaults' => ['title' => 'Terms & Conditions', 'html' => '<ol><li>A 50% deposit is required before work begins.</li><li>The remaining balance is due on project completion.</li><li>This proposal is valid until {{expiry_date}}.</li></ol>', 'size' => 12],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'html', 't' => 'rich', 'l' => 'Terms'],
                ['k' => 'size', 't' => 'number', 'l' => 'Font size (px)', 'min' => 9, 'max' => 18],
            ],
        ],
        'contact' => [
            'label' => 'Contact Information', 'icon' => 'bi-person-lines-fill', 'group' => 'Company',
            'defaults' => ['title' => "Let's Work Together", 'text' => 'Have questions about this proposal? We would love to hear from you.', 'style' => 'card', 'show_phone' => true, 'show_email' => true, 'show_website' => true, 'show_address' => true, 'show_whatsapp' => false],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'text', 't' => 'textarea', 'l' => 'Text'],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['card' => 'Highlighted card', 'list' => 'Simple list', 'columns' => 'Columns']],
                ['k' => 'show_phone', 't' => 'toggle', 'l' => 'Show phone'],
                ['k' => 'show_whatsapp', 't' => 'toggle', 'l' => 'Show WhatsApp'],
                ['k' => 'show_email', 't' => 'toggle', 'l' => 'Show email'],
                ['k' => 'show_website', 't' => 'toggle', 'l' => 'Show website'],
                ['k' => 'show_address', 't' => 'toggle', 'l' => 'Show address'],
            ],
        ],
        'social' => [
            'label' => 'Social Links', 'icon' => 'bi-share', 'group' => 'Company',
            'defaults' => ['align' => 'left', 'style' => 'icons', 'size' => 18],
            'fields' => [
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['icons' => 'Icons', 'list' => 'Icons with links']],
                ['k' => 'align', 't' => 'select', 'l' => 'Alignment', 'o' => $align],
                ['k' => 'size', 't' => 'range', 'l' => 'Icon size', 'min' => 12, 'max' => 40],
            ],
        ],
        'cta' => [
            'label' => 'Call To Action', 'icon' => 'bi-megaphone', 'group' => 'Content',
            'defaults' => ['title' => 'Ready to get started?', 'text' => 'Accept this proposal today and we will kick off your project within 48 hours.', 'button' => 'Accept Proposal', 'url' => '#accept', 'style' => 'primary'],
            'fields' => [
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'text', 't' => 'textarea', 'l' => 'Text'],
                ['k' => 'button', 't' => 'text', 'l' => 'Button text'],
                ['k' => 'url', 't' => 'text', 'l' => 'Button link'],
                ['k' => 'style', 't' => 'select', 'l' => 'Style', 'o' => ['primary' => 'Primary background', 'dark' => 'Dark background', 'soft' => 'Soft background']],
            ],
        ],
        'page_break' => [
            'label' => 'Page Break', 'icon' => 'bi-file-break', 'group' => 'Layout',
            'defaults' => [],
            'fields' => [],
        ],
        'html' => [
            'label' => 'Custom HTML', 'icon' => 'bi-code-slash', 'group' => 'Layout',
            'defaults' => ['code' => '<div style="padding:16px;border:1px dashed #ccc;text-align:center">Custom HTML content</div>'],
            'fields' => [['k' => 'code', 't' => 'code', 'l' => 'HTML code (scripts are removed)']],
        ],
        'cover' => [
            'label' => 'Cover Page', 'icon' => 'bi-journal-richtext', 'group' => 'hidden',
            'defaults' => ['eyebrow' => 'Business Proposal', 'title' => '{{proposal_title}}', 'subtitle' => '', 'title_size' => 46, 'for_label' => 'Prepared for', 'by_label' => 'Prepared by', 'show_client' => true, 'show_by' => true, 'show_date' => true, 'show_number' => true, 'show_expiry' => false, 'image' => '', 'logo_size' => 46, 'shapes' => true, 'layout' => 'template', 'show_toc' => true],
            'fields' => [
                ['k' => 'layout', 't' => 'select', 'l' => 'Cover layout', 'o' => ['template' => 'Template design', 'centered' => 'Centered', 'split' => 'Split with side panel']],
                ['k' => 'eyebrow', 't' => 'text', 'l' => 'Label above title'],
                ['k' => 'title', 't' => 'text', 'l' => 'Title'],
                ['k' => 'title_size', 't' => 'range', 'l' => 'Title size', 'min' => 24, 'max' => 90],
                ['k' => 'subtitle', 't' => 'textarea', 'l' => 'Subtitle'],
                ['k' => 'image', 't' => 'image', 'l' => 'Hero / cover image'],
                ['k' => 'logo_size', 't' => 'range', 'l' => 'Logo height', 'min' => 20, 'max' => 140],
                ['k' => 'shapes', 't' => 'toggle', 'l' => 'Show accent shapes'],
                ['k' => 'for_label', 't' => 'text', 'l' => '"Prepared for" label'],
                ['k' => 'by_label', 't' => 'text', 'l' => '"Prepared by" label'],
                ['k' => 'show_client', 't' => 'toggle', 'l' => 'Show client'],
                ['k' => 'show_by', 't' => 'toggle', 'l' => 'Show prepared by'],
                ['k' => 'show_date', 't' => 'toggle', 'l' => 'Show date'],
                ['k' => 'show_number', 't' => 'toggle', 'l' => 'Show proposal number'],
                ['k' => 'show_expiry', 't' => 'toggle', 'l' => 'Show expiry date'],
                ['k' => 'show_toc', 't' => 'toggle', 'l' => 'Show contents (Professional Blue)'],
            ],
        ],
    ];
    return $b;
}

function pm_block_defaults($type)
{
    return pm_blocks()[$type]['defaults'] ?? [];
}

/** Default common style values for every block. */
function pm_style_defaults()
{
    return ['bg' => '', 'color' => '', 'pad' => 0, 'mb' => 18, 'mt' => 0, 'align' => '', 'radius' => 0, 'border' => false];
}

/* ===================================================================
 *  Rendering helpers
 * ================================================================= */

function pm_wrap($doc, $b, $inner, $extraClass = '')
{
    $s = array_merge(pm_style_defaults(), is_array($b['data']['_s'] ?? null) ? $b['data']['_s'] : []);
    $st = [];
    if (is_hex_color($s['bg'])) $st[] = 'background-color:' . $s['bg'];
    if (is_hex_color($s['color'])) $st[] = 'color:' . $s['color'];
    $pad = max(0, min(80, (int) $s['pad']));
    if ($pad) $st[] = 'padding:' . $pad . 'px';
    $st[] = 'margin-bottom:' . max(0, min(200, (int) $s['mb'])) . 'px';
    if ((int) $s['mt']) $st[] = 'margin-top:' . max(0, min(200, (int) $s['mt'])) . 'px';
    if (in_array($s['align'], ['left', 'center', 'right'], true)) $st[] = 'text-align:' . $s['align'];
    if ((int) $s['radius']) $st[] = 'border-radius:' . (int) $s['radius'] . 'px';
    if (!empty($s['border'])) $st[] = 'border:1px solid ' . doc_color($doc, 'border');

    $cls = 'pm-block pm-b-' . $b['type'] . ($extraClass ? ' ' . $extraClass : '');
    $attr = '';
    if ($doc['mode'] === 'editor') {
        $attr = ' data-uid="' . e($b['uid']) . '" data-type="' . e($b['type']) . '"';
        if (!empty($b['hidden'])) $cls .= ' pm-is-hidden';
    }
    return '<div class="' . $cls . '"' . $attr . ' style="' . implode(';', $st) . '">' . $inner . '</div>';
}

/** Inner width available for a block (body width minus block padding). */
function pm_inner_width($doc, $b)
{
    $pad = (int) ($b['data']['_s']['pad'] ?? 0);
    return $doc['body_w'] - 2 * $pad;
}

/**
 * Render cells into an equal-width table grid (Dompdf-friendly).
 * $cells = [ ['html'=>..., 'class'=>..., 'style'=>...], ... ]
 */
function pm_grid($doc, $b, $cells, $cols, $gap = 16, $class = '')
{
    $cols = max(1, min(6, $cols));
    if (!$cells) return '';
    $w = pm_inner_width($doc, $b);
    $html = '';
    $rows = array_chunk($cells, $cols);
    foreach ($rows as $ri => $row) {
        while (count($row) < $cols) $row[] = ['html' => '', 'class' => 'pm-cell-empty', 'style' => ''];
        $html .= '<table class="pm-grid ' . $class . '" style="width:' . ($w + 2 * $gap) . 'px;margin-left:-' . $gap . 'px;border-spacing:' . $gap . 'px ' . ($ri ? $gap : 0) . 'px;' . ($ri ? 'margin-top:-' . $gap . 'px;' : '') . '"><tr>';
        foreach ($row as $c) {
            $html .= '<td class="pm-cell ' . ($c['class'] ?? '') . '" style="width:' . round(100 / $cols, 3) . '%;' . ($c['style'] ?? '') . '">' . $c['html'] . '</td>';
        }
        $html .= '</tr></table>';
    }
    return $html;
}

function pm_t($s)
{
    return nl2br(e($s));
}

/* ===================================================================
 *  Block renderers
 * ================================================================= */

function pm_render_heading($doc, $d, $b)
{
    $lvl = in_array($d['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4'], true) ? $d['level'] : 'h2';
    $style = $d['style'] ?? 'template';
    $h = '';
    if ($style === 'numbered' && ($d['number'] ?? '') !== '') {
        $h .= '<table class="pm-hnum"><tr><td class="pm-hnum-n">' . e($d['number']) . '</td><td>';
    }
    if (($d['eyebrow'] ?? '') !== '') $h .= '<div class="pm-eyebrow">' . e($d['eyebrow']) . '</div>';
    $h .= '<div class="pm-h pm-' . $lvl . '">' . e($d['text'] ?? '') . '</div>';
    if ($style === 'template' && in_array($lvl, ['h1', 'h2'], true)) $h .= '<div class="pm-hbar"></div>';
    if (($d['subtitle'] ?? '') !== '') $h .= '<div class="pm-hsub">' . pm_t($d['subtitle']) . '</div>';
    if ($style === 'numbered' && ($d['number'] ?? '') !== '') $h .= '</td></tr></table>';
    return pm_wrap($doc, $b, $h, 'pm-hs-' . $style);
}

function pm_render_text($doc, $d, $b)
{
    $st = [];
    if ((int) ($d['size'] ?? 0) > 0) $st[] = 'font-size:' . (int) $d['size'] . 'px';
    if ((float) ($d['line_height'] ?? 0) > 0) $st[] = 'line-height:' . (float) $d['line_height'];
    $html = clean_html($d['html'] ?? '');
    return pm_wrap($doc, $b, '<div class="pm-rich pm-flow" style="' . implode(';', $st) . '">' . $html . '</div>');
}

function pm_render_image($doc, $d, $b)
{
    $src = $d['src'] ?? '';
    $w = max(10, min(100, (int) ($d['width'] ?? 100)));
    $px = (int) round(pm_inner_width($doc, $b) * $w / 100);
    $hpx = (int) ($d['height'] ?? 0);
    $align = in_array($d['align'] ?? 'center', ['left', 'center', 'right'], true) ? $d['align'] : 'center';
    if ($src === '') {
        $img = '<div class="pm-img-ph" style="width:' . $px . 'px;height:' . ($hpx ?: 200) . 'px;border-radius:' . (int) $d['radius'] . 'px">'
            . icon_img('image', doc_color($doc, 'primary'), 40) . '<div>Click to add an image</div></div>';
        if ($doc['mode'] !== 'editor') $img = '';
    } else {
        $path = $hpx ? image_cover($src, $px, $hpx) : $src;
        $img = '<img class="pm-img" src="' . e(doc_media($doc, $path)) . '" alt="' . e($d['alt'] ?? '') . '" style="width:' . $px . 'px;' . ($hpx ? 'height:' . $hpx . 'px;' : '')
            . 'border-radius:' . (int) $d['radius'] . 'px;' . (!empty($d['border']) ? 'border:1px solid ' . doc_color($doc, 'border') . ';' : '') . '">';
    }
    $cap = ($d['caption'] ?? '') !== '' ? '<div class="pm-caption">' . e($d['caption']) . '</div>' : '';
    return pm_wrap($doc, $b, '<div class="pm-img-wrap" style="text-align:' . $align . '">' . $img . $cap . '</div>');
}

function pm_render_image_text($doc, $d, $b)
{
    $iw = max(25, min(60, (int) ($d['img_width'] ?? 42)));
    $W = pm_inner_width($doc, $b);
    $gap = 28;
    $imgPx = (int) round(($W - $gap) * $iw / 100);
    $h = (int) ($d['img_height'] ?? 0);
    $src = $d['src'] ?? '';
    if ($src !== '') {
        $path = $h ? image_cover($src, $imgPx, $h) : $src;
        $img = '<img class="pm-img" src="' . e(doc_media($doc, $path)) . '" style="width:' . $imgPx . 'px;' . ($h ? 'height:' . $h . 'px;' : '') . 'border-radius:' . (int) $d['radius'] . 'px" alt="">';
    } else {
        $img = '<div class="pm-img-ph" style="width:' . $imgPx . 'px;height:' . ($h ?: 240) . 'px;border-radius:' . (int) $d['radius'] . 'px">' . icon_img('image', doc_color($doc, 'primary'), 36) . '<div>Add an image</div></div>';
    }
    $text = (($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h3 pm-it-title">' . e($d['title']) . '</div>' : '') . '<div class="pm-rich">' . clean_html($d['html'] ?? '') . '</div>';
    $imgTd = '<td class="pm-it-img" style="width:' . $imgPx . 'px">' . $img . '</td>';
    $gapTd = '<td style="width:' . $gap . 'px"></td>';
    $txtTd = '<td class="pm-it-txt">' . $text . '</td>';
    $row = ($d['side'] ?? 'left') === 'right' ? $txtTd . $gapTd . $imgTd : $imgTd . $gapTd . $txtTd;
    return pm_wrap($doc, $b, '<table class="pm-it" style="width:' . $W . 'px"><tr>' . $row . '</tr></table>');
}

function pm_render_logo($doc, $d, $b)
{
    $variant = $d['variant'] ?? 'auto';
    $light = $variant === 'light' || ($variant === 'auto' && color_is_dark(doc_color($doc, 'background')));
    $align = in_array($d['align'] ?? 'left', ['left', 'center', 'right'], true) ? $d['align'] : 'left';
    return pm_wrap($doc, $b, '<div style="text-align:' . $align . '">' . doc_logo($doc, (int) ($d['height'] ?? 56), $light) . '</div>');
}

function pm_render_button($doc, $d, $b)
{
    $align = in_array($d['align'] ?? 'left', ['left', 'center', 'right'], true) ? $d['align'] : 'left';
    $style = in_array($d['style'] ?? 'solid', ['solid', 'outline', 'accent'], true) ? $d['style'] : 'solid';
    $size = in_array($d['size'] ?? 'md', ['sm', 'md', 'lg'], true) ? $d['size'] : 'md';
    $url = doc_link($doc, $d['url'] ?? '#');
    return pm_wrap($doc, $b, '<div style="text-align:' . $align . '"><a class="pm-btn pm-btn-' . $style . ' pm-btn-' . $size . '" href="' . e($url) . '">' . e($d['text'] ?? '') . '</a></div>');
}

function pm_render_divider($doc, $d, $b)
{
    $style = $d['style'] ?? 'solid';
    $t = max(1, min(12, (int) ($d['thickness'] ?? 1)));
    $w = max(5, min(100, (int) ($d['width'] ?? 100)));
    $color = doc_color($doc, in_array($d['color'] ?? 'border', ['border', 'primary', 'accent', 'heading'], true) ? $d['color'] : 'border');
    $align = $d['align'] ?? 'center';
    $ml = $align === 'center' ? 'auto' : ($align === 'right' ? 'auto' : '0');
    $mr = $align === 'center' ? 'auto' : ($align === 'right' ? '0' : 'auto');
    $px = (int) round(pm_inner_width($doc, $b) * $w / 100);
    if ($style === 'accent') {
        $line = '<div style="width:' . $px . 'px;height:' . max(3, $t) . 'px;background:' . doc_color($doc, 'primary') . ';margin-left:' . $ml . ';margin-right:' . $mr . ';border-radius:4px"></div>';
    } else {
        $bs = in_array($style, ['solid', 'dashed', 'dotted', 'double'], true) ? $style : 'solid';
        if ($bs === 'double') $t = max(3, $t);
        $line = '<div style="width:' . $px . 'px;height:0;border-top:' . $t . 'px ' . $bs . ' ' . $color . ';margin-left:' . $ml . ';margin-right:' . $mr . '"></div>';
    }
    return pm_wrap($doc, $b, $line);
}

function pm_render_spacer($doc, $d, $b)
{
    $h = max(4, min(400, (int) ($d['height'] ?? 32)));
    return pm_wrap($doc, $b, '<div class="pm-spacer" style="height:' . $h . 'px"></div>');
}

function pm_render_icon($doc, $d, $b)
{
    $size = max(16, min(120, (int) ($d['size'] ?? 40)));
    $color = doc_ref($doc, $d['color'] ?? 'primary');
    $align = in_array($d['align'] ?? 'left', ['left', 'center', 'right'], true) ? $d['align'] : 'left';
    $ico = icon_img($d['icon'] ?? 'star', $color, $size);
    if (!empty($d['circle'])) {
        $box = (int) round($size * 1.9);
        $pad = (int) round(($box - $size) / 2);
        $ico = '<div class="pm-icon-circle" style="width:' . $box . 'px;height:' . $box . 'px;border-radius:' . $box . 'px;background:' . doc_soft($doc, $d['color'] ?? 'primary') . ';text-align:center;' . ($align === 'center' ? 'margin:0 auto;' : ($align === 'right' ? 'margin-left:auto;' : '')) . '"><div style="padding-top:' . $pad . 'px;line-height:0">' . $ico . '</div></div>';
    }
    $h = '<div style="text-align:' . $align . '">' . $ico;
    if (($d['label'] ?? '') !== '') $h .= '<div class="pm-h pm-h4" style="margin-top:12px">' . e($d['label']) . '</div>';
    if (($d['text'] ?? '') !== '') $h .= '<div class="pm-muted">' . pm_t($d['text']) . '</div>';
    return pm_wrap($doc, $b, $h . '</div>');
}

/** Only the list items that have some text. */
function pm_non_empty_items($items)
{
    $out = array();
    foreach ((array) $items as $item) {
        if (trim((string) ($item['text'] ?? '')) !== '') $out[] = $item;
    }
    return $out;
}

function pm_list_item($doc, $marker, $text)
{
    return '<table class="pm-li"><tr><td class="pm-li-m">' . shape_img($marker, doc_color($doc, 'primary'), 15) . '</td><td class="pm-li-t">' . pm_t($text) . '</td></tr></table>';
}

function pm_render_list($doc, $d, $b)
{
    $items = pm_non_empty_items($d['items'] ?? array());
    $marker = $d['marker'] ?? 'check';
    $cols = max(1, min(3, (int) ($d['columns'] ?? 1)));
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h4 pm-chrome-top pm-list-title">' . e($d['title']) . '</div>' : '';
    if ($cols === 1) {
        $h .= '<div class="pm-flow">';
        foreach ($items as $i) $h .= pm_list_item($doc, $marker, $i['text']);
        $h .= '</div>';
    } else {
        $per = (int) ceil(count($items) / $cols);
        $cells = [];
        foreach (array_chunk($items, max(1, $per)) as $chunk) {
            $c = '';
            foreach ($chunk as $i) $c .= pm_list_item($doc, $marker, $i['text']);
            $cells[] = ['html' => $c];
        }
        $h .= pm_grid($doc, $b, $cells, $cols, 12, 'pm-grid-plain');
    }
    return pm_wrap($doc, $b, $h);
}

function pm_render_numbered_list($doc, $d, $b)
{
    $items = pm_non_empty_items($d['items'] ?? array());
    $style = $d['style'] ?? 'circle';
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h4 pm-chrome-top pm-list-title">' . e($d['title']) . '</div>' : '';
    $h .= '<div class="pm-flow">';
    foreach ($items as $n => $i) {
        $h .= '<table class="pm-nli pm-nli-' . e($style) . '"><tr><td class="pm-nli-n"><span>' . ($n + 1) . '</span></td><td class="pm-nli-t">' . pm_t($i['text']) . '</td></tr></table>';
    }
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_quote($doc, $d, $b)
{
    $style = in_array($d['style'] ?? 'bar', ['bar', 'big', 'card'], true) ? $d['style'] : 'bar';
    $h = '<div class="pm-quote pm-quote-' . $style . '">';
    if ($style === 'big') $h .= '<div class="pm-quote-mark">&ldquo;</div>';
    $h .= '<div class="pm-quote-t">' . pm_t($d['text'] ?? '') . '</div>';
    if (($d['author'] ?? '') !== '') {
        $h .= '<div class="pm-quote-a">— ' . e($d['author']) . (($d['role'] ?? '') !== '' ? '<span>, ' . e($d['role']) . '</span>' : '') . '</div>';
    }
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_feature_cards($doc, $d, $b)
{
    $style = in_array($d['style'] ?? 'card', ['card', 'bordered', 'filled', 'plain'], true) ? $d['style'] : 'card';
    $align = in_array($d['align'] ?? 'left', ['left', 'center', 'right'], true) ? $d['align'] : 'left';
    $icoColor = $style === 'filled' ? doc_on($doc, 'primary') : doc_color($doc, 'primary');
    $cells = [];
    foreach ($d['items'] ?? [] as $it) {
        $c = '';
        if (($it['icon'] ?? '') !== '') {
            $c .= '<div class="pm-fc-ico" style="text-align:' . $align . '">' . icon_img($it['icon'], $icoColor, 30) . '</div>';
        }
        $c .= '<div class="pm-fc-title">' . e($it['title'] ?? '') . '</div><div class="pm-fc-text">' . pm_t($it['text'] ?? '') . '</div>';
        $cells[] = ['html' => $c, 'class' => 'pm-fc pm-fc-' . $style, 'style' => 'text-align:' . $align];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 3), 16));
}

function pm_render_service_cards($doc, $d, $b)
{
    $cells = [];
    foreach ($d['items'] ?? [] as $it) {
        $acc = is_hex_color($it['color'] ?? '') ? $it['color'] : doc_color($doc, 'primary');
        $bg = is_hex_color($it['bg'] ?? '') ? $it['bg'] : '';
        $c = '<div class="pm-sc-ico" style="background:' . color_mix($acc, 0.86) . '">' . icon_img($it['icon'] ?? 'briefcase', $acc, 26) . '</div>';
        $c .= '<div class="pm-sc-title">' . e($it['title'] ?? '') . '</div>';
        if (($it['description'] ?? '') !== '') $c .= '<div class="pm-sc-desc">' . pm_t($it['description']) . '</div>';
        $feats = array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($it['features'] ?? ''))));
        if ($feats) {
            $c .= '<div class="pm-sc-feats">';
            foreach ($feats as $f) {
                $c .= '<table class="pm-li pm-li-sm"><tr><td class="pm-li-m">' . shape_img('check', $acc, 13) . '</td><td class="pm-li-t">' . e($f) . '</td></tr></table>';
            }
            $c .= '</div>';
        }
        if (!empty($d['show_price']) && ($it['price'] ?? '') !== '') {
            $c .= '<div class="pm-sc-price" style="color:' . $acc . ';border-top-color:' . doc_color($doc, 'border') . '">' . e($it['price']) . '</div>';
        }
        $cells[] = ['html' => $c, 'class' => 'pm-sc', 'style' => ($bg ? 'background:' . $bg . ';' : '') . 'border-top-color:' . $acc];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 2), 16));
}

/** Calculates pricing totals for a pricing_table block's data. */
function pm_pricing_totals($d)
{
    $sub = 0.0;
    foreach ($d['rows'] ?? [] as $row) {
        $sub += (float) ($row['qty'] ?? 0) * (float) ($row['price'] ?? 0);
    }
    $disc = (float) ($d['discount'] ?? 0);
    $discount = ($d['discount_type'] ?? 'percent') === 'percent' ? $sub * min(100, $disc) / 100 : min($sub, $disc);
    $taxable = $sub - $discount;
    $tax = $taxable * (float) ($d['tax_rate'] ?? 0) / 100;
    return [
        'subtotal' => round($sub, 2),
        'discount' => round($discount, 2),
        'tax'      => round($tax, 2),
        'total'    => round($taxable + $tax, 2),
    ];
}

function pm_render_pricing_table($doc, $d, $b)
{
    $sym = $doc['symbol'];
    $showDesc = !empty($d['show_description']);
    $showQty = !empty($d['show_qty']);
    $t = pm_pricing_totals($d);
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h3 pm-chrome-top pm-pt-title">' . e($d['title']) . '</div>' : '';
    $h .= '<table class="pm-table pm-pt"><thead><tr><th>Service</th>' . ($showDesc ? '<th>Description</th>' : '') . ($showQty ? '<th class="pm-num" style="width:50px">Qty</th>' : '')
        . '<th class="pm-num" style="width:96px">Price</th><th class="pm-num" style="width:104px">Total</th></tr></thead><tbody class="pm-flow">';
    foreach ($d['rows'] ?? [] as $row) {
        $q = (float) ($row['qty'] ?? 0);
        $p = (float) ($row['price'] ?? 0);
        $h .= '<tr><td class="pm-pt-s">' . e($row['service'] ?? '') . '</td>' . ($showDesc ? '<td class="pm-pt-d">' . e($row['description'] ?? '') . '</td>' : '')
            . ($showQty ? '<td class="pm-num">' . e(rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.')) . '</td>' : '')
            . '<td class="pm-num">' . e(money($p, $sym)) . '</td><td class="pm-num pm-strong">' . e(money($q * $p, $sym)) . '</td></tr>';
    }
    $h .= '</tbody></table>';
    $h .= '<table class="pm-pt-totals pm-chrome-bottom"><tr><td class="pm-pt-note">' . (($d['note'] ?? '') !== '' ? pm_t($d['note']) : '') . '</td><td class="pm-pt-sum"><table>';
    $h .= '<tr><td>Subtotal</td><td class="pm-num">' . e(money($t['subtotal'], $sym)) . '</td></tr>';
    if ($t['discount'] > 0) {
        $lbl = ($d['discount_type'] ?? 'percent') === 'percent' ? 'Discount (' . (float) $d['discount'] . '%)' : 'Discount';
        $h .= '<tr><td>' . e($lbl) . '</td><td class="pm-num">-' . e(money($t['discount'], $sym)) . '</td></tr>';
    }
    if ((float) ($d['tax_rate'] ?? 0) > 0) {
        $h .= '<tr><td>' . e(($d['tax_label'] ?? 'Tax') ?: 'Tax') . ' (' . (float) $d['tax_rate'] . '%)</td><td class="pm-num">' . e(money($t['tax'], $sym)) . '</td></tr>';
    }
    $h .= '<tr class="pm-pt-total"><td>Total</td><td class="pm-num">' . e(money($t['total'], $sym)) . '</td></tr>';
    $h .= '</table></td></tr></table>';
    return pm_wrap($doc, $b, $h);
}

function pm_yes_no($doc, $v)
{
    $l = strtolower(trim($v));
    if (in_array($l, ['yes', 'y', 'true', '✓', 'included'], true)) return shape_img('circle-check', doc_color($doc, 'primary'), 16);
    if (in_array($l, ['no', 'n', 'false', '✗', 'x', '-'], true)) return shape_img('cross', doc_mix($doc, 'text', 0.55), 16);
    return e(trim($v));
}

/** Split on "|" (or on commas when no "|" is present). */
function pm_split_list($s)
{
    return array_map('trim', explode(strpos($s, '|') !== false ? '|' : ',', $s));
}

function pm_render_comparison_table($doc, $d, $b)
{
    $heads = array_values(array_filter(pm_split_list((string) ($d['headers'] ?? '')), 'strlen'));
    $prices = pm_split_list((string) ($d['prices'] ?? ''));
    $hl = (int) ($d['highlight'] ?? 0);
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h3 pm-chrome-top">' . e($d['title']) . '</div>' : '';
    $h .= '<table class="pm-table pm-cmp"><thead><tr><th>Features</th>';
    foreach ($heads as $i => $hd) {
        $h .= '<th class="pm-c' . ($hl === $i + 1 ? ' pm-hl' : '') . '">' . e($hd) . (isset($prices[$i]) && $prices[$i] !== '' ? '<div class="pm-cmp-price">' . e($prices[$i]) . '</div>' : '') . '</th>';
    }
    $h .= '</tr></thead><tbody class="pm-flow">';
    foreach ($d['rows'] ?? [] as $row) {
        $vals = array_map('trim', explode('|', (string) ($row['values'] ?? '')));
        $h .= '<tr><td class="pm-strong">' . e($row['feature'] ?? '') . '</td>';
        foreach ($heads as $i => $hd) {
            $h .= '<td class="pm-c' . ($hl === $i + 1 ? ' pm-hl' : '') . '">' . pm_yes_no($doc, $vals[$i] ?? '') . '</td>';
        }
        $h .= '</tr>';
    }
    return pm_wrap($doc, $b, $h . '</tbody></table>');
}

function pm_render_table($doc, $d, $b)
{
    $heads = array_values(array_filter(pm_split_list((string) ($d['headers'] ?? '')), 'strlen'));
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h4 pm-chrome-top">' . e($d['title']) . '</div>' : '';
    $h .= '<table class="pm-table' . (!empty($d['striped']) ? ' pm-striped' : '') . '">';
    if ($heads) {
        $h .= '<thead><tr>';
        foreach ($heads as $hd) $h .= '<th>' . e($hd) . '</th>';
        $h .= '</tr></thead>';
    }
    $h .= '<tbody class="pm-flow">';
    $n = max(1, count($heads));
    foreach ($d['rows'] ?? [] as $row) {
        $cells = array_map('trim', explode('|', (string) ($row['cells'] ?? '')));
        $h .= '<tr>';
        for ($i = 0; $i < max($n, count($cells)); $i++) $h .= '<td>' . pm_yes_no($doc, $cells[$i] ?? '') . '</td>';
        $h .= '</tr>';
    }
    return pm_wrap($doc, $b, $h . '</tbody></table>');
}

function pm_render_stats($doc, $d, $b)
{
    $style = in_array($d['style'] ?? 'card', ['card', 'filled', 'plain', 'divided'], true) ? $d['style'] : 'card';
    $icoColor = $style === 'filled' ? doc_on($doc, 'primary') : doc_color($doc, 'primary');
    $cells = [];
    $items = $d['items'] ?? [];
    foreach ($items as $i => $it) {
        $c = (($it['icon'] ?? '') !== '' ? '<div class="pm-st-ico">' . icon_img($it['icon'], $icoColor, 26) . '</div>' : '')
            . '<div class="pm-st-v">' . e($it['value'] ?? '') . '</div><div class="pm-st-l">' . e($it['label'] ?? '') . '</div>';
        $cells[] = ['html' => $c, 'class' => 'pm-st pm-st-' . $style . ($i ? '' : ' pm-first')];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 4), $style === 'divided' ? 0 : 14));
}

function pm_avatar($doc, $photo, $name, $size)
{
    if ($photo !== '') {
        return '<img class="pm-avatar" src="' . e(doc_media($doc, image_cover($photo, $size, $size))) . '" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:' . $size . 'px" alt="">';
    }
    $ini = '';
    foreach (preg_split('/\s+/', trim($name)) as $p) {
        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return '<div class="pm-avatar pm-avatar-i" style="width:' . $size . 'px;height:' . $size . 'px;line-height:' . $size . 'px;border-radius:' . $size . 'px;font-size:' . round($size * 0.36) . 'px">' . e(mb_substr($ini, 0, 2)) . '</div>';
}

function pm_render_testimonial($doc, $d, $b)
{
    $cells = [];
    foreach ($d['items'] ?? [] as $it) {
        $stars = '';
        for ($i = 0; $i < max(0, min(5, (int) ($it['rating'] ?? 0))); $i++) $stars .= shape_img('star', doc_color($doc, 'accent'), 13) . ' ';
        $c = ($stars ? '<div class="pm-ts-stars">' . $stars . '</div>' : '') . '<div class="pm-ts-q">&ldquo;' . pm_t($it['quote'] ?? '') . '&rdquo;</div>'
            . '<table class="pm-ts-who"><tr><td style="width:52px">' . pm_avatar($doc, (string) ($it['photo'] ?? ''), (string) ($it['name'] ?? ''), 42) . '</td><td><div class="pm-ts-n">' . e($it['name'] ?? '') . '</div><div class="pm-ts-r">' . e($it['role'] ?? '') . '</div></td></tr></table>';
        $cells[] = ['html' => $c, 'class' => 'pm-ts'];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 2), 16));
}

function pm_render_team($doc, $d, $b)
{
    $cells = [];
    foreach ($d['items'] ?? [] as $it) {
        $c = '<div class="pm-tm-photo">' . pm_avatar($doc, (string) ($it['photo'] ?? ''), (string) ($it['name'] ?? ''), 92) . '</div>'
            . '<div class="pm-tm-n">' . e($it['name'] ?? '') . '</div><div class="pm-tm-r">' . e($it['role'] ?? '') . '</div>'
            . (($it['bio'] ?? '') !== '' ? '<div class="pm-tm-b">' . pm_t($it['bio']) . '</div>' : '');
        $cells[] = ['html' => $c, 'class' => 'pm-tm'];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 3), 16));
}

function pm_render_timeline($doc, $d, $b)
{
    $items = $d['items'] ?? [];
    $h = '<div class="pm-flow">';
    $n = count($items);
    foreach ($items as $i => $it) {
        $h .= '<table class="pm-tl' . ($i === $n - 1 ? ' pm-tl-last' : '') . '"><tr><td class="pm-tl-date">' . e($it['date'] ?? '') . '</td><td class="pm-tl-axis"><div class="pm-tl-dot"></div></td><td class="pm-tl-body"><div class="pm-tl-title">' . e($it['title'] ?? '') . '</div><div class="pm-tl-text">' . pm_t($it['text'] ?? '') . '</div></td></tr></table>';
    }
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_process($doc, $d, $b)
{
    $cells = [];
    foreach ($d['items'] ?? [] as $i => $it) {
        $c = '<div class="pm-ps-top"><span class="pm-ps-num">' . sprintf('%02d', $i + 1) . '</span></div>'
            . (($it['icon'] ?? '') !== '' ? '<div class="pm-ps-ico">' . icon_img($it['icon'], doc_color($doc, 'primary'), 28) . '</div>' : '')
            . '<div class="pm-ps-title">' . e($it['title'] ?? '') . '</div><div class="pm-ps-text">' . pm_t($it['text'] ?? '') . '</div>';
        $cells[] = ['html' => $c, 'class' => 'pm-ps'];
    }
    return pm_wrap($doc, $b, pm_grid($doc, $b, $cells, (int) ($d['columns'] ?? 4), 14));
}

function pm_render_faq($doc, $d, $b)
{
    $h = '<div class="pm-flow">';
    foreach ($d['items'] ?? [] as $it) {
        $h .= '<div class="pm-faq"><table class="pm-faq-q"><tr><td class="pm-faq-m">Q</td><td>' . e($it['q'] ?? '') . '</td></tr></table><div class="pm-faq-a">' . pm_t($it['a'] ?? '') . '</div></div>';
    }
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_signature($doc, $d, $b)
{
    $h = '';
    if (($d['title'] ?? '') !== '') $h .= '<div class="pm-h pm-h3">' . e($d['title']) . '</div>';
    if (($d['text'] ?? '') !== '') $h .= '<div class="pm-muted" style="margin-bottom:22px">' . pm_t($d['text']) . '</div>';
    $left = '<div class="pm-sig-l">' . e($d['left_label'] ?? '') . '</div><div class="pm-sig-box">'
        . (($d['left_signature'] ?? '') !== '' ? '<img src="' . e(doc_media($doc, $d['left_signature'])) . '" style="max-height:60px;max-width:220px" alt="">' : '&nbsp;')
        . '</div><div class="pm-sig-n">' . e($d['left_name'] ?? '') . '</div><div class="pm-sig-r">' . e($d['left_role'] ?? '') . '</div>'
        . '<div class="pm-sig-d">Date: ' . e(doc_date($doc, $doc['p']['proposal_date'] ?? '')) . '</div>';
    $cells = [['html' => $left, 'class' => 'pm-sig']];
    if (!empty($d['show_client'])) {
        $acc = $doc['acceptance'];
        $sigHtml = '&nbsp;';
        $name = '';
        $date = '';
        if ($acc) {
            if ($acc['signature_type'] === 'typed' || ($acc['signature_type'] === '' && $acc['signature_text'])) {
                $sigHtml = '<span class="pm-sig-typed">' . e($acc['signature_text']) . '</span>';
            } elseif ($acc['signature_data']) {
                $sigHtml = '<img src="' . e(doc_media($doc, $acc['signature_data'])) . '" style="max-height:60px;max-width:220px" alt="">';
            }
            $name = $acc['name'];
            $date = doc_date($doc, $acc['created_at']);
        }
        $right = '<div class="pm-sig-l">' . e($d['right_label'] ?? '') . '</div><div class="pm-sig-box">' . $sigHtml . '</div>'
            . '<div class="pm-sig-n">' . e($name !== '' ? $name : $doc['p']['client_name'] ?? '') . '</div><div class="pm-sig-r">' . e($doc['p']['client_company'] ?? '') . '</div>'
            . '<div class="pm-sig-d">Date: ' . e($date) . '</div>'
            . ($acc ? '<div class="pm-sig-ok">' . shape_img('circle-check', '#16a34a', 13) . ' Accepted electronically · IP ' . e($acc['ip_address']) . '</div>' : '');
        $cells[] = ['html' => $right, 'class' => 'pm-sig'];
    }
    $h .= pm_grid($doc, $b, $cells, 2, 24);
    return pm_wrap($doc, $b, $h);
}

function pm_render_terms($doc, $d, $b)
{
    $size = max(9, min(18, (int) ($d['size'] ?? 12)));
    $h = ($d['title'] ?? '') !== '' ? '<div class="pm-h pm-h3 pm-chrome-top">' . e($d['title']) . '</div>' : '';
    $h .= '<div class="pm-rich pm-terms pm-flow" style="font-size:' . $size . 'px">' . clean_html($d['html'] ?? '') . '</div>';
    return pm_wrap($doc, $b, $h);
}

function pm_render_contact($doc, $d, $b)
{
    $co = $doc['company'];
    $style = in_array($d['style'] ?? 'card', ['card', 'list', 'columns'], true) ? $d['style'] : 'card';
    $onDark = $style === 'card';
    $icoColor = $onDark ? doc_on($doc, 'primary') : doc_color($doc, 'primary');
    $rows = [];
    $addr = trim(implode(', ', array_filter([$co['address'] ?? '', $co['city'] ?? '', $co['postal_code'] ?? '', $co['country'] ?? ''])));
    if (!empty($d['show_phone']) && !empty($co['phone'])) $rows[] = ['telephone', 'Phone', $co['phone']];
    if (!empty($d['show_whatsapp']) && !empty($co['whatsapp'])) $rows[] = ['whatsapp', 'WhatsApp', $co['whatsapp']];
    if (!empty($d['show_email']) && !empty($co['email'])) $rows[] = ['envelope', 'Email', $co['email']];
    if (!empty($d['show_website']) && !empty($co['website'])) $rows[] = ['globe', 'Website', $co['website']];
    if (!empty($d['show_address']) && $addr !== '') $rows[] = ['geo-alt', 'Address', $addr];

    $h = '';
    if (($d['title'] ?? '') !== '') $h .= '<div class="pm-h pm-h2 pm-ct-title">' . e($d['title']) . '</div>';
    if (($d['text'] ?? '') !== '') $h .= '<div class="pm-ct-text">' . pm_t($d['text']) . '</div>';
    if ($style === 'columns') {
        $cells = [];
        foreach ($rows as $row) {
            $cells[] = ['html' => '<div class="pm-ct-ico">' . icon_img($row[0], $icoColor, 22) . '</div><div class="pm-ct-l">' . $row[1] . '</div><div class="pm-ct-v">' . e($row[2]) . '</div>', 'class' => 'pm-ct-col'];
        }
        $h .= pm_grid($doc, $b, $cells, max(1, min(4, count($rows))), 12);
    } else {
        $h .= '<table class="pm-ct-list">';
        foreach ($rows as $row) {
            $h .= '<tr><td class="pm-ct-i">' . icon_img($row[0], $icoColor, 18) . '</td><td><span class="pm-ct-l">' . $row[1] . '</span><br><span class="pm-ct-v">' . e($row[2]) . '</span></td></tr>';
        }
        $h .= '</table>';
    }
    return pm_wrap($doc, $b, '<div class="pm-ct pm-ct-' . $style . '">' . $h . '</div>');
}

function pm_social_links($co)
{
    $map = ['facebook' => 'facebook', 'instagram' => 'instagram', 'linkedin' => 'linkedin', 'youtube' => 'youtube', 'twitter' => 'twitter-x'];
    $out = [];
    foreach ($map as $field => $icon) {
        if (!empty($co[$field])) $out[] = ['icon' => $icon, 'url' => $co[$field], 'name' => $field === 'twitter' ? 'X' : ucfirst($field)];
    }
    if (!empty($co['whatsapp'])) $out[] = ['icon' => 'whatsapp', 'url' => 'https://wa.me/' . preg_replace('/\D+/', '', $co['whatsapp']), 'name' => 'WhatsApp'];
    return $out;
}

function pm_render_social($doc, $d, $b)
{
    $links = pm_social_links($doc['company']);
    $size = max(12, min(40, (int) ($d['size'] ?? 18)));
    $align = in_array($d['align'] ?? 'left', ['left', 'center', 'right'], true) ? $d['align'] : 'left';
    if (!$links) {
        return $doc['mode'] === 'editor' ? pm_wrap($doc, $b, '<div class="pm-muted">Add social media links in Settings → Company Profile.</div>') : '';
    }
    $h = '<div class="pm-social" style="text-align:' . $align . '">';
    foreach ($links as $l) {
        if (($d['style'] ?? 'icons') === 'list') {
            $h .= '<a href="' . e($l['url']) . '" class="pm-social-l">' . icon_img($l['icon'], doc_color($doc, 'primary'), $size) . ' <span>' . e($l['name']) . '</span></a> ';
        } else {
            $box = $size + 18;
            $h .= '<a href="' . e($l['url']) . '" class="pm-social-i" style="width:' . $box . 'px;height:' . $box . 'px;border-radius:' . $box . 'px;background:' . doc_color($doc, 'primary') . '"><span style="display:block;padding-top:9px;line-height:0">' . icon_img($l['icon'], doc_on($doc, 'primary'), $size) . '</span></a> ';
        }
    }
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_cta($doc, $d, $b)
{
    $style = in_array($d['style'] ?? 'primary', ['primary', 'dark', 'soft'], true) ? $d['style'] : 'primary';
    $h = '<div class="pm-cta pm-cta-' . $style . '"><div class="pm-cta-t">' . e($d['title'] ?? '') . '</div>';
    if (($d['text'] ?? '') !== '') $h .= '<div class="pm-cta-x">' . pm_t($d['text']) . '</div>';
    if (($d['button'] ?? '') !== '') $h .= '<a class="pm-cta-b" href="' . e(doc_link($doc, $d['url'] ?? '#')) . '">' . e($d['button']) . '</a>';
    return pm_wrap($doc, $b, $h . '</div>');
}

function pm_render_page_break($doc, $d, $b)
{
    if ($doc['mode'] !== 'editor') return '';
    return pm_wrap($doc, $b, '<div class="pm-pagebreak"><span>Page break — content below starts on a new page</span></div>');
}

function pm_render_html($doc, $d, $b)
{
    $code = (string) ($d['code'] ?? '');
    $code = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $code);
    $code = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $code);
    $code = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $code);
    return pm_wrap($doc, $b, '<div class="pm-html">' . $code . '</div>');
}
