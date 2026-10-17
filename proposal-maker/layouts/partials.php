<?php
/** Reusable admin UI fragments. */

function proposal_actions(array $p): string
{
    $id = (int) $p['id'];
    $link = public_url($p['public_token']);
    return '<div class="act-btns">'
        . '<a class="btn" href="' . url('proposal.php?id=' . $id) . '" title="View details" data-bs-toggle="tooltip"><i class="bi bi-eye"></i></a>'
        . '<a class="btn" href="' . url('editor.php?id=' . $id) . '" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil-square"></i></a>'
        . '<button class="btn" data-act="duplicate" data-id="' . $id . '" title="Duplicate" data-bs-toggle="tooltip"><i class="bi bi-copy"></i></button>'
        . '<a class="btn" href="' . url('pdf.php?id=' . $id) . '" title="Download PDF" data-bs-toggle="tooltip"><i class="bi bi-file-earmark-pdf"></i></a>'
        . '<button class="btn" data-act="share" data-id="' . $id . '" data-link="' . e($link) . '" data-email="' . e($p['client_email'] ?? '') . '" data-title="' . e($p['title']) . '" title="Share" data-bs-toggle="tooltip"><i class="bi bi-share"></i></button>'
        . '<button class="btn text-danger" data-act="delete" data-id="' . $id . '" title="Delete" data-bs-toggle="tooltip"><i class="bi bi-trash3"></i></button>'
        . '</div>';
}

function proposal_rows(array $rows): string
{
    if (!$rows) {
        return '<tr><td colspan="7"><div class="empty"><i class="bi bi-file-earmark-plus"></i>No proposals yet.<div class="mt-3"><a href="' . url('proposal-create.php') . '" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Create your first proposal</a></div></div></td></tr>';
    }
    $h = '';
    foreach ($rows as $p) {
        $client = $p['client_company'] ?: $p['client_name'];
        $h .= '<tr>'
            . '<td><a class="p-title" href="' . url('editor.php?id=' . (int) $p['id']) . '">' . e($p['title']) . '</a><div class="p-sub">' . e($p['proposal_number']) . ' · ' . e(pm_template((int) $p['template_id'])['name']) . '</div></td>'
            . '<td><div class="fw-semibold text-dark">' . e($client) . '</div><div class="p-sub">' . e($p['client_email']) . '</div></td>'
            . '<td>' . ($p['service_type'] ? '<span class="chip">' . e($p['service_type']) . '</span>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td class="fw-semibold text-dark text-nowrap">' . e(money($p['total'], $p['currency_symbol'])) . '</td>'
            . '<td>' . status_badge($p['status']) . ($p['views'] ? '<div class="p-sub mt-1"><i class="bi bi-eye"></i> ' . (int) $p['views'] . ' views</div>' : '') . '</td>'
            . '<td class="text-nowrap"><div>' . e(fmt_date($p['created_at'])) . '</div><div class="p-sub">' . e(time_ago($p['updated_at'] ?: $p['created_at'])) . '</div></td>'
            . '<td>' . proposal_actions($p) . '</td>'
            . '</tr>';
    }
    return $h;
}

/** Scaled live thumbnail of a template cover (rendered with sample data). */
function template_thumb(int $tplId, array $colors = [], bool $withInner = true): string
{
    static $cache = [];
    $key = $tplId . md5(json_encode($colors)) . (int) $withInner;
    if (isset($cache[$key])) return $cache[$key];
    $tpl = pm_template($tplId);
    $p = [
        'id' => 0, 'title' => 'Website Development Proposal', 'proposal_number' => 'PROP-' . date('Y') . '-001',
        'client_name' => 'Ahmed Khan', 'client_company' => 'ABC Company', 'template_id' => $tplId,
        'colors' => $colors ?: $tpl['colors'], 'settings' => [], 'currency_symbol' => '$', 'total' => 3200,
        'proposal_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+30 days')), 'public_token' => '',
    ];
    $pages = pm_structure_instantiate([
        ['title' => 'Cover', 'page_type' => 'cover', 'blocks' => [['type' => 'cover', 'data' => ['subtitle' => 'A modern website built to grow your business.']]]],
        ['title' => 'Services', 'blocks' => [
            ['type' => 'heading', 'data' => ['eyebrow' => 'What we offer', 'text' => 'Our Services']],
            ['type' => 'text', 'data' => ['html' => '<p>We design and build high-performing digital experiences that help businesses grow online and convert visitors into loyal customers.</p>']],
            ['type' => 'feature_cards', 'data' => []],
            ['type' => 'stats', 'data' => []],
            ['type' => 'pricing_table', 'data' => []],
        ]],
    ]);
    $r = new ProposalRenderer($p, $pages, company(), 'screen');
    $sheets = $r->renderSheets();
    $h = '<div class="mini-wrap"><div ' . $r->docAttrs('mini') . '>' . $sheets[0] . '</div></div>';
    if ($withInner && isset($sheets[1])) $h .= '<div class="mini-wrap"><div ' . $r->docAttrs('mini') . '>' . $sheets[1] . '</div></div>';
    return $cache[$key] = $h;
}

function proposal_head_assets(): string
{
    $fonts = [];
    foreach (pm_templates() as $t) {
        $fonts[] = $t['fonts']['heading'];
        $fonts[] = $t['fonts']['body'];
    }
    return '<link rel="stylesheet" href="' . asset('css/proposal.css') . '"><link rel="stylesheet" href="' . asset('css/templates.css') . '"><style>' . font_face_css(array_keys(font_list())) . '</style>';
}

/** Mini preview sheet of a list of blocks (content library). */
function blocks_thumb(array $blocks, int $tplId = 1): string
{
    $tpl = pm_template($tplId);
    $p = ['id' => 0, 'title' => 'Sample Proposal', 'proposal_number' => 'PROP-0000', 'client_name' => 'Client Name', 'client_company' => 'Client Company',
        'template_id' => $tplId, 'colors' => $tpl['colors'], 'settings' => [], 'currency_symbol' => '$', 'total' => 0,
        'proposal_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+30 days')), 'public_token' => ''];
    $pages = pm_structure_instantiate([['title' => 'Preview', 'blocks' => $blocks]]);
    $r = new ProposalRenderer($p, $pages, company(), 'screen');
    $sheets = $r->renderSheets();
    return '<div class="mini-wrap"><div ' . $r->docAttrs('mini') . '>' . ($sheets[0] ?? '') . '</div></div>';
}
