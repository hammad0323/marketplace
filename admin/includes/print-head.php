<?php if (!defined('FS_BOOT')) { http_response_code(403); exit; } ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title><?= e($printTitle) ?></title>
<style>
  * { box-sizing: border-box; } body { font-family: Arial, Helvetica, sans-serif; color: #1f2933; margin: 0; font-size: 13px; }
  .page { max-width: 800px; margin: 20px auto; padding: 28px; background: #fff; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
  .head img { height: 54px; } h1 { font-size: 22px; margin: 0 0 4px; color: #0f766e; }
  table { width: 100%; border-collapse: collapse; } th, td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
  th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; } .r { text-align: right; } .c { text-align: center; }
  .grid { display: flex; gap: 24px; margin-bottom: 16px; } .grid > div { flex: 1; } .muted { color: #6b7280; } .big { font-size: 18px; font-weight: bold; }
  .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; } .chk { width: 16px; height: 16px; border: 1.5px solid #111; display: inline-block; }
  .cat { background: #ecfdf5; font-weight: bold; } .noprint { text-align: center; margin: 12px; }
  .noprint button { background: #0f766e; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; font-size: 14px; cursor: pointer; }
  @media print { .noprint { display: none; } .page { margin: 0; padding: 10px; max-width: none; } body { background: #fff; } }
  body { background: #f3f4f6; }
</style></head><body>
<div class="noprint"><button onclick="window.print()">Print</button></div>
