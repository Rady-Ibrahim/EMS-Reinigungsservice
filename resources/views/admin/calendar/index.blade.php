@extends('admin.layouts.app')

@section('title', 'Kalender')
@section('content')
    <h1>Interaktiver Kalender</h1>

    @php $activeLayers = old('layers', ['fix', 'extra', 'shifts', 'personal', 'internal']); @endphp

    <div class="card">
        <div class="card-header">
            <div class="card-title">Layer-Filter</div>
        </div>
        <div id="layer-filters" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;">
            @foreach($layers as $layer)
                <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;">
                    <input type="checkbox" class="layer-toggle" value="{{ $layer['key'] }}"
                           {{ in_array($layer['key'], $activeLayers, true) ? 'checked' : '' }}>
                    <span class="color-dot" style="background:{{ $layer['color'] }}"></span>
                    {{ $layer['label'] }}
                </label>
            @endforeach

            <div style="margin-inline-start:auto;display:flex;gap:.5rem;align-items:center;">
                <a href="{{ route('admin.calendar.shifts.create') }}" class="btn btn-primary btn-sm">+ Schicht</a>
                <a href="{{ route('admin.calendar.appointments.create') }}" class="btn btn-primary btn-sm">+ Pers. Termin</a>
                <a href="{{ route('admin.calendar.internal-events.create') }}" class="btn btn-primary btn-sm">+ Interner Termin</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div id="calendar" style="min-height:600px;"></div>
    </div>

    @php
        $sources = [
            'jobs'     => ['label' => 'Arbeitseinsätze', 'color' => '#FFD700'],
            'shifts'   => ['label' => 'Schichten',       'color' => '#10b981'],
            'personal' => ['label' => 'Meine Termine',   'color' => '#8b5cf6'],
            'internal' => ['label' => 'Interne Termine', 'color' => '#f59e0b'],
            'teamup'   => ['label' => 'Teamup (extern)', 'color' => '#64748b'],
        ];
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const sources = {!! json_encode($sources) !!};

            const curatedColors = {!! json_encode($sources) !!};

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'de',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,agendaDay,listMonth'
                },
                height: 'auto',
                eventSources: [buildSource()],
                eventDidMount(info) {
                    if (info.event.extendedProps.has_conflict) {
                        info.el.style.boxShadow = '0 0 0 2px #ef4444';
                        info.el.title = 'Terminkonflikt!';
                    }
                },
                eventClick(info) {
                    const meta = info.event.extendedProps.metadata || {};
                    if (meta.detail_url) {
                        window.location.href = meta.detail_url;
                    }
                }
            });
            calendar.render();

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
        });
    </script>
@endsection