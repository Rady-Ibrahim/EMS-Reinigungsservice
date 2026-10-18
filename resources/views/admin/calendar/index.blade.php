@extends('admin.layouts.app')

@section('title', app()->getLocale() === 'ar' ? __('messages.cal.title') : 'Kalender')
@section('content')
    @php
        $activeLayers = old('layers', ['fix', 'extra', 'shifts', 'personal', 'internal']);
        $employeeMap   = \App\Models\User::pluck('name', 'id')->map(fn($n) => (string) $n)->all();
        $cal = __('messages.cal');
    @endphp

    {{-- Toolbar / filters --}}
    <div class="card" style="padding:1rem 1.25rem;">
        <div class="cal-toolbar">
            <div id="layer-filters" class="layer-filters">
                @foreach($layers as $layer)
                    <label class="layer-pill">
                        <input type="checkbox" class="layer-toggle" value="{{ $layer['key'] }}"
                               {{ in_array($layer['key'], $activeLayers, true) ? 'checked' : '' }}>
                        <span class="color-dot" style="background:{{ $layer['color'] }}"></span>
                        {{ $layer['label'] }}
                    </label>
                @endforeach
            </div>
            <div class="cal-actions">
                <div class="cal-viewtoggle">
                    <button type="button" class="vt-btn is-active" data-view="agenda">🗓 {{ $cal['day_agenda'] }}</button>
                    <button type="button" class="vt-btn" data-view="calendar">▦ {{ $cal['month_overview'] }}</button>
                </div>
                <button type="button" class="btn-gold-outline btn-sm js-add" data-add="ask">{{ $cal['new_appointment'] }}</button>
            </div>
        </div>
    </div>

    {{-- ═══ Agenda view: mini-month + day list (TeamUp style) ═══ --}}
    <div class="cal-split" id="cal-split">
        <div class="card mini-cal-card">
            <div class="mini-head">
                <button type="button" class="mc-nav" id="mini-prev" aria-label="prev">‹</button>
                <span id="mini-title">—</span>
                <button type="button" class="mc-nav" id="mini-next" aria-label="next">›</button>
            </div>
            <div class="mini-grid" id="mini-grid"></div>
            <button type="button" class="mc-today" id="mini-today">{{ $cal['today'] }}</button>
            <div class="mini-legend">
                @foreach($layers as $layer)
                    <span class="ml-item"><span class="color-dot" style="background:{{ $layer['color'] }}"></span>{{ $layer['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="card agenda-card">
            <div class="agenda-head">
                <div>
                    <div class="ag-kicker">{{ $cal['day_agenda'] }}</div>
                    <h3 id="agenda-date">—</h3>
                </div>
                <button type="button" class="ag-add js-add" data-add="ask">＋</button>
            </div>
            <div class="agenda-list" id="agenda-list"></div>
        </div>
    </div>

    {{-- ═══ Full month / week calendar view ═══ --}}
    <div class="card" id="full-cal-card" style="padding:1rem;display:none;">
        <div id="calendar" style="min-height:620px;"></div>
    </div>

    {{-- ═══ Detail slide-over panel ═══ --}}
    <div class="panel-backdrop" id="panel-backdrop"></div>
    <aside class="slide-panel" id="slide-panel" aria-hidden="true">
        <div class="sp-head">
            <div>
                <div class="sp-kicker">{{ $cal['detail'] }}</div>
                <h3 id="sp-title">—</h3>
            </div>
            <button type="button" class="sp-close" id="sp-close" aria-label="{{ $cal['close'] }}">✕</button>
        </div>
        <div class="sp-body" id="sp-body"></div>
    </aside>

    {{-- ═══ Create-type chooser ═══ --}}
    <div class="chooser-backdrop" id="chooser-backdrop"></div>
    <div class="chooser" id="chooser" aria-hidden="true">
        <div class="ch-head">
            <div>
                <div class="sp-kicker">{{ $cal['add_appointment'] }}</div>
                <h3>{{ $cal['choose_type'] }}</h3>
                <p class="ch-date" id="chooser-date"></p>
            </div>
            <button type="button" class="sp-close" id="chooser-close" aria-label="{{ $cal['close'] }}">✕</button>
        </div>
        <div class="ch-options">
            <button type="button" class="ch-option" data-create="personal">
                <span class="ch-ico">👤</span>
                <span class="ch-lbl">{{ $cal['new_personal'] }}</span>
            </button>
            <button type="button" class="ch-option" data-create="shift">
                <span class="ch-ico">🕒</span>
                <span class="ch-lbl">{{ $cal['new_shift'] }}</span>
            </button>
            <button type="button" class="ch-option" data-create="internal">
                <span class="ch-ico">🏢</span>
                <span class="ch-lbl">{{ $cal['new_internal'] }}</span>
            </button>
        </div>
    </div>

    <style>
        /* ── Toolbar ───────────────────────────────────────────── */
        .cal-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .layer-filters { display: flex; gap: .5rem; flex-wrap: wrap; }
        .layer-pill {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .4rem .85rem; border-radius: 999px; cursor: pointer;
            background: var(--panel-2); border: 1px solid var(--border);
            font-size: .8rem; color: var(--text-muted); user-select: none;
            transition: all .15s ease;
        }
        .layer-pill:has(input:checked) { border-color: var(--gold); color: var(--gold-light); background: rgba(212,175,55,.08); }
        .layer-pill input { display: none; }
        .color-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex: none; }
        .cal-actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .cal-viewtoggle { display: inline-flex; background: var(--panel-2); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .vt-btn { background: transparent; border: none; color: var(--text-muted); padding: .42rem .85rem; font-size: .78rem; font-weight: 600; cursor: pointer; }
        .vt-btn.is-active { background: linear-gradient(180deg, var(--gold-light), var(--gold)); color: #141008; }
        .btn-sm { padding: .42rem .8rem; font-size: .78rem; }

        /* ── Split layout ──────────────────────────────────────── */
        .cal-split { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; align-items: start; }
        @media (max-width: 900px) { .cal-split { grid-template-columns: 1fr; } }

        /* ── Mini month ────────────────────────────────────────── */
        .mini-cal-card { padding: 1rem; position: sticky; top: 1rem; }
        .mini-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .8rem; }
        .mini-head #mini-title { font-weight: 800; color: var(--gold-light); font-size: .95rem; text-transform: capitalize; }
        .mc-nav { width: 30px; height: 30px; border-radius: 8px; background: var(--panel-2); border: 1px solid var(--border); color: var(--text-muted); cursor: pointer; font-size: 1.1rem; line-height: 1; }
        .mc-nav:hover { color: var(--gold-light); border-color: var(--gold); }
        .mini-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
        .mc-wd { text-align: center; font-size: .62rem; color: var(--text-faint); text-transform: uppercase; padding: .3rem 0; letter-spacing: .04em; }
        .mc-cell {
            position: relative; aspect-ratio: 1; border: 1px solid transparent; border-radius: 9px;
            background: transparent; color: var(--text-faint); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 600;
            transition: all .12s ease; padding: 0;
        }
        .mc-cell:hover { background: rgba(212,175,55,.08); }
        .mc-cell.mc-empty { cursor: default; }
        .mc-cell.has-events { color: var(--text); background: rgba(212,175,55,.05); }
        .mc-cell.is-today { border-color: rgba(212,175,55,.5); color: var(--gold-bright); }
        .mc-cell.is-selected { background: linear-gradient(180deg, var(--gold-light), var(--gold)); color: #141008; box-shadow: 0 4px 14px rgba(212,175,55,.35); }
        .mc-cell.is-selected .mc-dots { display: none; }
        .mc-num { position: relative; z-index: 1; }
        .mc-dots { position: absolute; bottom: 4px; left: 0; right: 0; display: flex; gap: 2px; justify-content: center; }
        .mc-dots i { width: 4px; height: 4px; border-radius: 50%; display: block; }
        .mc-today { width: 100%; margin-top: .8rem; padding: .5rem; border-radius: 9px; background: var(--panel-2); border: 1px solid var(--border); color: var(--text-muted); font-weight: 600; font-size: .78rem; cursor: pointer; }
        .mc-today:hover { color: var(--gold-light); border-color: var(--gold); }
        .mini-legend { display: flex; flex-direction: column; gap: .35rem; margin-top: 1rem; padding-top: .8rem; border-top: 1px solid var(--border); }
        .ml-item { display: flex; align-items: center; gap: .5rem; font-size: .74rem; color: var(--text-faint); }

        /* ── Day agenda ────────────────────────────────────────── */
        .agenda-card { padding: 1rem 1.2rem; min-height: 520px; }
        .agenda-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding-bottom: .9rem; border-bottom: 1px solid var(--border); }
        .ag-kicker { font-size: .62rem; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-light); font-weight: 700; }
        .agenda-head h3 { font-size: 1.15rem; font-weight: 800; color: var(--text); margin-top: .2rem; text-transform: capitalize; }
        .ag-add { width: 42px; height: 42px; border-radius: 12px; border: none; cursor: pointer; font-size: 1.3rem; font-weight: 800; color: #141008; background: linear-gradient(180deg, var(--gold-light), var(--gold)); box-shadow: 0 8px 22px rgba(212,175,55,.3); flex: none; }
        .ag-add:hover { filter: brightness(1.08); }
        .agenda-list { display: flex; flex-direction: column; gap: .6rem; }
        .agenda-item {
            display: flex; gap: .8rem; align-items: center;
            background: rgba(10,11,13,.55); border: 1px solid var(--border);
            border-inline-start: 4px solid var(--ev, var(--gold));
            border-radius: 12px; padding: .7rem .85rem; cursor: pointer;
            transition: all .14s ease;
        }
        .agenda-item:hover { border-color: var(--gold); background: rgba(212,175,55,.06); transform: translateX(2px); }
        html[dir="rtl"] .agenda-item:hover { transform: translateX(-2px); }
        .agenda-item.is-conflict { box-shadow: 0 0 0 1px #ef4444 inset; }
        .ai-time { font-size: .74rem; font-weight: 800; color: var(--gold-light); min-width: 92px; font-variant-numeric: tabular-nums; }
        .ai-main { flex: 1; min-width: 0; }
        .ai-title { font-weight: 700; color: var(--text); font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ai-meta { display: flex; gap: .7rem; flex-wrap: wrap; margin-top: .25rem; font-size: .72rem; color: var(--text-faint); }
        .ai-layer { color: var(--text-muted); }
        .ai-edit { flex: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 9px; border: 1px solid var(--border); color: var(--text-muted); text-decoration: none; font-size: .85rem; }
        .ai-edit:hover { color: var(--gold-light); border-color: var(--gold); }
        .agenda-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6rem;
            width: 100%; min-height: 320px; background: transparent; border: 1.5px dashed var(--border);
            border-radius: 14px; color: var(--text-faint); cursor: pointer; font-size: .85rem; padding: 2rem 1rem; text-align: center;
        }
        .agenda-empty:hover { border-color: rgba(212,175,55,.5); color: var(--gold-light); }
        .agenda-empty span { font-size: 2rem; color: var(--gold-deep); }
        .agenda-empty p { max-width: 260px; line-height: 1.5; }

        /* ── FullCalendar dark theme ───────────────────────────── */
        .fc { --fc-border-color: #2A2E39; --fc-page-bg-color: transparent; --fc-neutral-bg-color: #15171E; --fc-today-bg-color: rgba(212,175,55,.10); color: var(--text); }
        .fc .fc-toolbar-title { color: var(--gold-light); font-weight: 800; font-size: 1.15rem; }
        .fc .fc-button { background: var(--panel-2); border: 1px solid var(--border); color: var(--text-muted); font-weight: 600; border-radius: 9px; text-transform: capitalize; }
        .fc .fc-button:hover { background: #232732; border-color: rgba(212,175,55,.45); color: var(--gold-light); }
        .fc .fc-button-primary:not(:disabled).fc-button-active, .fc .fc-button-primary:not(:disabled):active {
            background: linear-gradient(180deg, var(--gold-light), var(--gold)); color: #141008; border-color: var(--gold);
            box-shadow: 0 0 16px rgba(212,175,55,.35);
        }
        .fc .fc-button-primary:disabled { opacity: .55; }
        .fc .fc-col-header-cell { background: #15171E; }
        .fc .fc-col-header-cell-cushion { color: var(--text-muted); font-size: .74rem; letter-spacing: .04em; text-transform: uppercase; padding: .6rem .4rem; }
        .fc .fc-scrollgrid, .fc .fc-scrollgrid-section > td { border-color: var(--border); }
        .fc .fc-timegrid-slot, .fc .fc-daygrid-day { background: transparent; }
        .fc .fc-daygrid-day.fc-day-today, .fc .fc-timegrid-col.fc-day-today { background: rgba(212,175,55,.07); }
        .fc .fc-daygrid-day-number, .fc .fc-timegrid-axis, .fc .fc-list-day-side-label { color: var(--text-muted); }
        .fc .fc-daygrid-day-top .fc-daygrid-day-number { color: var(--text); font-weight: 700; }
        .fc .fc-day-today .fc-daygrid-day-number { color: var(--gold-bright); }
        .fc .fc-event {
            border: none; border-radius: 8px;
            box-shadow: 0 3px 12px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.18);
            cursor: pointer; font-weight: 600; padding: 2px 4px;
        }
        .fc .fc-event:hover { filter: brightness(1.15); box-shadow: 0 6px 18px rgba(0,0,0,.55); }
        .fc .fc-list-event:hover td { background: rgba(212,175,55,.06); }
        .fc .fc-list-day-cushion { background: #15171E; }
        .fc .fc-list-day-cushion .fc-list-day-text, .fc .fc-list-day-cushion .fc-list-day-side-text { color: var(--text-muted); }
        .fc .fc-list-event-dot { border-color: transparent; }
        .fc .fc-popover { background: var(--panel-2); border: 1px solid var(--border-gold); border-radius: 12px; color: var(--text); }
        .fc .fc-more-link { color: var(--gold-light); }

        /* ── Slide-over panel ───────────────────────────────────── */
        .panel-backdrop {
            position: fixed; inset: 0; background: rgba(4,5,7,.6); backdrop-filter: blur(2px);
            display: none; z-index: 80;
        }
        .panel-backdrop.open { display: block; }
        .slide-panel {
            position: fixed; top: 0; inset-inline-end: 0; width: min(440px, 92vw); height: 100%;
            background: var(--panel); border-inline-start: 1px solid var(--border-gold);
            transform: translateX(105%); transition: transform .28s ease; z-index: 90;
            display: flex; flex-direction: column;
        }
        html[dir="rtl"] .slide-panel { transform: translateX(-105%); }
        .slide-panel.open { transform: translateX(0); }
        .sp-head { padding: 1.2rem 1.3rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; }
        .sp-kicker { font-size: .62rem; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-light); font-weight: 700; }
        .sp-head h3 { font-size: 1.2rem; font-weight: 800; color: var(--text); margin-top: .2rem; }
        .sp-close { width: 34px; height: 34px; border-radius: 9px; background: var(--panel-2); border: 1px solid var(--border); color: var(--text-muted); cursor: pointer; font-size: .85rem; flex: none; }
        .sp-close:hover { color: var(--gold-light); border-color: var(--gold); }
        .sp-body { flex: 1; overflow-y: auto; padding: 1.2rem 1.3rem 2rem; }
        .sp-section { margin-bottom: 1.4rem; }
        .sp-section-title { font-size: .64rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-light); margin-bottom: .6rem; display: flex; align-items: center; gap: .5rem; }
        .sp-section-title::before { content: ""; width: 14px; height: 1px; background: linear-gradient(90deg, var(--gold), transparent); }
        .sp-row { display: flex; gap: .7rem; align-items: flex-start; padding: .34rem 0; font-size: .86rem; }
        .sp-row .sp-ico { width: 18px; text-align: center; opacity: .8; }
        .sp-row .sp-k { color: var(--text-faint); font-size: .78rem; min-width: 104px; }
        .sp-row .sp-v { color: var(--text); font-weight: 600; flex: 1; }
        .sp-duo { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; margin-top: .4rem; }
        .sp-duo .sp-tile { background: rgba(10,11,13,.6); border: 1px solid var(--border); border-radius: 11px; padding: .7rem .8rem; }
        .sp-tile .t-lbl { font-size: .64rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: .08em; }
        .sp-tile .t-val { font-size: 1.05rem; font-weight: 800; color: var(--gold-light); margin-top: .2rem; font-variant-numeric: tabular-nums; }
        .btn-nav-gold {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%; margin-top: .75rem; padding: .65rem;
            background: linear-gradient(180deg, var(--gold-light), var(--gold)); color: #141008;
            border: none; border-radius: 11px; font-weight: 800; font-size: .85rem; text-decoration: none;
            box-shadow: 0 8px 22px rgba(212,175,55,.3); cursor: pointer;
        }
        .btn-nav-gold:hover { filter: brightness(1.08); }
        .check-list { display: flex; flex-direction: column; gap: .42rem; }
        .check-item { display: flex; align-items: center; gap: .6rem; background: rgba(10,11,13,.55); border: 1px solid var(--border); border-radius: 10px; padding: .55rem .7rem; font-size: .82rem; color: var(--text); cursor: pointer; transition: all .15s ease; }
        .check-item:hover { border-color: rgba(212,175,55,.4); }
        .check-item input { accent-color: var(--gold); width: 15px; height: 15px; }
        .check-item.done { opacity: .6; }
        .check-item.done .ci-label { text-decoration: line-through; }
        .photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .6rem; }
        .photo-tile { position: relative; aspect-ratio: 4/3; border-radius: 11px; overflow: hidden; background: linear-gradient(145deg, #20242D, #13151B); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--gold-deep); font-size: 1.7rem; }
        .photo-tile .pt-tag { position: absolute; top: 6px; inset-inline-start: 6px; background: rgba(4,5,7,.75); color: var(--gold-light); border: 1px solid rgba(212,175,55,.4); font-size: .6rem; font-weight: 700; letter-spacing: .08em; padding: .1rem .4rem; border-radius: 999px; }
        .photo-tile .pt-sub { position: absolute; bottom: 6px; inset-inline-end: 6px; font-size: .58rem; color: var(--text-faint); }
        .gps-row { display: flex; align-items: center; gap: .6rem; padding: .45rem .2rem; font-size: .8rem; border-bottom: 1px dashed rgba(42,46,57,.55); }
        .gps-row:last-child { border-bottom: none; }
        .gps-ico { width: 16px; text-align: center; }
        .gps-t { color: var(--text-faint); font-variant-numeric: tabular-nums; }
        .gps-v { color: var(--text-muted); flex: 1; }
        .gps-ok { color: var(--green); font-weight: 700; font-size: .74rem; }
        .detail-link { display: block; text-align: center; margin-top: .8rem; color: var(--gold-light); font-weight: 700; font-size: .84rem; text-decoration: none; }
        .detail-link:hover { text-decoration: underline; }

        /* ── Create chooser ────────────────────────────────────── */
        .chooser-backdrop { position: fixed; inset: 0; background: rgba(4,5,7,.6); backdrop-filter: blur(2px); display: none; z-index: 95; }
        .chooser-backdrop.open { display: block; }
        .chooser {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -46%) scale(.96);
            width: min(440px, 92vw); background: var(--panel); border: 1px solid var(--border-gold);
            border-radius: 16px; z-index: 96; padding: 1.3rem; opacity: 0; pointer-events: none;
            transition: all .2s ease; box-shadow: 0 24px 70px rgba(0,0,0,.6);
        }
        .chooser.open { opacity: 1; pointer-events: auto; transform: translate(-50%, -50%) scale(1); }
        .ch-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.1rem; }
        .ch-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-top: .2rem; }
        .ch-date { font-size: .8rem; color: var(--gold-light); margin-top: .3rem; font-weight: 600; text-transform: capitalize; }
        .ch-options { display: flex; flex-direction: column; gap: .6rem; }
        .ch-option { display: flex; align-items: center; gap: .8rem; padding: .85rem 1rem; border-radius: 12px; background: rgba(10,11,13,.55); border: 1px solid var(--border); color: var(--text); cursor: pointer; font-weight: 700; font-size: .9rem; transition: all .14s ease; text-align: start; }
        .ch-option:hover { border-color: var(--gold); background: rgba(212,175,55,.08); color: var(--gold-light); }
        .ch-ico { font-size: 1.2rem; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    @if(app()->getLocale() === 'ar')
        <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/ar.global.min.js"></script>
    @elseif(app()->getLocale() === 'de')
        <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/de.global.min.js"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locale = '{{ app()->getLocale() }}';
            const intlLocale = locale === 'ar' ? 'ar-EG' : (locale === 'de' ? 'de-DE' : 'en-GB');
            const employeeMap = {!! json_encode($employeeMap) !!};
            const layerLabels = {!! json_encode(collect($layers)->pluck('label', 'key')) !!};
            const layerColors = {!! json_encode(collect($layers)->pluck('color', 'key')) !!};
            const T = {!! json_encode($cal) !!};
            const FEED_URL = '{{ route('admin.calendar.events') }}';
            const CREATE_URL = {
                personal: '{{ route('admin.calendar.appointments.create') }}',
                shift: '{{ route('admin.calendar.shifts.create') }}',
                internal: '{{ route('admin.calendar.internal-events.create') }}',
            };

            // ── Small helpers ─────────────────────────────────────
            const pad2 = n => String(n).padStart(2, '0');
            const ymd = d => d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
            const activeLayers = () => [...document.querySelectorAll('.layer-toggle:checked')].map(c => c.value);

            function parseDate(iso, allDay) {
                if (allDay || /^\d{4}-\d{2}-\d{2}$/.test(String(iso).slice(0, 10)) && String(iso).length <= 10) {
                    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
                    return new Date(y, m - 1, d);
                }
                return new Date(iso);
            }

            // ── State ─────────────────────────────────────────────
            let viewDate = new Date();
            viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            let selected = new Date();
            let monthEvents = [];
            let calendar = null;

            // ── Data fetch ────────────────────────────────────────
            function fetchMonth() {
                const start = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
                const end = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
                const params = new URLSearchParams({ from: ymd(start), to: ymd(end), layers: activeLayers().join(',') });
                return fetch(FEED_URL + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then(data => { monthEvents = Array.isArray(data) ? data : (data.events || []); })
                    .catch(err => { console.error(err); monthEvents = []; });
            }

            function startOf(event) {
                return /^\d{4}-\d{2}-\d{2}$/.test(event.start) ? parseDate(event.start, true) : new Date(event.start);
            }
            function endOf(event, exclusive) {
                const d = /^\d{4}-\d{2}-\d{2}$/.test(event.end) ? parseDate(event.end, true) : new Date(event.end);
                return d;
            }

            function eventsOnDay(date) {
                const dayStart = new Date(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0, 0);
                const dayEnd = new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999);
                return monthEvents.filter(ev => {
                    const s = startOf(ev);
                    const e = endOf(ev);
                    if (ev.all_day) { return s <= dayEnd && e > dayStart; }
                    return s <= dayEnd && e >= dayStart;
                }).sort((a, b) => startOf(a) - startOf(b));
            }

            // ── Mini month ────────────────────────────────────────
            const miniGrid = document.getElementById('mini-grid');
            const miniTitle = document.getElementById('mini-title');

            function renderMini() {
                miniGrid.innerHTML = '';
                miniTitle.textContent = new Intl.DateTimeFormat(intlLocale, { month: 'long', year: 'numeric' }).format(viewDate);

                const wdFmt = new Intl.DateTimeFormat(intlLocale, { weekday: 'narrow' });
                const monday = new Date(2024, 0, 1);
                for (let i = 0; i < 7; i++) {
                    const h = document.createElement('div');
                    h.className = 'mc-wd';
                    h.textContent = wdFmt.format(new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i));
                    miniGrid.appendChild(h);
                }

                const year = viewDate.getFullYear();
                const month = viewDate.getMonth();
                const first = new Date(year, month, 1);
                const offset = (first.getDay() + 6) % 7;
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const today = new Date();

                for (let i = 0; i < offset; i++) {
                    const empty = document.createElement('div');
                    empty.className = 'mc-cell mc-empty';
                    miniGrid.appendChild(empty);
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const date = new Date(year, month, d);
                    const evs = eventsOnDay(date);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'mc-cell';
                    if (evs.length) btn.classList.add('has-events');
                    if (sameDay(date, today)) btn.classList.add('is-today');
                    if (sameDay(date, selected)) btn.classList.add('is-selected');

                    const num = document.createElement('span');
                    num.className = 'mc-num';
                    num.textContent = d;
                    btn.appendChild(num);

                    if (evs.length) {
                        const dots = document.createElement('span');
                        dots.className = 'mc-dots';
                        [...new Set(evs.map(e => e.color || layerColors[e.layer] || '#D4AF37'))].slice(0, 5).forEach(c => {
                            const dot = document.createElement('i');
                            dot.style.background = c;
                            dots.appendChild(dot);
                        });
                        btn.appendChild(dots);
                    }

                    btn.addEventListener('click', () => {
                        selected = date;
                        if (!evs.length) { openChooser(date); }
                        renderMini();
                        renderAgenda();
                    });

                    miniGrid.appendChild(btn);
                }
            }

            // ── Day agenda ────────────────────────────────────────
            const agendaList = document.getElementById('agenda-list');
            const agendaDate = document.getElementById('agenda-date');

            const timeFmt = new Intl.DateTimeFormat(intlLocale, { hour: '2-digit', minute: '2-digit' });
            const fullDateFmt = new Intl.DateTimeFormat(intlLocale, { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });

            function renderAgenda() {
                agendaDate.textContent = fullDateFmt.format(selected);
                agendaList.innerHTML = '';
                const evs = eventsOnDay(selected);

                if (!evs.length) {
                    const empty = document.createElement('button');
                    empty.type = 'button';
                    empty.className = 'agenda-empty';
                    empty.innerHTML = '<span>＋</span><p>' + escapeHtml(T.no_events_day) + '</p>';
                    empty.addEventListener('click', () => openChooser(selected));
                    agendaList.appendChild(empty);
                    return;
                }

                evs.forEach(ev => {
                    const color = ev.color || layerColors[ev.layer] || '#D4AF37';
                    const card = document.createElement('div');
                    card.className = 'agenda-item' + (ev.has_conflict ? ' is-conflict' : '');
                    card.style.setProperty('--ev', color);

                    const time = ev.all_day ? T.all_day : timeFmt.format(startOf(ev)) + ' – ' + timeFmt.format(endOf(ev));
                    const layer = layerLabels[ev.layer] || ev.type_label || ev.layer;
                    const emp = (ev.employee_ids || []).map(id => employeeMap[id]).filter(Boolean).join(', ');

                    card.innerHTML =
                        '<div class="ai-time">' + escapeHtml(time) + '</div>' +
                        '<div class="ai-main">' +
                            '<div class="ai-title">' + escapeHtml(ev.title || '') + '</div>' +
                            '<div class="ai-meta">' +
                                '<span class="ai-layer">' + escapeHtml(layer) + '</span>' +
                                (ev.location ? '<span>📍 ' + escapeHtml(ev.location) + '</span>' : '') +
                                (emp ? '<span>👤 ' + escapeHtml(emp) + '</span>' : '') +
                                (ev.has_conflict ? '<span style="color:#ef4444">⚠ ' + escapeHtml(T.conflict) + '</span>' : '') +
                            '</div>' +
                        '</div>' +
                        (ev.detail_url ? '<a class="ai-edit" href="' + ev.detail_url + '" title="' + escapeHtml(T.edit) + '">✎</a>' : '');

                    card.addEventListener('click', e => {
                        if (e.target.closest('.ai-edit')) return;
                        openEvent(ev);
                    });

                    agendaList.appendChild(card);
                });
            }

            // ── Create chooser ────────────────────────────────────
            const chooser = document.getElementById('chooser');
            const chooserBackdrop = document.getElementById('chooser-backdrop');
            const chooserDate = document.getElementById('chooser-date');
            let chooserDay = new Date();

            function openChooser(date) {
                chooserDay = date;
                chooserDate.textContent = fullDateFmt.format(date);
                chooser.classList.add('open');
                chooserBackdrop.classList.add('open');
                chooser.setAttribute('aria-hidden', 'false');
            }
            function closeChooser() {
                chooser.classList.remove('open');
                chooserBackdrop.classList.remove('open');
                chooser.setAttribute('aria-hidden', 'true');
            }
            document.getElementById('chooser-close').addEventListener('click', closeChooser);
            chooserBackdrop.addEventListener('click', closeChooser);
            chooser.querySelectorAll('.ch-option').forEach(btn => {
                btn.addEventListener('click', () => {
                    const kind = btn.dataset.create;
                    const url = CREATE_URL[kind];
                    if (url) { window.location.href = url + '?date=' + ymd(chooserDay); }
                });
            });
            document.querySelectorAll('.js-add').forEach(btn => {
                btn.addEventListener('click', () => openChooser(selected));
            });

            // ── Slide-over panel ──────────────────────────────────
            const panel = document.getElementById('slide-panel');
            const backdrop = document.getElementById('panel-backdrop');

            function openEvent(ev) {
                openPanel({
                    title: ev.title,
                    start: startOf(ev),
                    end: endOf(ev),
                    allDay: ev.all_day,
                    extendedProps: ev,
                });
            }

            window.openPanel = function (ev) {
                document.getElementById('sp-title').textContent = ev.title || T.appointment;
                document.getElementById('sp-body').innerHTML = panelContent(ev);
                panel.classList.add('open');
                backdrop.classList.add('open');
                panel.setAttribute('aria-hidden', 'false');
                panel.querySelectorAll('.check-item input').forEach(cb => {
                    cb.addEventListener('change', () => cb.closest('.check-item').classList.toggle('done', cb.checked));
                });
            };
            window.closePanel = function () {
                panel.classList.remove('open');
                backdrop.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
            };
            document.getElementById('sp-close').addEventListener('click', closePanel);
            backdrop.addEventListener('click', closePanel);
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { closePanel(); closeChooser(); } });

            function panelContent(ev) {
                const m = ev.extendedProps || {};
                const meta = m.metadata || {};
                const layerKey = m.layer || ev.type || '';
                const layer = (layerLabels[layerKey] || ev.type_label || layerKey);
                const allDay = ev.allDay || m.all_day;
                const intlLocaleD = locale === 'ar' ? 'ar-EG' : 'de-DE';

                const timeFmt2 = new Intl.DateTimeFormat(intlLocaleD, { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                const dateFmt = new Intl.DateTimeFormat(intlLocaleD, { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
                const timeOnly = new Intl.DateTimeFormat(intlLocaleD, { hour: '2-digit', minute: '2-digit' });

                const startTxt = allDay ? dateFmt.format(new Date(ev.start)) : timeFmt2.format(new Date(ev.start));
                const endTxt = allDay ? T.all_day : timeFmt2.format(new Date(ev.end));

                const ids = Array.isArray(m.employee_ids) ? m.employee_ids : [];
                const names = ids.map(id => employeeMap[id] || T.employee_fallback + ' #' + id).filter(Boolean);
                const empList = names.length ? names.join(', ') : (meta.employee_name || meta.owner_name || meta.creator_name || '—');

                const address = m.location || meta.location_name || (meta.customer_name ? meta.customer_name : null) || ev.title || '';
                const navHref = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(address);

                const hours = allDay ? '—' : workingHours(new Date(ev.start), new Date(ev.end));
                const contract = meta.contract_hours ? parseFloat(meta.contract_hours).toFixed(2).replace('.', ',') : null;
                const estimated = meta.estimated_hours ? parseFloat(meta.estimated_hours).toFixed(2).replace('.', ',') : null;

                const done = /abgeschlossen|completed|erledigt/.test((m.status_label || '').toLowerCase());
                const checklist = T.checklist_items;
                const docs = Array.isArray(meta.documents) ? meta.documents : [];

                const statusBadge = m.status_label
                    ? '<span class="badge ' + (done ? 'badge-green' : 'badge-blue') + '" style="font-size:.74rem">' + escapeHtml(m.status_label) + '</span>'
                    : '';

                return `
                    <div class="sp-section">
                        <div class="sp-section-title">${T.details}</div>
                        <div class="sp-row"><span class="sp-ico">🏷️</span><span class="sp-k">${T.type}</span><span class="sp-v">${escapeHtml(m.type_label || layer)} ${statusBadge}</span></div>
                        ${meta.customer_name ? '<div class="sp-row"><span class="sp-ico">🏢</span><span class="sp-k">' + T.customer + '</span><span class="sp-v">' + escapeHtml(meta.customer_name) + '</span></div>' : ''}
                        <div class="sp-row"><span class="sp-ico">📍</span><span class="sp-k">${T.location}</span><span class="sp-v">${escapeHtml(m.location || '—')}</span></div>
                        <div class="sp-row"><span class="sp-ico">👤</span><span class="sp-k">${T.staff}</span><span class="sp-v">${escapeHtml(empList)}</span></div>
                        <div class="sp-duo">
                            <div class="sp-tile"><div class="t-lbl">${T.start}</div><div class="t-val">${escapeHtml(startTxt)}</div></div>
                            <div class="sp-tile"><div class="t-lbl">${T.end}</div><div class="t-val">${escapeHtml(endTxt)}</div></div>
                        </div>
                    </div>

                    <div class="sp-section">
                        <a class="btn-nav-gold" href="${navHref}" target="_blank" rel="noopener">🧭 ${T.nav}</a>
                    </div>

                    <div class="sp-section">
                        <div class="sp-section-title">${T.checklist}</div>
                        <div class="check-list">
                            ${checklist.map((c) => `
                                <label class="check-item ${done ? 'done' : ''}">
                                    <input type="checkbox" ${done ? 'checked' : ''}>
                                    <span class="ci-label">${c}</span>
                                </label>`).join('')}
                        </div>
                    </div>

                    <div class="sp-section">
                        <div class="sp-section-title">${T.photos}</div>
                        <div class="photo-grid">
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.before}</span><span class="pt-sub">${T.photo}</span></div>
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.after}</span><span class="pt-sub">${T.photo}</span></div>
                        </div>
                    </div>

                    <div class="sp-section">
                        <div class="sp-section-title">${T.work_time_gps}</div>
                        <div class="sp-duo">
                            <div class="sp-tile"><div class="t-lbl">${T.work_time}</div><div class="t-val">${escapeHtml(hours)}</div></div>
                            <div class="sp-tile"><div class="t-lbl">${T.contract_plan}</div><div class="t-val">${escapeHtml(contract ? contract + ' h' : (estimated ? estimated + ' h' : '—'))}</div></div>
                        </div>
                        <div style="margin-top:.8rem;background:rgba(10,11,13,.55);border:1px solid var(--border);border-radius:12px;padding:.4rem .8rem;">
                            <div class="gps-row"><span class="gps-ico">🛰️</span><span class="gps-t">07:00</span><span class="gps-v">${T.check_in}</span><span class="gps-ok">✓ GPS</span></div>
                            <div class="gps-row"><span class="gps-ico">🕒</span><span class="gps-t">live</span><span class="gps-v">${T.clock_update}</span><span class="gps-ok">✓</span></div>
                            <div class="gps-row"><span class="gps-ico">⏱️</span><span class="gps-t">${allDay ? '—' : escapeHtml(timeOnly.format(new Date(ev.end)))}</span><span class="gps-v">${T.check_out}</span><span class="gps-ok">✓</span></div>
                        </div>
                    </div>

                    ${docs.length ? `
                    <div class="sp-section">
                        <div class="sp-section-title">${T.documents}</div>
                        ${docs.map(d => `
                            <div class="sp-row" style="align-items:center">
                                <span class="sp-ico">📄</span>
                                <span class="sp-k">${escapeHtml(d.title || '—')}</span>
                                <span class="sp-v">
                                    ${d.notes ? '<small style="color:var(--text-faint);display:block">' + escapeHtml(d.notes) + '</small>' : ''}
                                    ${d.url ? '<a class="detail-link" style="margin:0" href="' + d.url + '">⬇ ' + escapeHtml(T.download) + '</a>' : ''}
                                </span>
                            </div>`).join('')}
                    </div>` : ''}

                    ${m.detail_url ? '<a class="detail-link" href="' + m.detail_url + '">✎ ' + escapeHtml(T.edit) + '</a>' : ''}
                `;
            }

            function workingHours(start, end) {
                const mins = Math.max(0, Math.round((end - start) / 60000));
                const h = Math.floor(mins / 60);
                const m = mins % 60;
                return (h ? h + 'h ' : '') + m + 'm';
            }

            function escapeHtml(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            // ── FullCalendar (month/week view) ────────────────────
            let calendarBuilt = false;
            function buildCalendar() {
                if (calendarBuilt) return;
                calendarBuilt = true;
                const calendarEl = document.getElementById('calendar');
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: locale,
                    direction: locale === 'ar' ? 'rtl' : 'ltr',
                    firstDay: 1,
                    height: 'auto',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                    buttonText: { today: T.today, dayGridMonth: T.month, timeGridWeek: T.week, timeGridDay: T.day, listWeek: T.agenda },
                    dateClick(info) { openChooser(info.date); },
                    events(fetchInfo, successCallback, failureCallback) {
                        const params = new URLSearchParams({
                            from: fetchInfo.start.toISOString(),
                            to: fetchInfo.end.toISOString(),
                            layers: activeLayers().join(','),
                        });
                        fetch(FEED_URL + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                            .then(data => successCallback(Array.isArray(data) ? data : (data.events || [])))
                            .catch(err => { console.error(err); failureCallback(err); });
                    },
                    eventClick(info) {
                        info.jsEvent.preventDefault();
                        const p = info.event.toPlainObject();
                        p.extendedProps = Object.assign({}, p.extendedProps, info.event.toPlainObject().extendedProps);
                        openPanel(p);
                    }
                });
                calendar.render();
            }

            // ── View toggle ───────────────────────────────────────
            const split = document.getElementById('cal-split');
            const fullCard = document.getElementById('full-cal-card');
            document.querySelectorAll('.vt-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const view = btn.dataset.view;
                    document.querySelectorAll('.vt-btn').forEach(b => b.classList.toggle('is-active', b === btn));
                    if (view === 'calendar') {
                        split.style.display = 'none';
                        fullCard.style.display = 'block';
                        buildCalendar();
                        setTimeout(() => calendar && calendar.updateSize(), 60);
                    } else {
                        fullCard.style.display = 'none';
                        split.style.display = 'grid';
                    }
                });
            });

            // ── Layer toggles ─────────────────────────────────────
            document.querySelectorAll('.layer-toggle').forEach(input => {
                input.addEventListener('change', () => {
                    fetchMonth().then(() => { renderMini(); renderAgenda(); });
                    if (calendarBuilt && calendar) { calendar.refetchEvents(); }
                });
            });

            // ── Mini nav ──────────────────────────────────────────
            document.getElementById('mini-prev').addEventListener('click', () => {
                viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
                fetchMonth().then(() => { renderMini(); renderAgenda(); });
            });
            document.getElementById('mini-next').addEventListener('click', () => {
                viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
                fetchMonth().then(() => { renderMini(); renderAgenda(); });
            });
            document.getElementById('mini-today').addEventListener('click', () => {
                const now = new Date();
                selected = now;
                viewDate = new Date(now.getFullYear(), now.getMonth(), 1);
                fetchMonth().then(() => { renderMini(); renderAgenda(); });
            });

            // ── Boot ──────────────────────────────────────────────
            fetchMonth().then(() => { renderMini(); renderAgenda(); });
        });
    </script>
@endsection
