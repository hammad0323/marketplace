<?php
/**
 * Pre-built service proposals + default content library.
 * Seeded into the database on install (service_templates / content_library),
 * after which they can be edited, duplicated or extended from the admin.
 */

/* ---------- tiny builders ---------- */
function _h(string $eyebrow, string $text, string $sub = '', string $level = 'h2'): array
{
    return ['type' => 'heading', 'data' => ['eyebrow' => $eyebrow, 'text' => $text, 'subtitle' => $sub, 'level' => $level]];
}
function _h3(string $text, string $sub = ''): array
{
    return ['type' => 'heading', 'data' => ['eyebrow' => '', 'text' => $text, 'subtitle' => $sub, 'level' => 'h3', 'style' => 'plain', '_s' => ['mb' => 8, 'mt' => 6]]];
}
function _t(string $html): array
{
    return ['type' => 'text', 'data' => ['html' => $html]];
}
function _l(array $items, string $marker = 'check', int $cols = 1, string $title = ''): array
{
    return ['type' => 'list', 'data' => ['title' => $title, 'marker' => $marker, 'columns' => (string) $cols, 'items' => array_map(fn($t) => ['text' => $t], $items)]];
}
function _nl(array $items, string $title = ''): array
{
    return ['type' => 'numbered_list', 'data' => ['title' => $title, 'items' => array_map(fn($t) => ['text' => $t], $items)]];
}
function _fc(array $items, int $cols = 3, string $style = 'card'): array
{
    return ['type' => 'feature_cards', 'data' => ['columns' => (string) $cols, 'style' => $style, 'items' => array_map(fn($i) => ['icon' => $i[0], 'title' => $i[1], 'text' => $i[2]], $items)]];
}
function _sc(array $items, int $cols = 2): array
{
    return ['type' => 'service_cards', 'data' => ['columns' => (string) $cols, 'show_price' => true, 'items' => array_map(fn($i) => ['icon' => $i[0], 'title' => $i[1], 'description' => $i[2], 'features' => $i[3], 'price' => $i[4] ?? '', 'bg' => '', 'color' => ''], $items)]];
}
function _stats(array $items): array
{
    return ['type' => 'stats', 'data' => ['columns' => (string) count($items), 'style' => 'card', 'items' => array_map(fn($i) => ['value' => $i[0], 'label' => $i[1], 'icon' => $i[2] ?? ''], $items)]];
}
function _tl(array $items): array
{
    return ['type' => 'timeline', 'data' => ['items' => array_map(fn($i) => ['date' => $i[0], 'title' => $i[1], 'text' => $i[2]], $items)]];
}
function _ps(array $items, int $cols = 4): array
{
    return ['type' => 'process', 'data' => ['columns' => (string) $cols, 'items' => array_map(fn($i) => ['icon' => $i[0], 'title' => $i[1], 'text' => $i[2]], $items)]];
}
function _price(string $title, array $rows, float $tax = 0, string $note = 'All prices are in {{currency}}. This quotation is valid until {{expiry_date}}.'): array
{
    return ['type' => 'pricing_table', 'data' => ['title' => $title, 'rows' => array_map(fn($r) => ['service' => $r[0], 'description' => $r[1], 'qty' => $r[2], 'price' => $r[3]], $rows), 'tax_rate' => $tax, 'note' => $note]];
}
function _table(string $headers, array $rows, string $title = ''): array
{
    return ['type' => 'table', 'data' => ['title' => $title, 'headers' => $headers, 'striped' => true, 'rows' => array_map(fn($r) => ['cells' => $r], $rows)]];
}
function _sp(int $h = 16): array
{
    return ['type' => 'spacer', 'data' => ['height' => $h]];
}
function _div(): array
{
    return ['type' => 'divider', 'data' => ['style' => 'solid', 'thickness' => 1, 'width' => 100, 'color' => 'border']];
}
function _page(string $title, array $blocks): array
{
    return ['title' => $title, 'page_type' => 'content', 'blocks' => $blocks];
}
function _cover(string $eyebrow, string $subtitle): array
{
    return ['title' => 'Cover', 'page_type' => 'cover', 'blocks' => [['type' => 'cover', 'data' => ['eyebrow' => $eyebrow, 'title' => '{{proposal_title}}', 'subtitle' => $subtitle]]]];
}

/* ---------- shared sections ---------- */
function _about_page(): array
{
    return _page('About Us', [
        _h('About Us', 'Who We Are'),
        ['type' => 'image_text', 'data' => ['src' => '', 'side' => 'right', 'img_width' => 40, 'img_height' => 260, 'title' => '{{company_name}}', 'html' => '<p>{{company_name}} is a full-service digital agency helping ambitious businesses grow through beautiful design, robust technology and data-driven marketing.</p><p>Our team of designers, developers and strategists has delivered hundreds of successful projects for clients across the world — from start-ups to established enterprises.</p>']],
        _sp(8),
        _stats([['250+', 'Projects', 'trophy'], ['98%', 'Happy Clients', 'emoji-smile'], ['10+', 'Years', 'award'], ['24/7', 'Support', 'chat-dots']]),
        _h3('Why Choose Us'),
        _fc([
            ['people', 'Dedicated Team', 'A single point of contact and a senior team assigned to your project.'],
            ['bullseye', 'Results Focused', 'Every decision is tied to your business goals and measurable KPIs.'],
            ['shield-check', 'Reliable Delivery', 'Transparent timelines, weekly updates and on-time launches.'],
        ], 3),
    ]);
}

