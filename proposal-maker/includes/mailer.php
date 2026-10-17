<?php
/**
 * EMAIL — sending proposals by email.
 *
 * Uses PHPMailer. If SMTP is turned on in Settings → Email, it sends through
 * your mail server; otherwise it uses PHP's normal mail() function.
 */

function pm_mailer()
{
    require_once APP_ROOT . '/vendor/autoload.php';
    $co = company();
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    $m->CharSet = 'UTF-8';
    if (!empty($co['smtp_enabled']) && !empty($co['smtp_host'])) {
        $m->isSMTP();
        $m->Host = $co['smtp_host'];
        $m->Port = (int) ($co['smtp_port'] ?: 587);
        $m->SMTPAuth = !empty($co['smtp_user']);
        $m->Username = (string) $co['smtp_user'];
        $m->Password = (string) $co['smtp_pass'];
        $sec = strtolower((string) $co['smtp_secure']);
        if ($sec === 'ssl') $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($sec === 'tls') $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else {
            $m->SMTPSecure = '';
            $m->SMTPAutoTLS = false;
        }
        $m->Timeout = 20;
    } else {
        $m->isMail();
    }
    $from = $co['smtp_from_email'] ?: ($co['email'] ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $m->setFrom($from, $co['smtp_from_name'] ?: ($co['company_name'] ?? ''));
    if (!empty($co['email']) && $co['email'] !== $from) $m->addReplyTo($co['email'], $co['company_name'] ?? '');
    return $m;
}

function pm_send_mail($to, $subject, $html, $attachments = [], $cc = [])
{
    $m = pm_mailer();
    $m->addAddress($to);
    foreach ($cc as $c) $m->addCC($c);
    $m->isHTML(true);
    $m->Subject = $subject;
    $m->Body = $html;
    $m->AltBody = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html))));
    foreach ($attachments as $name => $data) $m->addStringAttachment($data, $name, 'base64', 'application/pdf');
    $m->send();
}

/** Branded HTML email wrapper. */
function pm_email_html($bodyText, $buttonText = '', $buttonUrl = '')
{
    $co = company();
    $primary = clean_color($co['primary_color'] ?? '', '#015957');
    $logo = !empty($co['logo']) ? '<img src="' . e(media_src($co['logo'])) . '" alt="" style="max-height:44px">' : '<b style="font-size:20px;color:#0f172a">' . e($co['company_name']) . '</b>';
    $paras = '';
    foreach (preg_split('/\n{2,}/', trim($bodyText)) as $p) {
        $paras .= '<p style="margin:0 0 16px;line-height:1.6">' . nl2br(e($p)) . '</p>';
    }
    $btn = $buttonUrl ? '<p style="margin:26px 0"><a href="' . e($buttonUrl) . '" style="background:' . $primary . ';color:#fff;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:bold;display:inline-block">' . e($buttonText) . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#334155">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="padding:24px 32px;border-bottom:4px solid ' . $primary . '">' . $logo . '</td></tr>'
        . '<tr><td style="padding:32px;font-size:15px">' . $paras . $btn . '</td></tr>'
        . '<tr><td style="padding:18px 32px;background:#f8fafc;font-size:12px;color:#94a3b8">' . e($co['company_name']) . ($co['website'] ? ' · ' . e($co['website']) : '') . ($co['phone'] ? ' · ' . e($co['phone']) : '') . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function pm_send_proposal_email($p, $to, $cc, $subject, $body, $attachPdf)
{
    $pages = pm_pages((int) $p['id']);
    $doc = doc_create($p, $pages, company(), 'screen');
    $link = public_url($p['public_token']);
    $subject = replace_vars($doc, $subject, false);
    $body = replace_vars($doc, $body, false);
    $body = str_replace('{{proposal_link}}', $link, $body);
    // the link is rendered as a button; drop a bare link line from the text
    $bodyNoLink = trim(str_replace($link, '', $body));
    $html = pm_email_html($bodyNoLink, 'View Proposal', $link);
    $att = [];
    if ($attachPdf) $att[pm_filename($p)] = pm_pdf($p, $pages);
    pm_send_mail($to, $subject, $html, $att, $cc);
}

/** Notify the company when a client responds. Fails silently. */
function pm_notify_response($p, $response, $name, $comment)
{
    $co = company();
    $to = $co['notify_email'] ?: $co['email'];
    if (!$to) return;
    try {
        $txt = "Good news" . ($response === 'accepted' ? '!' : '.') . "\n\n{$name} has " . $response . " the proposal \"{$p['title']}\" ({$p['proposal_number']})."
            . ($comment !== '' ? "\n\nComment:\n" . $comment : '');
        pm_send_mail($to, 'Proposal ' . $response . ': ' . $p['title'], pm_email_html($txt, 'Open proposal', url('proposal.php?id=' . (int) $p['id'])));
    } catch (Throwable $e) {
        // ignore mail failures
    }
}
