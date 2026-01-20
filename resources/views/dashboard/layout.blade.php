<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Guard</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.5rem; font-weight: 700; color: #f8fafc; }
        .header h1 span { color: #38bdf8; }
        .nav { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .nav a { padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; color: #94a3b8; font-size: 0.875rem; transition: all 0.2s; }
        .nav a:hover, .nav a.active { background: #1e293b; color: #f8fafc; }
        .period-selector { display: flex; gap: 0.25rem; background: #1e293b; border-radius: 0.5rem; padding: 0.25rem; }
        .period-selector a { padding: 0.375rem 0.75rem; border-radius: 0.375rem; text-decoration: none; color: #94a3b8; font-size: 0.75rem; transition: all 0.2s; }
        .period-selector a:hover, .period-selector a.active { background: #334155; color: #f8fafc; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #1e293b; border-radius: 0.75rem; padding: 1.25rem; border: 1px solid #334155; }
        .stat-card .label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; color: #f8fafc; }
        .stat-card .value.warning { color: #fbbf24; }
        .stat-card .value.danger { color: #f87171; }
        .stat-card .value.success { color: #34d399; }
        .grade-bar { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .grade-item { flex: 1; text-align: center; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; border: 1px solid #334155; }
        .grade-item .letter { font-size: 1.25rem; font-weight: 700; }
        .grade-item .count { font-size: 0.875rem; color: #94a3b8; }
        .grade-a { color: #34d399; }
        .grade-b { color: #38bdf8; }
        .grade-c { color: #fbbf24; }
        .grade-d { color: #fb923c; }
        .grade-f { color: #f87171; }
        .table-container { background: #1e293b; border-radius: 0.75rem; border: 1px solid #334155; overflow-x: auto; }
        .table-container h2 { padding: 1rem 1.25rem; font-size: 1rem; border-bottom: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #334155; }
        td { padding: 0.75rem 1rem; font-size: 0.875rem; border-bottom: 1px solid #1e293b; }
        tr:hover td { background: #334155; }
        .badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-a { background: #064e3b; color: #34d399; }
        .badge-b { background: #0c4a6e; color: #38bdf8; }
        .badge-c { background: #78350f; color: #fbbf24; }
        .badge-d { background: #7c2d12; color: #fb923c; }
        .badge-f { background: #7f1d1d; color: #f87171; }
        .badge-warning { background: #78350f; color: #fbbf24; }
        .badge-danger { background: #7f1d1d; color: #f87171; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
        .method { font-weight: 600; font-size: 0.75rem; }
        .method-get { color: #34d399; }
        .method-post { color: #38bdf8; }
        .method-put { color: #fbbf24; }
        .method-patch { color: #fb923c; }
        .method-delete { color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