function _terms_page(string $extra = ''): array
{
    return _page('Terms & Conditions', [
        _h('The Fine Print', 'Terms & Conditions'),
        ['type' => 'terms', 'data' => ['title' => '', 'size' => 12, 'html' => '<ol>'
            . '<li><b>Acceptance.</b> This proposal becomes a binding agreement once accepted in writing or electronically by {{client_company}}.</li>'
            . '<li><b>Payment.</b> Invoices are payable within 7 days. Work begins once the initial deposit has been received.</li>'
            . '<li><b>Revisions.</b> The number of revision rounds is limited to what is specified in this proposal. Additional changes are billed at our standard hourly rate.</li>'
            . '<li><b>Client responsibilities.</b> The client agrees to provide content, feedback and approvals in a timely manner. Delays may affect the timeline.</li>'
            . '<li><b>Intellectual property.</b> Upon full payment, ownership of the final deliverables transfers to the client. {{company_name}} may showcase the work in its portfolio.</li>'
            . '<li><b>Third-party costs.</b> Domains, hosting, premium plugins, stock assets and advertising spend are not included unless stated.</li>'
            . '<li><b>Confidentiality.</b> Both parties agree to keep confidential information private.</li>'
            . '<li><b>Cancellation.</b> Either party may terminate with 14 days written notice. Work completed up to that date is payable.</li>'
            . $extra
            . '<li><b>Validity.</b> This proposal is valid until {{expiry_date}}.</li>'
            . '</ol>']],
    ]);
}

function _contact_page(): array
{
    return _page('Contact', [
        _h('Next Steps', 'Let\'s Get Started', 'Accepting this proposal is simple — review the details, click "Accept Proposal" and we will schedule your kick-off call within 48 hours.'),
        _nl(['Accept this proposal online or sign below.', 'Receive your deposit invoice and project onboarding pack.', 'Kick-off meeting to align on goals, content and timeline.']),
        _sp(6),
        ['type' => 'signature', 'data' => ['left_name' => '', 'left_role' => 'Project Director']],
        _sp(6),
        ['type' => 'contact', 'data' => ['style' => 'card']],
    ]);
}

function _payment_schedule(array $rows): array
{
    return _table('Milestone, Percentage, Due', $rows, 'Payment Schedule');
}

