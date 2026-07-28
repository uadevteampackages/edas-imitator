<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Imitator</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --ink: #1a1f24;
            --muted: #5c6b7a;
            --accent: #900;
            --accent-soft: #f8e8e8;
            --border: #d5dde5;
            --ok: #0f7b4b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background: linear-gradient(160deg, #eef2f6 0%, #f8f1ef 100%);
            color: var(--ink);
            min-height: 100vh;
        }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 4rem;
        }
        h1 {
            font-size: 2rem;
            margin: 0 0 0.35rem;
            letter-spacing: -0.02em;
        }
        .lede {
            color: var(--muted);
            margin: 0 0 1.75rem;
        }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .panel h2 {
            margin: 0 0 0.75rem;
            font-size: 1.05rem;
        }
        .active {
            background: var(--accent-soft);
            border-color: #e0b3b3;
        }
        label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }
        input[type="search"] {
            width: 100%;
            padding: 0.7rem 0.8rem;
            border: 1px solid var(--border);
            font: inherit;
        }
        .results {
            list-style: none;
            margin: 0.75rem 0 0;
            padding: 0;
            border: 1px solid var(--border);
            max-height: 320px;
            overflow: auto;
            display: none;
        }
        .results li button {
            width: 100%;
            text-align: left;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: #fff;
            padding: 0.75rem 0.85rem;
            cursor: pointer;
            font: inherit;
        }
        .results li:last-child button { border-bottom: 0; }
        .results li button:hover,
        .results li button[aria-selected="true"] {
            background: #f3f7fb;
        }
        .selected {
            margin-top: 1rem;
            padding: 0.85rem;
            background: #f7fafc;
            border: 1px dashed var(--border);
            display: none;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        button.primary, button.secondary, a.button {
            appearance: none;
            border: 1px solid transparent;
            padding: 0.65rem 1rem;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        button.primary {
            background: var(--accent);
            color: #fff;
        }
        button.primary:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        button.secondary, a.button {
            background: #fff;
            border-color: var(--border);
            color: var(--ink);
        }
        .flash {
            background: #e8f7ef;
            color: var(--ok);
            border: 1px solid #b7e0c8;
            padding: 0.75rem 0.9rem;
            margin-bottom: 1rem;
        }
        .error {
            background: #fdecec;
            color: #8a1f1f;
            border: 1px solid #efc2c2;
            padding: 0.75rem 0.9rem;
            margin-bottom: 1rem;
        }
        .meta { color: var(--muted); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Imitator</h1>
        <p class="lede">Search Entra and imitate a user in this local session. Original session state is restored when you stop.</p>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($status['active'])
            <div class="panel active">
                <h2>Currently imitating</h2>
                <p>
                    <strong>{{ $status['targetUser']['name'] ?? 'Unknown' }}</strong>
                    <span class="meta">{{ $status['targetUser']['email'] ?? '' }}</span>
                </p>
                <p class="meta">
                    Original: {{ $status['originalUser']['name'] ?? 'Unknown' }}
                    @if ($status['startedAt'])
                        · started {{ $status['startedAt'] }}
                    @endif
                </p>
                <form method="post" action="{{ url('/imitator') }}" class="actions">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="secondary">Stop imitation</button>
                    <a class="button" href="{{ url('/') }}">Open app home</a>
                </form>
            </div>
        @endif

        <div class="panel">
            <h2>{{ $status['active'] ? 'Switch user' : 'Start imitation' }}</h2>
            <label for="imitator-search">Search by name or userPrincipalName</label>
            <input id="imitator-search" type="search" placeholder="e.g. jdoe or Jane Doe" autocomplete="off">
            <ul id="imitator-results" class="results" role="listbox"></ul>

            <div id="imitator-selected" class="selected">
                <div class="meta">Selected</div>
                <div id="imitator-selected-label"></div>
            </div>

            <form id="imitator-start-form" method="post" action="{{ url('/imitator') }}">
                @csrf
                <input type="hidden" name="user[id]" id="user-id">
                <input type="hidden" name="user[name]" id="user-name">
                <input type="hidden" name="user[userPrincipalName]" id="user-upn">
                <input type="hidden" name="user[email]" id="user-email">
                <input type="hidden" name="user[job_title]" id="user-job-title">
                <div class="actions">
                    <button id="imitator-start" type="submit" class="primary" disabled>
                        {{ $status['active'] ? 'Switch imitation' : 'Start imitation' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const searchInput = document.getElementById('imitator-search');
            const results = document.getElementById('imitator-results');
            const selected = document.getElementById('imitator-selected');
            const selectedLabel = document.getElementById('imitator-selected-label');
            const startButton = document.getElementById('imitator-start');
            let timer = null;

            const fields = {
                id: document.getElementById('user-id'),
                name: document.getElementById('user-name'),
                upn: document.getElementById('user-upn'),
                email: document.getElementById('user-email'),
                jobTitle: document.getElementById('user-job-title'),
            };

            function selectUser(user) {
                fields.id.value = user.id || '';
                fields.name.value = user.displayName || '';
                fields.upn.value = user.userPrincipalName || '';
                fields.email.value = user.mail || user.userPrincipalName || '';
                fields.jobTitle.value = user.jobTitle || '';
                selectedLabel.textContent = `${user.displayName} · ${user.userPrincipalName || ''}`;
                selected.style.display = 'block';
                startButton.disabled = !fields.id.value;
                results.style.display = 'none';
            }

            function renderResults(users) {
                results.innerHTML = '';
                if (!users.length) {
                    results.style.display = 'none';
                    return;
                }

                users.forEach((user) => {
                    const li = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('role', 'option');
                    button.innerHTML = `<strong>${user.displayName || 'Unknown'}</strong><div class="meta">${user.userPrincipalName || ''}</div>`;
                    button.addEventListener('click', () => selectUser(user));
                    li.appendChild(button);
                    results.appendChild(li);
                });

                results.style.display = 'block';
            }

            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                const q = searchInput.value.trim();
                if (q.length < 2) {
                    results.style.display = 'none';
                    return;
                }

                timer = setTimeout(async () => {
                    const response = await fetch(`{{ url('/imitator/search') }}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    renderResults(data.value || []);
                }, 250);
            });
        })();
    </script>
</body>
</html>
