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
                <a href="{{ route('admin.calendar.shifts.create') }}" class="btn-gold-outline btn-sm">{{ $cal['new_shift'] }}</a>
                <a href="{{ route('admin.calendar.appointments.create') }}" class="btn-gold-outline btn-sm">{{ $cal['new_personal'] }}</a>
                <a href="{{ route('admin.calendar.internal-events.create') }}" class="btn-gold-outline btn-sm">{{ $cal['new_internal'] }}</a>
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="card" style="padding:1rem;">
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

    <style>
        /* ── Toolbar ───────────────────────────────────────────── */
        .cal-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .layer-filters { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; }
        .layer-pill {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .4rem .75rem; border-radius: 999px; cursor: pointer;
            background: var(--panel-2); border: 1px solid var(--border);
            font-size: .8rem; font-weight: 600; color: var(--text-muted);
            transition: all .15s ease; user-select: none;
        }
        .layer-pill:hover { border-color: rgba(212,175,55,.4); color: var(--text); }
        .layer-pill:has(input:checked) {
            background: rgba(212,175,55,.12); border-color: rgba(212,175,55,.45); color: var(--gold-light);
        }
        .layer-pill input { display: none; }
        .cal-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn-gold-outline { border: 1px solid rgba(212,175,55,.45); }

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
            z-index: 80; opacity: 0; pointer-events: none; transition: opacity .25s ease;
        }
        .panel-backdrop.open { opacity: 1; pointer-events: auto; }

        .slide-panel {
            position: fixed; top: 0; right: 0; bottom: 0; width: min(480px, 94vw);
            z-index: 90;
            background: linear-gradient(180deg, #1A1D25 0%, #12141A 60%, #0E1013 100%);
            border-inline-start: 1px solid var(--border-gold);
            box-shadow: -30px 0 80px rgba(0,0,0,.65);
            transform: translateX(110%);
            transition: transform .32s cubic-bezier(.22,.9,.25,1);
            display: flex; flex-direction: column;
        }
        .slide-panel.open { transform: translateX(0); }

        html[dir="rtl"] .slide-panel { right: auto; left: 0; border-inline-start: none; border-inline-end: 1px solid var(--border-gold); transform: translateX(-110%); box-shadow: 30px 0 80px rgba(0,0,0,.65); }
        html[dir="rtl"] .slide-panel.open { transform: translateX(0); }

        .sp-head { padding: 1.2rem 1.4rem; border-bottom: 1px solid var(--border-gold); background: rgba(212,175,55,.05); }
        .sp-kicker { font-size: .64rem; letter-spacing: .18em; text-transform: uppercase; color: var(--gold-light); font-weight: 700; }
        .sp-head h3 { font-size: 1.15rem; font-weight: 800; color: var(--text); margin-top: .25rem; }
        .sp-close {
            position: absolute; top: 1rem; inset-inline-end: 1rem;
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--panel-2); border: 1px solid var(--border); color: var(--text-muted);
            cursor: pointer; font-size: .9rem;
        }
        .sp-close:hover { color: var(--gold-light); border-color: rgba(212,175,55,.45); }

        .sp-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.4rem 2rem; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }

        .sp-section { margin-bottom: 1.4rem; }
        .sp-section-title {
            font-size: .64rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: var(--gold-light); margin-bottom: .6rem; display: flex; align-items: center; gap: .5rem;
        }
        .sp-section-title::before { content: ""; width: 14px; height: 1px; background: linear-gradient(90deg, var(--gold), transparent); }

        .sp-row { display: flex; gap: .7rem; align-items: flex-start; padding: .34rem 0; font-size: .86rem; }
        .sp-row .sp-ico { width: 18px; text-align: center; opacity: .8; }
        .sp-row .sp-k { color: var(--text-faint); font-size: .78rem; min-width: 104px; }
        .sp-row .sp-v { color: var(--text); font-weight: 600; flex: 1; }

        .sp-duo { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; margin-top: .4rem; }
        .sp-duo .sp-tile {
            background: rgba(10,11,13,.6); border: 1px solid var(--border); border-radius: 11px; padding: .7rem .8rem;
        }
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
        .check-item {
            display: flex; align-items: center; gap: .6rem;
            background: rgba(10,11,13,.55); border: 1px solid var(--border);
            border-radius: 10px; padding: .55rem .7rem; font-size: .82rem; color: var(--text);
            cursor: pointer; transition: all .15s ease;
        }
        .check-item:hover { border-color: rgba(212,175,55,.4); }
        .check-item input { accent-color: var(--gold); width: 15px; height: 15px; }
        .check-item.done { opacity: .6; }
        .check-item.done .ci-label { text-decoration: line-through; }

        .photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .6rem; }
        .photo-tile {
            position: relative; aspect-ratio: 4/3; border-radius: 11px; overflow: hidden;
            background: linear-gradient(145deg, #20242D, #13151B);
            border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
            color: var(--gold-deep); font-size: 1.7rem;
        }
        .photo-tile .pt-tag {
            position: absolute; top: 6px; inset-inline-start: 6px;
            background: rgba(4,5,7,.75); color: var(--gold-light); border: 1px solid rgba(212,175,55,.4);
            font-size: .6rem; font-weight: 700; letter-spacing: .08em; padding: .1rem .4rem; border-radius: 999px;
        }
        .photo-tile .pt-sub { position: absolute; bottom: 6px; inset-inline-end: 6px; font-size: .58rem; color: var(--text-faint); }

        .gps-row {
            display: flex; align-items: center; gap: .6rem; padding: .45rem .2rem; font-size: .8rem;
            border-bottom: 1px dashed rgba(42,46,57,.55);
        }
        .gps-row:last-child { border-bottom: none; }
        .gps-ico { width: 16px; text-align: center; }
        .gps-t { color: var(--text-faint); font-variant-numeric: tabular-nums; }
        .gps-v { color: var(--text-muted); flex: 1; }
        .gps-ok { color: var(--green); font-weight: 700; font-size: .74rem; }

        .detail-link {
            display: block; text-align: center; margin-top: .8rem;
            color: var(--gold-light); font-weight: 700; font-size: .84rem; text-decoration: none;
        }
        .detail-link:hover { text-decoration: underline; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/timeline@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const employeeMap = {!! json_encode($employeeMap) !!};
            const T = {!! json_encode($cal) !!};
            const locale = '{{ app()->getLocale() }}';

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: locale,
                direction: locale === 'ar' ? 'rtl' : 'ltr',
                plugins: [FullCalendar.Timeline],
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,timelineWeek,listWeek'
                },
                buttonText: {
                    today: T.today, dayGridMonth: T.month, timeGridWeek: T.week,
                    timeGridDay: T.day, timelineWeek: T.timeline, listWeek: T.agenda
                },
                firstDay: 1,
                height: 'auto',
                eventDisplay: 'auto',
                eventSources: [buildSource()],
                eventDidMount(info) {
                    const el = info.el;
                    const m = info.event.extendedProps;
                    if (m.has_conflict) {
                        el.style.boxShadow = '0 0 0 2px #ef4444, 0 4px 14px rgba(239,68,68,.45)';
                        el.style.borderColor = '#ef4444';
                        el.title = T.conflict;
                    }
                    const c = themeColor(info.event);
                    if (c) { el.style.backgroundColor = c; el.style.borderColor = 'transparent'; }
                },
                eventClick(info) {
                    info.jsEvent.preventDefault();
                    openPanel(info.event.toPlainObject());
                }
            });
            calendar.render();

            function themeColor(ev) {
                const t = (ev.title || '').toLowerCase();
                const st = ((ev.extendedProps && ev.extendedProps.status_label) || '').toLowerCase();
                if (ev.extendedProps && ev.extendedProps.has_conflict) { return null; }
                if (/abgeschlossen|completed|erledigt/.test(st)) { return '#059669'; }
                if (t.includes('büro') || t.includes('blackshark')) { return '#0284C7'; }
                if (t.includes('insitu') || t.includes('geotechnik')) { return '#7C3AED'; }
                if (t.includes('bauend') || t.includes('baureinigung')) { return '#EA580C'; }
                if (t.includes('wiki')) { return '#10B981'; }
                return null;
            }

            function activeLayers() {
                return [...document.querySelectorAll('.layer-toggle:checked')].map(c => c.value);
            }

            function buildSource() {
                return {
                    url: '{{ route('admin.calendar.events') }}',
                    method: 'GET',
                    extraParams() {
                        const layers = activeLayers();
                        return {
                            from: calendar.view.currentStart.toISOString(),
                            to: calendar.view.currentEnd.toISOString(),
                            layers: layers.join(','),
                        };
                    },
                    failure(err) { console.error(err); },
                };
            }

            document.querySelectorAll('.layer-toggle').forEach(input => {
                input.addEventListener('change', () => calendar.refetchSources());
            });

            // ── Slide-over panel ──────────────────────────────────
            const panel    = document.getElementById('slide-panel');
            const backdrop = document.getElementById('panel-backdrop');

            window.openPanel = function (ev) {
                document.getElementById('sp-title').textContent = ev.title || T.appointment;
                document.getElementById('sp-body').innerHTML = panelContent(ev);
                panel.classList.add('open');
                backdrop.classList.add('open');
                panel.setAttribute('aria-hidden', 'false');
                // wire checklist
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
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });

            function panelContent(ev) {
                const m = ev.extendedProps || {};
                const meta = m.metadata || {};
                const layer = m.layer || ev.type || '';
                const allDay = ev.allDay || m.all_day;
                const intlLocale = locale === 'ar' ? 'ar-EG' : 'de-DE';
                const arrow = locale === 'ar' ? '←' : '→';

                const timeFmt = new Intl.DateTimeFormat(intlLocale, { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                const dateFmt = new Intl.DateTimeFormat(intlLocale, { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
                const timeOnly = new Intl.DateTimeFormat(intlLocale, { hour: '2-digit', minute: '2-digit' });

                const startTxt = allDay ? dateFmt.format(new Date(ev.start)) : timeFmt.format(new Date(ev.start));
                const endTxt   = allDay ? T.all_day : timeFmt.format(new Date(ev.end));

                // employees
                const ids = Array.isArray(m.employee_ids) ? m.employee_ids : [];
                const names = ids.map(id => employeeMap[id] || T.employee_fallback + ' #' + id).filter(Boolean);
                const empList = names.length ? names.join(', ') : (meta.employee_name || meta.owner_name || meta.creator_name || '—');

                // address for navigation
                const address = m.location || meta.location_name || (meta.customer_name ? meta.customer_name : null) || ev.title || '';
                const navHref = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(address);

                // working hours
                const hours = allDay ? '—' : workingHours(new Date(ev.start), new Date(ev.end));
                const contract = meta.contract_hours ? parseFloat(meta.contract_hours).toFixed(2).replace('.', ',') : null;
                const estimated = meta.estimated_hours ? parseFloat(meta.estimated_hours).toFixed(2).replace('.', ',') : null;

                // checklist
                const done = /abgeschlossen|completed|erledigt/.test((m.status_label || '').toLowerCase());
                const checklist = T.checklist_items;

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
                            ${checklist.map((c, i) => `
                                <label class="check-item ${done ? 'done' : ''}">
                                    <input type="checkbox" ${done ? 'checked' : ''}>
                                    <span class="ci-label">${c}</span>
                                </label>`).join('')}
                        </div>
                    </div>

                    <div class="sp-section">
                        <div class="sp-section-title">${T.photos}</div>
                        <div class="photo-grid">
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.before}</span><span class="pt-sub">${T.photo} 1</span></div>
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.after}</span><span class="pt-sub">${T.photo} 2</span></div>
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.before}</span><span class="pt-sub">${T.photo} 3</span></div>
                            <div class="photo-tile"><span>📷</span><span class="pt-tag">${T.after}</span><span class="pt-sub">${T.photo} 4</span></div>
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
                            <div class="gps-row"><span class="gps-ico">🏁</span><span class="gps-t">${escapeHtml(allDay ? '—' : timeOnly.format(new Date(ev.end)))}</span><span class="gps-v">${T.check_out}</span><span class="gps-ok">🛰️</span></div>
                        </div>
                    </div>

                    ${m.detail_url ? '<a class="detail-link" href="' + m.detail_url + '">' + T.detail_link + ' ' + arrow + '</a>' : ''}
                `;
            }

            function workingHours(start, end) {
                if (!end || isNaN(end)) { return '—'; }
                const ms = Math.max(0, end - start);
                const h = Math.floor(ms / 3600000);
                const min = Math.floor((ms % 3600000) / 60000);
                return String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0') + ' h';
            }

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
            }
        });
    </script>
@endsection