/* ---------- proposals ---------- */
function pm_prebuilt_proposals(): array
{
    $p = [];

    /* ============ WEB DEVELOPMENT ============ */
    $p['web-development'] = [
        'name' => 'Web Development Proposal', 'service' => 'Web Development', 'icon' => 'code-slash',
        'description' => 'Complete website development proposal — 20 sections from requirements to launch.',
        'pages' => [
            _cover('Website Development Proposal', 'A modern, fast and conversion-focused website built to grow your business.'),
            _page('Introduction', [
                _h('Introduction', 'Dear {{client_name}},'),
                _t('<p>Thank you for considering <b>{{company_name}}</b> for your new website. We have reviewed your requirements and prepared this proposal outlining our recommended approach, deliverables, timeline and investment.</p><p>Our goal is simple: to build a website for <b>{{client_company}}</b> that looks exceptional, loads instantly, ranks well on search engines and turns visitors into customers.</p>'),
                ['type' => 'quote', 'data' => ['text' => 'Your website is your best salesperson — it works 24 hours a day, 7 days a week.', 'author' => '{{company_name}}', 'style' => 'card']],
            ]),
            _about_page(),
            _page('Requirements & Objectives', [
                _h('Understanding Your Needs', 'Client Requirements'),
                _t('<p>Based on our discussions, {{client_company}} requires a professional website that clearly communicates its services, builds trust and generates qualified enquiries.</p>'),
                _l(['A modern, professional design aligned with your brand', 'Easy content management without technical knowledge', 'Lead capture forms and clear calls-to-action', 'Fast performance on mobile and desktop', 'A solid SEO foundation from day one'], 'circle-check'),
                _sp(10),
                _h('Goals', 'Project Objectives', '', 'h3'),
                _fc([
                    ['graph-up-arrow', 'Grow Leads', 'Increase qualified enquiries through optimised user journeys.'],
                    ['award', 'Build Credibility', 'Showcase your expertise with a premium brand presence.'],
                    ['speedometer2', 'Improve Speed', 'Achieve 90+ PageSpeed scores for better UX and rankings.'],
                ], 3),
            ]),
            _page('Proposed Solution', [
                _h('Our Approach', 'Proposed Solution'),
                _t('<p>We propose a custom-designed, fully responsive website built on a modern CMS. The website will be engineered for speed, security and search visibility, with an intuitive admin panel so your team can update content with ease.</p>'),
                _h3('Website Features'),
                _l(['Custom responsive design', 'Content management system', 'Contact & quote request forms', 'Blog / news section', 'Google Analytics & Search Console', 'WhatsApp & social integration', 'Image galleries & portfolio', 'SSL security & backups', 'Multi-language ready', 'Cookie consent & GDPR basics'], 'check', 2),
                _h3('Pages Included'),
                _table('Page, Purpose', ['Home | First impression, key services & calls-to-action', 'About | Company story, team and values', 'Services | Detailed service pages', 'Portfolio | Case studies and project gallery', 'Blog | News and SEO content', 'Contact | Form, map and contact details']),
            ]),
            _page('Technology & Design', [
                _h('Under The Hood', 'Technology Stack'),
                _fc([
                    ['code-slash', 'Front-end', 'HTML5, CSS3, Bootstrap 5 and JavaScript for a fast, accessible interface.'],
                    ['server', 'Back-end', 'PHP & MySQL or WordPress CMS on reliable cloud hosting.'],
                    ['cloud', 'Infrastructure', 'SSL, CDN, daily backups and uptime monitoring.'],
                ], 3, 'bordered'),
                _h3('UI/UX Design'),
                _t('<p>We design every page around your users. Clear navigation, strong visual hierarchy and persuasive calls-to-action guide visitors toward enquiring or buying.</p>'),
                _h3('Responsive Design'),
                _t('<p>Over 60% of web traffic is mobile. Your website will adapt perfectly to phones, tablets, laptops and large desktop screens.</p>'),
                _fc([['phone', 'Mobile', 'Thumb-friendly layouts'], ['tablet', 'Tablet', 'Optimised grids'], ['display', 'Desktop', 'Rich, immersive layouts']], 3, 'plain'),
            ]),
            _page('SEO, Speed & Security', [
                _h('Built To Perform', 'SEO, Speed & Security'),
                _h3('Search Engine Optimisation'),
                _l(['Clean semantic HTML and schema markup', 'Optimised titles, meta descriptions and headings', 'XML sitemap, robots.txt and Search Console setup', 'SEO-friendly URLs and internal linking'], 'check'),
                _h3('Speed Optimisation'),
                _l(['Image compression & lazy loading', 'Minified CSS/JS and browser caching', 'CDN delivery and Core Web Vitals tuning'], 'check'),
                _h3('Security'),
                _l(['SSL certificate and HTTPS everywhere', 'Firewall, malware scanning and login protection', 'Automated daily backups'], 'check'),
            ]),
            _page('Development Process', [
                _h('How We Work', 'Development Process'),
                _ps([['search', 'Discovery', 'Workshops, research and sitemap planning.'], ['palette', 'Design', 'Wireframes and high-fidelity UI designs.'], ['code-slash', 'Development', 'Coding, CMS integration and testing.'], ['rocket-takeoff', 'Launch', 'Go-live, training and ongoing support.']]),
                _sp(10),
                _h('Schedule', 'Timeline', '', 'h3'),
                _tl([['Week 1', 'Discovery & Planning', 'Kick-off meeting, requirements and sitemap.'], ['Week 2-3', 'UI/UX Design', 'Homepage and inner page designs with two revision rounds.'], ['Week 4-6', 'Development', 'Responsive build, CMS setup and integrations.'], ['Week 7', 'Testing & QA', 'Cross-browser testing, speed and SEO checks.'], ['Week 8', 'Launch & Training', 'Deployment, handover and team training.']]),
            ]),
            _page('Pricing', [
                _h('Investment', 'Pricing'),
                _price('Project Investment', [['UI/UX Design', 'Custom design for up to 8 pages', 1, 900], ['Website Development', 'Responsive build + CMS', 1, 1600], ['SEO Setup', 'On-page SEO, sitemap, analytics', 1, 300], ['Speed & Security', 'Optimisation, SSL, backups', 1, 200]]),
                _sp(8),
                _payment_schedule(['Project kick-off | 50% | On acceptance', 'Design approval | 25% | End of week 3', 'Website launch | 25% | On go-live']),
            ]),
            _terms_page('<li><b>Hosting & domain.</b> Renewal fees for hosting and domains are the client\'s responsibility.</li>'),
            _contact_page(),
        ],
    ];

    /* ============ WEB DESIGN ============ */
    $p['web-design'] = [
        'name' => 'Web Design Proposal', 'service' => 'Web Design', 'icon' => 'palette',
        'description' => 'UI/UX focused proposal covering strategy, wireframes, visual design and deliverables.',
        'pages' => [
            _cover('Web Design Proposal', 'Beautiful, user-centred design that elevates your brand online.'),
            _page('Introduction & Goals', [
                _h('Introduction', 'Hello {{client_name}},'),
                _t('<p>We are delighted to present our web design proposal for <b>{{client_company}}</b>. This document outlines how we will create a stunning, intuitive design that reflects your brand and delights your customers.</p>'),
                _h('Design Goals', 'What We Will Achieve', '', 'h3'),
                _fc([['brush', 'Elevate The Brand', 'A distinctive visual identity across every page.'], ['person-check', 'Delight Users', 'Effortless navigation and clear content hierarchy.'], ['graph-up-arrow', 'Convert Visitors', 'Strategic layouts that guide users to action.']], 3),
            ]),
            _page('UI/UX Strategy', [
                _h('Strategy', 'UI/UX Strategy'),
                _t('<p>Great design starts with understanding people. We research your audience, competitors and business goals to define user journeys that feel natural and lead to conversions.</p>'),
                _l(['User personas and journey mapping', 'Competitor and inspiration analysis', 'Information architecture & sitemap', 'Accessibility (WCAG) considerations'], 'circle-check'),
                _h3('Design Process'),
                _ps([['search', 'Research', 'Audience & competitor analysis.'], ['layout-text-window', 'Wireframes', 'Low-fidelity page structure.'], ['palette', 'Visual Design', 'High-fidelity mockups.'], ['check-circle', 'Handoff', 'Specs and assets for developers.']]),
            ]),
            _page('Wireframes & Visual Design', [
                _h('Design Phases', 'Wireframes'),
                _t('<p>Wireframes define the structure of each page — content blocks, navigation and calls-to-action — before visual styling is applied. This allows fast feedback and alignment.</p>'),
                ['type' => 'image', 'data' => ['src' => '', 'caption' => 'Example wireframe (replace with your own)', 'width' => 100, 'height' => 220, 'radius' => 12]],
                _h3('Visual Design'),
                _t('<p>We then bring the wireframes to life with your brand colours, typography, imagery and micro-interactions to create a premium, cohesive look.</p>'),
                _h3('Responsive Design'),
                _t('<p>Every layout is designed for mobile, tablet and desktop breakpoints so your website looks perfect everywhere.</p>'),
            ]),
            _page('Deliverables & Timeline', [
                _h('What You Get', 'Deliverables'),
                _l(['Sitemap & user flows', 'Wireframes for all key pages', 'High-fidelity designs (desktop + mobile)', 'Interactive clickable prototype', 'Style guide (colours, typography, components)', 'Exported assets & developer handoff'], 'check', 2),
                _h('Schedule', 'Timeline', '', 'h3'),
                _tl([['Week 1', 'Research & Strategy', 'Discovery workshop and sitemap.'], ['Week 2', 'Wireframes', 'Structure for all key pages.'], ['Week 3-4', 'Visual Design', 'High-fidelity designs and revisions.'], ['Week 5', 'Prototype & Handoff', 'Clickable prototype and design system.']]),
            ]),
            _page('Pricing', [
                _h('Investment', 'Pricing'),
                _price('Design Investment', [['UX Research & Strategy', 'Workshop, personas, sitemap', 1, 400], ['Wireframes', 'Up to 8 pages', 1, 450], ['Visual Design', 'Desktop + mobile designs', 1, 1100], ['Prototype & Style Guide', 'Figma prototype + design system', 1, 350]]),
            ]),
            _terms_page('<li><b>Revisions.</b> Two rounds of revisions are included per design phase.</li>'),
            _contact_page(),
        ],
    ];

    /* ============ SEO ============ */
    $p['seo'] = [
        'name' => 'SEO Proposal', 'service' => 'SEO', 'icon' => 'graph-up-arrow',
        'description' => 'Monthly SEO retainer: audit, keyword research, technical, on-page, local SEO and link building.',
        'pages' => [
            _cover('SEO Proposal', 'A data-driven SEO strategy to increase rankings, traffic and revenue.'),
            _page('SEO Introduction', [
                _h('Introduction', 'Grow Your Organic Visibility'),
                _t('<p>Dear {{client_name}},</p><p>Search engine optimisation is one of the highest-ROI marketing channels available. This proposal outlines how {{company_name}} will help <b>{{client_company}}</b> rank higher on Google, attract qualified visitors and convert them into customers.</p>'),
                _stats([['93%', 'of online experiences begin with a search engine', 'search'], ['75%', 'of users never scroll past page one', 'bar-chart'], ['14.6%', 'average close rate of SEO leads', 'graph-up']]),
            ]),
            _page('Website Analysis', [
                _h('Where You Are Now', 'Current Website Analysis'),
                _t('<p>Our initial review of your website identified the following opportunities:</p>'),
                _table('Area, Current Status, Priority', ['Page speed | Needs improvement | High', 'Mobile usability | Good | Medium', 'Meta titles & descriptions | Missing on several pages | High', 'Backlink profile | Limited authority | High', 'Google Business Profile | Not optimised | Medium']),
                _h('Targets', 'SEO Objectives', '', 'h3'),
                _l(['Rank on page one for priority keywords', 'Increase organic traffic by 100% in 12 months', 'Improve conversion rate from organic visitors', 'Dominate local search results in your area'], 'circle-check'),
            ]),
            _page('Strategy', [
                _h('Our Strategy', 'Keyword Research & Technical SEO'),
                _t('<p>We identify the keywords your customers actually search for, prioritising those with strong commercial intent and achievable competition.</p>'),
                _fc([['search', 'Keyword Research', 'Competitor gap analysis and intent mapping.'], ['tools', 'Technical SEO', 'Crawlability, indexation, Core Web Vitals and schema.'], ['file-earmark-text', 'On-Page SEO', 'Titles, headings, content and internal links.']], 3),
                _h3('Content Strategy'),
                _t('<p>We plan and produce high-quality, SEO-optimised content — service pages, blog posts and guides — that answer your customers\' questions and earn links.</p>'),
                _h3('Local SEO'),
                _l(['Google Business Profile optimisation', 'Local citations and NAP consistency', 'Review generation strategy', 'Location landing pages'], 'check', 2),
                _h3('Link Building'),
                _t('<p>White-hat outreach, digital PR and guest posting on relevant, authoritative websites to build your domain authority.</p>'),
            ]),
            _page('Reporting & Deliverables', [
                _h('Transparency', 'Monthly Reporting'),
                _t('<p>Every month you receive a clear report covering rankings, traffic, conversions and the work completed — plus a call to discuss results and next steps.</p>'),
                _h('Monthly Deliverables', 'Expected Deliverables', '', 'h3'),
                _l(['Keyword ranking report', '4 SEO blog articles', '10 quality backlinks', 'Technical health monitoring', 'On-page optimisation of 5 pages', 'Monthly strategy call'], 'check', 2),
                _h('Roadmap', 'Timeline', '', 'h3'),
                _tl([['Month 1', 'Audit & Foundations', 'Full audit, keyword research and technical fixes.'], ['Month 2-3', 'Optimisation', 'On-page SEO, content and local SEO.'], ['Month 4-6', 'Authority Building', 'Link building and content expansion.'], ['Month 6+', 'Scale & Grow', 'Expand keywords and conversion optimisation.']]),
            ]),
            _page('Monthly Pricing', [
                _h('Investment', 'Monthly Pricing'),
                ['type' => 'comparison_table', 'data' => ['title' => 'SEO Packages', 'headers' => 'Starter | Growth | Premium', 'prices' => '$350/mo | $650/mo | $1,200/mo', 'highlight' => 2, 'rows' => [
                    ['feature' => 'Target keywords', 'values' => '10 | 25 | 50'],
                    ['feature' => 'Blog articles / month', 'values' => '2 | 4 | 8'],
                    ['feature' => 'Backlinks / month', 'values' => '5 | 10 | 20'],
                    ['feature' => 'Local SEO', 'values' => 'yes | yes | yes'],
                    ['feature' => 'Technical SEO', 'values' => 'Basic | Advanced | Advanced'],
                    ['feature' => 'Strategy call', 'values' => 'no | yes | yes'],
                ]]],
                _sp(10),
                _price('Selected Package', [['SEO Growth Package', 'Monthly SEO retainer', 1, 650], ['One-time SEO Audit & Setup', 'Technical fixes and tracking', 1, 300]], 0, 'Monthly fees are billed in advance. Minimum engagement of 6 months.'),
            ]),
            _terms_page('<li><b>Results.</b> SEO results depend on many external factors; no specific ranking can be guaranteed.</li><li><b>Minimum term.</b> SEO services require a minimum 6-month commitment.</li>'),
            _contact_page(),
        ],
    ];

    /* ============ GRAPHICS ============ */
    $p['graphic-design'] = [
        'name' => 'Graphics Design Proposal', 'service' => 'Graphic Design', 'icon' => 'brush',
        'description' => 'Branding & graphic design proposal with creative direction, concepts and revision policy.',
        'pages' => [
            _cover('Graphic Design Proposal', 'Creative design that captures attention and communicates your brand.'),
            _page('Introduction', [
                _h('Introduction', 'Hello {{client_name}},'),
                _t('<p>Thank you for inviting {{company_name}} to submit a proposal for your graphic design project. We specialise in creating visuals that are not only beautiful, but strategically designed to help <b>{{client_company}}</b> stand out.</p>'),
                _h('Your Brief', 'Design Requirements', '', 'h3'),
                _l(['Logo and visual identity refresh', 'Business stationery (cards, letterhead)', 'Social media templates', 'Marketing collateral (brochure, flyers)'], 'circle-check'),
            ]),
            _page('Brand & Creative Direction', [
                _h('Strategy', 'Brand Understanding'),
                _t('<p>Before designing, we immerse ourselves in your brand — your audience, values, personality and competitors — so every design decision is purposeful.</p>'),
                _h3('Creative Direction'),
                _fc([['palette', 'Colour Palette', 'A distinctive palette that evokes the right emotions.'], ['type', 'Typography', 'Typefaces that are legible and on-brand.'], ['images', 'Imagery Style', 'Consistent photography and illustration direction.']], 3),
                _h3('Design Concepts'),
                _t('<p>We will present <b>3 unique initial concepts</b>. You choose your favourite direction and we refine it into the final design.</p>'),
            ]),
            _page('Deliverables & Revisions', [
                _h('What You Get', 'Deliverables'),
                _sc([['brush', 'Logo & Identity', 'Primary logo, variations and brand guidelines.', "Primary & secondary logo\nIcon / favicon\nColour & type guide\nAll file formats", ''], ['file-earmark-text', 'Print & Digital', 'Stationery and marketing materials.', "Business cards\nLetterhead\nBrochure design\n10 social templates", '']], 2),
                _h('Revisions', 'Revision Policy', '', 'h3'),
                _t('<p>Each deliverable includes <b>three rounds of revisions</b>. Additional revisions are charged at an hourly rate. Revisions must be requested within 14 days of delivery.</p>'),
                _h('Schedule', 'Timeline', '', 'h3'),
                _tl([['Week 1', 'Discovery & Moodboards', 'Brand questionnaire and creative direction.'], ['Week 2', 'Initial Concepts', 'Presentation of 3 concepts.'], ['Week 3', 'Refinement', 'Revisions on selected concept.'], ['Week 4', 'Final Delivery', 'Final files and brand guidelines.']]),
            ]),
            _page('Pricing', [
                _h('Investment', 'Pricing'),
                _price('Design Investment', [['Logo Design', '3 concepts, 3 revisions', 1, 450], ['Brand Guidelines', 'Colour, typography, usage rules', 1, 250], ['Stationery Set', 'Business card & letterhead', 1, 150], ['Social Media Templates', '10 editable templates', 1, 200]]),
            ]),
            _terms_page('<li><b>Source files.</b> Editable source files are delivered after final payment.</li>'),
        ],
    ];

    /* ============ VIDEO ============ */
    $p['video-editing'] = [
        'name' => 'Video Editing Proposal', 'service' => 'Video Editing', 'icon' => 'film',
        'description' => 'Video editing proposal: storytelling, motion graphics, colour grading and sound design.',
        'pages' => [
            _cover('Video Editing Proposal', 'Professional editing that turns raw footage into compelling stories.'),
            _page('Project Overview', [
                _h('Overview', 'Project Overview'),
                _t('<p>Dear {{client_name}}, we are excited to help <b>{{client_company}}</b> produce engaging video content. This proposal covers our editing approach, deliverables, timeline and investment.</p>'),
                _h('Your Brief', 'Video Requirements', '', 'h3'),
                _table('Requirement, Details', ['Video type | Promotional / brand video', 'Length | 60–90 seconds + short cut-downs', 'Formats | 16:9, 9:16 and 1:1', 'Footage | Supplied by client', 'Music | Licensed royalty-free track']),
            ]),
            _page('Editing Approach', [
                _h('Our Craft', 'Editing Style & Storytelling'),
                _t('<p>We craft a clear narrative arc — hook, story and call-to-action — paced to keep viewers watching until the end.</p>'),
                _fc([['film', 'Storytelling', 'Structure and pacing that keeps viewers engaged.'], ['magic', 'Motion Graphics', 'Animated titles, lower thirds and logo reveals.'], ['palette', 'Colour Grading', 'Cinematic, consistent colour across all shots.']], 3),
                _fc([['broadcast', 'Sound Design', 'Music, SFX, voice clean-up and mixing.'], ['phone-vibrate', 'Social Ready', 'Vertical cut-downs for Reels, Shorts and TikTok.'], ['translate', 'Subtitles', 'Accurate captions for silent autoplay.']], 3),
            ]),
            _page('Deliverables & Timeline', [
                _h('What You Get', 'Deliverables'),
                _l(['1 × master video (60–90 sec, 4K)', '3 × social cut-downs (15–30 sec)', 'Burned-in & SRT subtitles', 'Thumbnail design', 'Project files on request'], 'check'),
                _h3('Revisions'),
                _t('<p>Two rounds of revisions are included. Feedback should be consolidated and provided with timestamps.</p>'),
                _h('Schedule', 'Timeline', '', 'h3'),
                _tl([['Day 1-2', 'Footage Review', 'Selects and story outline.'], ['Day 3-5', 'First Cut', 'Rough edit with music.'], ['Day 6-8', 'Fine Cut', 'Motion graphics, grade and sound.'], ['Day 9-10', 'Final Delivery', 'Exports in all formats.']]),
            ]),
            _page('Pricing', [
                _h('Investment', 'Pricing'),
                _price('Video Editing Investment', [['Master Video Edit', 'Up to 90 seconds', 1, 600], ['Motion Graphics Package', 'Titles, lower thirds, logo animation', 1, 250], ['Colour Grading & Sound', 'Grade, mix and mastering', 1, 200], ['Social Cut-downs', 'Vertical & square versions', 3, 80]]),
            ]),
            _terms_page('<li><b>Footage & licences.</b> The client warrants that it owns or has licensed all supplied footage and assets.</li>'),
        ],
    ];

    /* ============ MOBILE APP ============ */
    $p['mobile-app'] = [
        'name' => 'Mobile App Development Proposal', 'service' => 'Mobile App Development', 'icon' => 'phone',
        'description' => 'Android & iOS app proposal with backend/API, admin panel, testing and deployment.',
        'pages' => [
            _cover('Mobile App Development Proposal', 'A powerful, scalable mobile app for Android and iOS.'),
            _page('Project Overview', [
                _h('Overview', 'Project Overview'),
                _t('<p>Dear {{client_name}},</p><p>{{company_name}} is pleased to propose the design and development of a cross-platform mobile application for <b>{{client_company}}</b>. The app will deliver a fast, intuitive experience on both Android and iOS, backed by a secure cloud backend and an admin panel.</p>'),
                _h('Goals', 'App Objectives', '', 'h3'),
                _l(['Deliver a seamless experience on Android and iOS', 'Increase customer engagement and retention', 'Enable secure user accounts and data', 'Provide real-time notifications', 'Give your team full control via an admin panel'], 'circle-check'),
                _h('Audience', 'Target Audience', '', 'h3'),
                _t('<p>The primary audience is smartphone users aged 18–45 who value speed, simplicity and convenience. The design will prioritise one-handed use and accessibility.</p>'),
            ]),
            _page('Scope of Work', [
                _h('Scope', 'Features & Technology'),
                _sc([
                    ['palette', 'UI/UX Design', 'Modern, intuitive app screens.', "User flows\nWireframes\nHigh-fidelity UI\nClickable prototype", ''],
                    ['android2', 'Android Development', 'Native-quality Android app.', "Flutter / React Native\nMaterial design\nOffline support\nPlay Store ready", ''],
                    ['apple', 'iOS Development', 'Polished iPhone experience.', "Human Interface guidelines\nFace ID support\nApp Store ready\niPad compatible", ''],
                    ['server', 'Backend / API', 'Secure, scalable cloud backend.', "REST API\nDatabase design\nCloud hosting\nPayment gateway", ''],
                ], 2),
            ]),
            _page('Platform Features', [
                _h('Platform', 'Core Platform Features'),
                _fc([['key', 'Authentication', 'Email, phone OTP and social login with secure tokens.'], ['bell', 'Notifications', 'Push notifications with segmentation and scheduling.'], ['kanban', 'Admin Panel', 'Web dashboard to manage users, content and analytics.']], 3),
                _h3('Testing & Quality Assurance'),
                _l(['Unit and integration testing', 'Manual QA on real devices', 'Performance and security testing', 'Beta testing via TestFlight & Play Console'], 'check', 2),
                _h3('App Store Deployment'),
                _t('<p>We handle app store listings, screenshots, compliance checks and submission to the Google Play Store and Apple App Store.</p>'),
                _h3('Maintenance'),
                _t('<p>After launch we provide 60 days of free bug-fix support, followed by optional monthly maintenance covering OS updates, monitoring and improvements.</p>'),
            ]),
            _page('Timeline', [
                _h('Schedule', 'Timeline'),
                _tl([['Week 1-2', 'Discovery & UX', 'Requirements, user flows and wireframes.'], ['Week 3-4', 'UI Design', 'High-fidelity screens and prototype.'], ['Week 5-10', 'Development', 'App, backend and admin panel sprints.'], ['Week 11', 'Testing', 'QA, beta testing and fixes.'], ['Week 12', 'Launch', 'Store submission and go-live.']]),
            ]),
            _page('Pricing', [
                _h('Investment', 'Pricing'),
                _price('App Development Investment', [['UI/UX Design', 'Up to 25 screens', 1, 2500], ['Android & iOS App', 'Cross-platform development', 1, 9000], ['Backend & API', 'Database, API, hosting setup', 1, 4000], ['Admin Panel', 'Web dashboard', 1, 2500], ['Testing & Deployment', 'QA and store submission', 1, 1200]]),
                _sp(8),
                _payment_schedule(['Kick-off | 30% | On acceptance', 'Design approval | 20% | Week 4', 'Beta release | 30% | Week 11', 'Store launch | 20% | Week 12']),
            ]),
            _terms_page('<li><b>Developer accounts.</b> Apple Developer and Google Play accounts are registered in the client\'s name and paid by the client.</li>'),
        ],
    ];

    /* ============ SOCIAL MEDIA ============ */
    $p['social-media'] = [
        'name' => 'Social Media Marketing Proposal', 'service' => 'Social Media Marketing', 'icon' => 'megaphone',
        'description' => 'Monthly social media management: strategy, content, reels, community & paid ads.',
        'pages' => [
            _cover('Social Media Marketing Proposal', 'Strategic social media that builds community and drives growth.'),
            _page('Introduction & Audit', [
                _h('Introduction', 'Hi {{client_name}},'),
                _t('<p>Social media is where your customers spend their time. This proposal outlines how {{company_name}} will grow <b>{{client_company}}</b>\'s presence, engagement and sales through consistent, high-quality content and targeted advertising.</p>'),
                _h('Where You Are', 'Social Media Audit', '', 'h3'),
                _table('Platform, Followers, Engagement, Opportunity', ['Instagram | 1,200 | Low | Reels & consistency', 'Facebook | 2,500 | Low | Community & ads', 'LinkedIn | 300 | Medium | Thought leadership', 'TikTok | — | — | New channel']),
            ]),
            _page('Strategy', [
                _h('Strategy', 'Our Social Media Strategy'),
                _fc([['bullseye', 'Awareness', 'Reach new audiences with shareable content.'], ['chat-dots', 'Engagement', 'Build a loyal community through conversation.'], ['cart-check', 'Conversion', 'Turn followers into leads and customers.']], 3),
                _h3('Content Strategy'),
                _t('<p>Our content mix balances education, inspiration, entertainment and promotion, built around content pillars tailored to your brand.</p>'),
                _h3('Content Calendar'),
                _table('Day, Content, Platform', ['Monday | Educational carousel | Instagram, LinkedIn', 'Wednesday | Reel / short video | Instagram, TikTok', 'Friday | Customer story / testimonial | Facebook, Instagram', 'Weekly | Stories & polls | Instagram, Facebook']),
            ]),
            _page('Content Production', [
                _h('Production', 'Graphic Design & Video'),
                _t('<p>All posts are custom designed in your brand style. We produce eye-catching graphics, carousels and short-form videos optimised for each platform.</p>'),
                _fc([['image', 'Graphic Design', 'Branded posts, carousels and story designs.'], ['camera-video', 'Video / Reels', 'Edited reels, captions and trending audio.'], ['calendar-check', 'Posting Schedule', 'Posts published at peak engagement times.']], 3),
                _h3('Community Management'),
                _l(['Respond to comments and DMs within business hours', 'Engage with followers and relevant accounts', 'Monitor brand mentions and reviews'], 'check'),
                _h3('Paid Advertising'),
                _t('<p>We plan, launch and optimise Meta (Facebook & Instagram) ad campaigns with precise targeting, A/B testing and retargeting. Ad spend is paid directly by the client.</p>'),
            ]),
            _page('Analytics & Deliverables', [
                _h('Measurement', 'Analytics & Monthly Reporting'),
                _t('<p>We track reach, engagement, follower growth, website clicks and conversions. A monthly report and strategy call keep you informed and allow continuous improvement.</p>'),
                _stats([['+150%', 'Target reach growth', 'graph-up-arrow'], ['+80%', 'Target engagement', 'heart'], ['3x', 'Target ad ROAS', 'cash-coin']]),
                _h('Every Month', 'Deliverables', '', 'h3'),
                _l(['16 feed posts (graphics & carousels)', '8 Reels / short videos', '20 stories', 'Community management', 'Ad campaign management', 'Monthly analytics report'], 'check', 2),
            ]),
            _page('Monthly Pricing', [
                _h('Investment', 'Monthly Pricing'),
                _price('Monthly Retainer', [['Social Media Management', '3 platforms, 16 posts', 1, 600], ['Reels Production', '8 edited short videos', 1, 400], ['Paid Ads Management', 'Meta ads setup & optimisation', 1, 250]], 0, 'Fees billed monthly in advance. Ad spend is not included.'),
            ]),
            _terms_page('<li><b>Approvals.</b> Content calendars are shared one week in advance for approval.</li><li><b>Ad spend.</b> Advertising budgets are paid directly to the platforms by the client.</li>'),
        ],
    ];

    return $p;
}

