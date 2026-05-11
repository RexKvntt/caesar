<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role     = $_SESSION['role'];

// Only students can join classes
if ($role !== 'student') {
    header("Location: dashboard.php");
    exit();
}

$classesFile = __DIR__ . "/classes.json";
if (!file_exists($classesFile)) {
    file_put_contents($classesFile, json_encode(['classes' => []]));
}

$classesData = json_decode(file_get_contents($classesFile), true);
$classes     = &$classesData['classes'];

$error   = null;
$success = null;

/* ── Handle Join ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_code'])) {
    $inputCode = strtoupper(trim($_POST['join_code'] ?? ''));

    if (empty($inputCode)) {
        $error = 'Please enter a class code.';
    } elseif (!preg_match('/^[A-Z0-9]{6}$/', $inputCode)) {
        $error = 'Invalid code format. Codes are 6 characters (letters and numbers).';
    } else {
        $found = false;
        foreach ($classes as &$class) {
            if ($class['code'] === $inputCode) {
                $found = true;

                if (in_array($username, $class['members'] ?? [])) {
                    $error = 'You are already a member of <strong>' . htmlspecialchars($class['name']) . '</strong>.';
                } else {
                    $class['members'][] = $username;
                    file_put_contents($classesFile, json_encode($classesData, JSON_PRETTY_PRINT));
                    $success = 'You joined <strong>' . htmlspecialchars($class['name']) . '</strong> — ' . htmlspecialchars($class['subject']) . '.';
                }
                break;
            }
        }
        unset($class);

        if (!$found) {
            $error = 'No class found with that code. Double-check and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join a Class — Helios University</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%231e40af'/><text x='50%25' y='54%25' dominant-baseline='middle' text-anchor='middle' font-size='18' font-family='sans-serif' font-weight='bold' fill='white'>S</text></svg>">
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function(){
            var t = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        /* ── Page layout ── */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--bg);
            background-image:
                radial-gradient(ellipse 60% 50% at 50% 0%, rgba(30,64,175,0.08) 0%, transparent 65%),
                radial-gradient(ellipse 35% 40% at 85% 90%, rgba(59,130,246,0.05) 0%, transparent 55%);
        }

        /* ── Header ── */
        .page-header { backdrop-filter: blur(14px); }

        /* ── Main centered area ── */
        .join-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }

        .join-container {
            width: 100%;
            max-width: 480px;
            animation: fadeUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        /* ── Card ── */
        .join-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-2xl);
            padding: 52px 44px;
            box-shadow: var(--shadow-xl);
            position: relative;
        }

        /* ── Icon wrap ── */
        .icon-wrap {
            width: 68px; height: 68px;
            border-radius: 50%;
            background: var(--accent-light);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
        }
        .icon-wrap svg {
            width: 30px; height: 30px;
            stroke: var(--accent); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Heading ── */
        .join-title {
            font-family: 'Instrument Serif', serif;
            font-size: 36px;
            font-style: italic;
            font-weight: 400;
            color: var(--text-primary);
            text-align: center;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
            line-height: 1.15;
        }

        .join-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.65;
            margin-bottom: 36px;
        }

        /* ── Code input group ── */
        .code-group {
            margin-bottom: 20px;
        }

        .code-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .code-input-wrap {
            position: relative;
        }

        .code-input-wrap svg.icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px; height: 16px;
            stroke: var(--text-muted); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
            pointer-events: none;
            transition: stroke var(--transition);
        }

        .code-input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            font-family: 'Sora', sans-serif;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--text-primary);
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
        }

        .code-input::placeholder {
            color: var(--text-muted);
            font-weight: 400;
            letter-spacing: 0.1em;
            font-size: 16px;
        }

        .code-input:hover {
            border-color: #b8cee0;
            background: var(--surface);
        }
        [data-theme="dark"] .code-input:hover { border-color: #243d5a; }

        .code-input:focus {
            border-color: var(--border-focus);
            background: var(--surface);
            box-shadow: 0 0 0 3.5px rgba(30,64,175,0.11);
        }
        .code-input:focus + .icon-left,
        .code-input-wrap:focus-within svg.icon-left {
            stroke: var(--accent);
        }

        /* Character counter */
        .char-counter {
            display: flex;
            justify-content: flex-end;
            margin-top: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            transition: color var(--transition);
        }
        .char-counter.full { color: var(--success); }

        /* ── Hints ── */
        .hint-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 28px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.55;
        }
        .hint-box svg {
            width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px;
            stroke: var(--accent-text); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Alert overrides ── */
        .alert strong { font-weight: 700; }

        /* ── Submit button ── */
        .btn-join {
            width: 100%;
            padding: 14px 20px;
            font-family: 'Sora', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.02em;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            box-shadow: 0 3px 16px rgba(30,64,175,0.30);
            transition: background 0.2s, transform 0.18s, box-shadow 0.2s;
            margin-bottom: 0;
        }
        .btn-join:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(30,64,175,0.40);
        }
        .btn-join:active { transform: translateY(0); box-shadow: none; }
        .btn-join:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-join svg {
            width: 16px; height: 16px;
            stroke: #fff; fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Divider ── */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 28px 0 20px;
        }
        .divider-line { flex: 1; height: 1px; background: var(--border); }
        .divider-text {
            font-size: 11px; font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.08em;
        }

        /* ── Back link ── */
        .back-link {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 13.5px; font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color var(--transition);
        }
        .back-link:hover { color: var(--accent); }
        .back-link svg {
            width: 14px; height: 14px;
            stroke: currentColor; fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Success state ── */
        .success-anim {
            text-align: center;
            animation: fadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }
        .success-icon-wrap {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--success-light);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon-wrap svg {
            width: 32px; height: 32px;
            stroke: var(--success); fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
        }
        .success-title {
            font-family: 'Instrument Serif', serif;
            font-size: 32px; font-style: italic;
            color: var(--text-primary);
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }
        .success-msg {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.65;
            margin-bottom: 32px;
        }
        .btn-go-dashboard {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px;
            font-family: 'Sora', sans-serif; font-size: 13.5px; font-weight: 700;
            background: var(--accent); color: #fff;
            border: none; border-radius: var(--radius-md);
            cursor: pointer; text-decoration: none;
            box-shadow: 0 3px 16px rgba(30,64,175,0.30);
            transition: background 0.2s, transform 0.18s;
        }
        .btn-go-dashboard:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            color: #fff;
        }
        .btn-go-dashboard svg {
            width: 15px; height: 15px;
            stroke: #fff; fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Theme corner ── */
        .theme-corner {
            position: absolute;
            top: 20px; right: 20px;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="page-header">
    <a href="dashboard.php" class="page-header-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Dashboard
    </a>
    <span class="page-header-title">Join a Class</span>
    <div class="header-actions">
        <button class="theme-toggle" id="themeBtn" aria-label="Toggle theme">
            <svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>Logout</span>
        </a>
    </div>
</header>

<!-- Main -->
<div class="join-wrapper">
    <div class="join-container">
        <div class="join-card">

            <div class="theme-corner">
                <button class="theme-toggle" id="themeBtnCard" aria-label="Toggle theme">
                    <svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>

            <?php if ($success): ?>
            <!-- ── Success State ── -->
            <div class="success-anim">
                <div class="success-icon-wrap">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="success-title">You're in!</div>
                <p class="success-msg"><?= $success ?></p>
                <a href="dashboard.php" class="btn-go-dashboard">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Go to Dashboard
                </a>
            </div>

            <?php else: ?>
            <!-- ── Form State ── -->
            <div class="icon-wrap">
                <svg viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </div>

            <h1 class="join-title">Join a Class</h1>
            <p class="join-subtitle">Enter the class code shared by your faculty to join their classroom.</p>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:24px;">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;stroke-linecap:round;stroke-linejoin:round;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="joinForm">
                <div class="code-group">
                    <label for="codeInput">Class Code</label>
                    <div class="code-input-wrap">
                        <svg class="icon-left" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input
                            type="text"
                            class="code-input"
                            id="codeInput"
                            name="join_code"
                            maxlength="6"
                            placeholder="ABC123"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            value="<?= htmlspecialchars(strtoupper($_POST['join_code'] ?? '')) ?>"
                        >
                    </div>
                    <div class="char-counter" id="charCounter">
                        <span id="charCount">0</span>/6
                    </div>
                </div>

                <div class="hint-box">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>Ask your faculty member for the 6-character class code. It looks like <strong>A1B2C3</strong>.</span>
                </div>

                <button type="submit" class="btn-join" id="submitBtn" disabled>
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    Join Class
                </button>
            </form>

            <div class="divider">
                <span class="divider-line"></span>
                <span class="divider-text">or</span>
                <span class="divider-line"></span>
            </div>

            <a href="dashboard.php" class="back-link">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Dashboard
            </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="theme.js"></script>
<script>
/* ── Theme toggles ── */
document.querySelectorAll('.theme-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var cur  = document.documentElement.getAttribute('data-theme');
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
    });
});

/* ── Code input behavior ── */
var input       = document.getElementById('codeInput');
var counter     = document.getElementById('charCount');
var counterWrap = document.getElementById('charCounter');
var submitBtn   = document.getElementById('submitBtn');

if (input) {
    function updateInput() {
        // Force uppercase, strip non-alphanumeric
        var cleaned = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 6);
        input.value = cleaned;

        var len = cleaned.length;
        counter.textContent = len;

        if (len === 6) {
            counterWrap.classList.add('full');
            submitBtn.disabled = false;
        } else {
            counterWrap.classList.remove('full');
            submitBtn.disabled = true;
        }
    }

    input.addEventListener('input', updateInput);
    input.addEventListener('paste', function() {
        setTimeout(updateInput, 0);
    });

    // Run on load in case of browser-autofill or PHP repopulation
    updateInput();
}
</script>
</body>
</html>