<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #6B6375; font: 11px/1.45 "Source Sans Variable", "Source Sans Pro", Calibri, "Open Sans", Helvetica, Arial, sans-serif; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 3px; color: #08060D; font-size: 20px; }
        h2 { margin: 0 0 14px; font-size: 15px; }
        h3 { margin: 16px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #E5E4E7; color: #13294B; font-size: 12px; }
        .masthead { margin-bottom: 16px; border-bottom: 3px solid #F9D028; padding-bottom: 10px; text-align: center; }
        .system { color: #6B6375; font-size: 10px; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { width: 25%; padding: 4px 7px; border: 1px solid #E5E4E7; vertical-align: top; }
        .label { display: block; color: #6B6375; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8px; }
        .data th { padding: 5px; border: 1px solid #E5E4E7; background: #F5F8FC; color: #13294B; text-align: left; }
        .data td { padding: 5px; border: 1px solid #E5E4E7; overflow-wrap: anywhere; vertical-align: top; }
        .summary { width: 100%; border-collapse: collapse; }
        .summary td { width: 25%; padding: 7px; border: 1px solid #E5E4E7; }
        .value { font-size: 14px; font-weight: bold; }
        ul { margin: 5px 0 10px; padding-left: 18px; }
        .muted { color: #6B6375; }
        .badge { display: inline-block; padding: 2px 7px; border: 1px solid #1E73BE; color: #13294B; font-weight: bold; }
        .actions { margin: 0 auto 16px; max-width: 1100px; text-align: right; }
        .actions button { border: 0; border-radius: 5px; background: #F9D028; padding: 9px 16px; color: #001F3F; font-weight: bold; cursor: pointer; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        @media print { .actions { display: none !important; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
@unless($isPdf ?? false)<div class="actions"><button type="button" onclick="window.print()">Print</button></div>@endunless
@yield('content')
</body>
</html>