/** Default content library sections. */
function pm_prebuilt_library(): array
{
    $about = _about_page()['blocks'];
    return [
        ['name' => 'About Company', 'category' => 'Company', 'blocks' => $about],
        ['name' => 'Web Development Introduction', 'category' => 'Introductions', 'blocks' => [
            _h('Introduction', 'Dear {{client_name}},'),
            _t('<p>Thank you for considering <b>{{company_name}}</b> for your new website. We have prepared this proposal to outline our approach, deliverables, timeline and investment for <b>{{client_company}}</b>.</p>'),
        ]],
        ['name' => 'SEO Introduction', 'category' => 'Introductions', 'blocks' => [
            _h('Introduction', 'Grow Your Organic Visibility'),
            _t('<p>Search engine optimisation is one of the highest-ROI marketing channels. This proposal outlines how we will help {{client_company}} rank higher, attract qualified visitors and grow revenue.</p>'),
        ]],
        ['name' => 'Our Process', 'category' => 'Process', 'blocks' => [
            _h('How We Work', 'Our Process'),
            _ps([['search', 'Discover', 'We learn about your goals and audience.'], ['palette', 'Design', 'We craft the look and experience.'], ['code-slash', 'Develop', 'We build, test and optimise.'], ['rocket-takeoff', 'Launch', 'We go live and support growth.']]),
        ]],
        ['name' => 'Why Choose Us', 'category' => 'Company', 'blocks' => [
            _h('Why Us', 'Why Choose {{company_name}}'),
            _fc([['people', 'Expert Team', 'Senior specialists on every project.'], ['clock', 'On-Time Delivery', 'Clear milestones and weekly updates.'], ['hand-thumbs-up', 'Satisfaction Guaranteed', 'We work until you are delighted.'], ['shield-check', 'Secure & Reliable', 'Best practices in security and quality.']], 2),
        ]],
        ['name' => 'Payment Terms', 'category' => 'Business', 'blocks' => [
            _h('Payments', 'Payment Terms', '', 'h3'),
            _payment_schedule(['Project kick-off | 50% | On acceptance', 'Midpoint | 25% | On design approval', 'Completion | 25% | On launch']),
        ]],
        ['name' => 'Terms & Conditions', 'category' => 'Business', 'blocks' => _terms_page()['blocks']],
        ['name' => 'About Team', 'category' => 'Company', 'blocks' => [
            _h('Our People', 'Meet The Team'),
            ['type' => 'team', 'data' => []],
        ]],
        ['name' => 'Contact Us', 'category' => 'Closing', 'blocks' => [['type' => 'contact', 'data' => ['style' => 'card']]]],
        ['name' => 'Call To Action', 'category' => 'Closing', 'blocks' => [['type' => 'cta', 'data' => []]]],
        ['name' => 'Client Testimonials', 'category' => 'Company', 'blocks' => [_h('Testimonials', 'What Our Clients Say'), ['type' => 'testimonial', 'data' => []]]],
    ];
}
