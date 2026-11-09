<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DeenQ AI helps you build a steady, thoughtful Islamic practice with prayer, Quran learning, and daily reflection.">
    <meta name="theme-color" content="#f5f4ed">
    <title>DeenQ AI — Grow closer, one day at a time</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17352b;
            --muted: #64746c;
            --green: #17684d;
            --green-dark: #104c39;
            --mint: #e5eee7;
            --paper: #f5f4ed;
            --line: #e6e8df;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: min(1120px, calc(100% - 48px));
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 84px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-size: 19px;
            font-weight: 750;
            letter-spacing: -0.04em;
        }

        .brand-mark {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 13px;
            background: var(--green);
            color: white;
        }

        .brand-mark svg { width: 30px; height: 30px; }

        .nav {
            display: flex;
            align-items: center;
            gap: 30px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 550;
        }

        .nav a:hover, .footer a:hover { color: var(--green); }

        .nav .admin-link {
            padding: 10px 16px;
            border: 1px solid #d9ded5;
            border-radius: 999px;
            color: var(--ink);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.04fr .96fr;
            align-items: center;
            gap: 72px;
            min-height: 610px;
            padding: 62px 0 84px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 23px;
            color: var(--green);
            font-size: 12px;
            font-weight: 750;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #79a88d;
            content: "";
        }

        h1 {
            max-width: 580px;
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(48px, 6vw, 76px);
            font-weight: 500;
            letter-spacing: -.055em;
            line-height: 1.04;
        }

        h1 em {
            color: var(--green);
            font-weight: 500;
        }

        .intro {
            max-width: 490px;
            margin: 24px 0 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.8;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 13px;
            margin-top: 32px;
        }

        .button {
            display: inline-flex;
            min-height: 49px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0 20px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 650;
            transition: transform .18s ease, background .18s ease;
        }

        .button:hover { transform: translateY(-2px); }
        .button-primary { background: var(--green); color: white; }
        .button-primary:hover { background: var(--green-dark); }
        .button-secondary { border-color: #d9ded5; background: transparent; }
        .button svg { width: 17px; height: 17px; }

        .note {
            margin: 17px 0 0;
            color: #78847d;
            font-size: 12px;
        }

        .preview-wrap {
            position: relative;
            padding: 24px;
        }

        .preview-wrap::before {
            position: absolute;
            inset: 5% 0 0 8%;
            border-radius: 48% 52% 43% 57% / 48% 43% 57% 52%;
            background: #e8ebdd;
            content: "";
        }

        .preview {
            position: relative;
            overflow: hidden;
            padding: 25px;
            border: 1px solid rgba(255,255,255,.85);
            border-radius: 25px;
            background: rgba(255,255,255,.93);
            box-shadow: 0 26px 70px rgba(34, 57, 43, .12);
            transform: rotate(1.5deg);
        }

        .preview-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--muted);
            font-size: 12px;
        }

        .preview-title {
            margin-top: 24px;
            font-size: 22px;
            font-weight: 720;
            letter-spacing: -.04em;
        }

        .preview-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .streak {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 23px;
            padding: 15px;
            border-radius: 17px;
            background: #eef4ed;
        }

        .streak-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 14px;
            background: white;
            color: var(--green);
        }

        .streak-icon svg { width: 22px; height: 22px; }
        .streak strong { display: block; font-size: 13px; }
        .streak span { display: block; margin-top: 4px; color: var(--muted); font-size: 11px; }

        .prayer-heading {
            display: flex;
            justify-content: space-between;
            margin: 23px 0 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .prayer-heading span:last-child {
            color: var(--green);
            font-size: 11px;
            font-weight: 600;
        }

        .prayer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 2px;
            border-bottom: 1px solid #edf0e9;
            font-size: 12px;
        }

        .prayer-name { display: flex; align-items: center; gap: 9px; }
        .prayer-dot { width: 7px; height: 7px; border-radius: 50%; background: #86aa91; }
        .prayer-time { color: var(--muted); font-variant-numeric: tabular-nums; }

        .preview-bottom {
            display: flex;
            justify-content: space-between;
            padding-top: 19px;
            color: var(--muted);
            font-size: 10px;
        }

        .section {
            padding: 75px 0 88px;
            border-top: 1px solid var(--line);
        }

        .section-heading {
            max-width: 600px;
            margin: 0 auto 38px;
            text-align: center;
        }

        .section-heading h2 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(32px, 4vw, 45px);
            font-weight: 500;
            letter-spacing: -.045em;
        }

        .section-heading p {
            margin: 13px 0 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
        }

        .feature {
            min-height: 195px;
            padding: 23px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,.55);
        }

        .feature-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 13px;
            background: var(--mint);
            color: var(--green);
        }

        .feature-icon svg { width: 20px; height: 20px; }
        .feature h3 { margin: 18px 0 7px; font-size: 15px; }
        .feature p { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.65; }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 24px 0 30px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
        }

        .footer-links { display: flex; gap: 20px; }

        @media (max-width: 800px) {
            .hero { grid-template-columns: 1fr; gap: 26px; padding: 55px 0 65px; }
            .hero-copy { max-width: 650px; }
            .preview-wrap { width: min(510px, 100%); margin: 0 auto; }
        }

        @media (max-width: 560px) {
            .shell { width: min(100% - 32px, 1120px); }
            .topbar { min-height: 72px; }
            .nav { gap: 15px; font-size: 12px; }
            .nav a:first-child { display: none; }
            .nav .admin-link { padding: 9px 12px; }
            .hero { padding-top: 44px; }
            .intro { font-size: 15px; }
            .preview-wrap { padding: 12px 4px; }
            .preview { padding: 20px; }
            .feature-grid { grid-template-columns: 1fr; }
            .feature { min-height: auto; }
            .section { padding: 58px 0; }
            .footer { align-items: flex-start; flex-direction: column; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <header class="shell topbar">
        <a class="brand" href="/" aria-label="DeenQ AI home">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 512 512" fill="none"><g transform="translate(256.0 256.0) scale(1.0022) translate(-301.0 -247.0)"><path d="M 88 256 A 168 168 0 1 0 424 256 A 168 168 0 1 0 88 256 Z M 148 236 A 128 128 0 1 1 404 236 A 128 128 0 1 1 148 236 Z" fill="currentColor" fill-rule="evenodd"/><path d="M 326 388 C 360 440 410 472 466 470 C 489 469 503 458 512 438 C 499 452 483 459 463 459 C 420 459 388 432 358 392 Z" fill="currentColor"/><path d="M 270 318 C 250 302 222 298 200 304 L 200 246 C 222 240 250 244 270 260 Z M 282 318 C 302 302 330 298 352 304 L 352 246 C 330 240 302 244 282 260 Z" fill="#C6A45B"/><path d="M 223.4 18.9 L 247.3 37.0 L 236.6 65.1 L 212.7 47.0 Z M 275.4 12.9 L 299.3 31.0 L 288.6 59.1 L 264.7 41.0 Z" fill="#C6A45B"/></g></svg></span>
            <span>DeenQ AI</span>
        </a>
        <nav class="nav" aria-label="Main navigation">
            <a href="#features">What you can do</a>
            <a class="admin-link" href="{{ url('/admin/login') }}">Admin sign in</a>
        </nav>
    </header>

    <main>
        <section class="shell hero">
            <div class="hero-copy">
                <div class="eyebrow">A companion for your journey</div>
                <h1>Grow closer,<br><em>one day at a time.</em></h1>
                <p class="intro">Make space for what matters. Build thoughtful daily habits, keep up with prayer, and keep learning at your own pace.</p>
                <div class="actions">
                    <a class="button button-primary" href="#features">
                        Explore DeenQ AI
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a class="button button-secondary" href="{{ url('/api/v1/config') }}">Explore the API</a>
                </div>
                <p class="note">A gentle companion for learning, reflection, and steady progress.</p>
            </div>

            <div class="preview-wrap" aria-label="Illustration of the DeenQ AI daily progress screen">
                <div class="preview">
                    <div class="preview-top"><span>YOUR DAILY SPACE</span><span>Today</span></div>
                    <div class="preview-title">A little progress adds up.</div>
                    <div class="preview-subtitle">A moment to pause and reconnect.</div>
                    <div class="streak">
                        <span class="streak-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3c1.2 3.4-.8 4.7-.8 6.5 0 1.2.8 2 1.8 2 1.8 0 2.4-2 2.4-3.1 2.1 2 3.4 4.1 3.4 6.7a6.8 6.8 0 0 1-13.6 0c0-3.1 1.8-6.2 5.1-9.2-.1 2.6.1 3.6.8 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span><strong>Keep showing up</strong><span>Small, consistent steps matter.</span></span>
                    </div>
                    <div class="prayer-heading"><span>Make room for prayer</span><span>Your daily rhythm</span></div>
                    <div class="prayer-row"><span class="prayer-name"><span class="prayer-dot"></span>Fajr</span><span class="prayer-time">A new beginning</span></div>
                    <div class="prayer-row"><span class="prayer-name"><span class="prayer-dot"></span>Dhuhr</span><span class="prayer-time">A pause in your day</span></div>
                    <div class="prayer-row"><span class="prayer-name"><span class="prayer-dot"></span>Asr</span><span class="prayer-time">Return to what matters</span></div>
                    <div class="prayer-row"><span class="prayer-name"><span class="prayer-dot"></span>Maghrib</span><span class="prayer-time">End the day with gratitude</span></div>
                    <div class="preview-bottom"><span>Prayer • Learning • Reflection</span><span>DeenQ AI</span></div>
                </div>
            </div>
        </section>

        <section class="section" id="features">
            <div class="shell">
                <div class="section-heading">
                    <h2>Make your practice your own.</h2>
                    <p>Helpful tools for the everyday moments that shape a more mindful, meaningful routine.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature">
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18m0-18c-2.4 2-3.6 4-3.6 6.2A3.6 3.6 0 0 0 12 12.8m0-9.8c2.4 2 3.6 4 3.6 6.2a3.6 3.6 0 0 1-3.6 3.6M5 21h14M7.5 17h9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3>Prayer, at your pace</h3>
                        <p>Set up prayer times and a routine that fits your location and preferences.</p>
                    </article>
                    <article class="feature">
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H20v17H7.5A2.5 2.5 0 0 0 5 21.5v-17ZM5 18.5A2.5 2.5 0 0 1 7.5 16H20M9 7h7m-7 4h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3>Learn and reflect</h3>
                        <p>Explore Quran, Hadith, and lessons with space to learn one step at a time.</p>
                    </article>
                    <article class="feature">
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 20.2s-7.5-4.1-7.5-10A4.2 4.2 0 0 1 12 7.5a4.2 4.2 0 0 1 7.5 2.7c0 5.9-7.5 10-7.5 10Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.5 12h2l1.2-2.3 1.6 4.6 1.1-2.3h1.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3>Notice your progress</h3>
                        <p>Keep track of good habits and personal goals without losing sight of the journey.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="shell footer">
        <a class="brand" href="/" aria-label="DeenQ AI home">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 512 512" fill="none"><g transform="translate(256.0 256.0) scale(1.0022) translate(-301.0 -247.0)"><path d="M 88 256 A 168 168 0 1 0 424 256 A 168 168 0 1 0 88 256 Z M 148 236 A 128 128 0 1 1 404 236 A 128 128 0 1 1 148 236 Z" fill="currentColor" fill-rule="evenodd"/><path d="M 326 388 C 360 440 410 472 466 470 C 489 469 503 458 512 438 C 499 452 483 459 463 459 C 420 459 388 432 358 392 Z" fill="currentColor"/><path d="M 270 318 C 250 302 222 298 200 304 L 200 246 C 222 240 250 244 270 260 Z M 282 318 C 302 302 330 298 352 304 L 352 246 C 330 240 302 244 282 260 Z" fill="#C6A45B"/><path d="M 223.4 18.9 L 247.3 37.0 L 236.6 65.1 L 212.7 47.0 Z M 275.4 12.9 L 299.3 31.0 L 288.6 59.1 L 264.7 41.0 Z" fill="#C6A45B"/></g></svg></span>
            <span>DeenQ AI</span>
        </a>
        <span>A little progress, every day.</span>
        <div class="footer-links">
            <a href="#features">Features</a>
            <a href="{{ url('/admin/login') }}">Admin</a>
        </div>
    </footer>
</body>
</html>
