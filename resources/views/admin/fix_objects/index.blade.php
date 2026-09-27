@extends('admin.layouts.app')
@section('title', __('fix_object.index_title'))

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">{{ __('fix_object.index_title') }}</h1>
        <a href="{{ route('admin.fix-objects.create') }}" class="btn btn-primary">{{ __('fix_object.new_fix_object') }}</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('fix_object.color') }}</th>
                <th>{{ __('fix_object.title_col') }}</th>
                <th>{{ __('fix_object.customer_location') }}</th>
                <th>{{ __('fix_object.frequency') }}</th>
                <th>{{ __('fix_object.contract_hours_short') }}</th>
                <th>{{ __('fix_object.employees') }}</th>
                <th>{{ __('fix_object.status') }}</th>
                <th>{{ __('fix_object.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fixObjects as $fo)
            <tr>
                <td>
                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:{{ $fo->calendar_color }};border:1px solid rgba(0,0,0,.15)"></span>
                </td>
                <td>
                    <a href="{{ route('admin.fix-objects.show', $fo) }}"><strong>{{ $fo->title }}</strong></a>
                </td>
                <td>
                    <small style="color:#64748b">{{ $fo->customer->name ?? '—' }}</small><br>
                    <small>{{ $fo->location->name ?? '—' }}</small>
                </td>
                <td><span class="badge badge-gray">{{ $fo->frequency->label() }}</span></td>
                <td>{{ $fo->contract_hours }}h</td>
                <td>{{ $fo->assignments_count }}</td>
                <td>
                    @if($fo->is_active)
                        <span class="badge badge-green">{{ __('fix_object.active') }}</span>
                    @else
                        <span class="badge badge-red">{{ __('fix_object.inactive') }}</span>
                    @endif
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('admin.fix-objects.edit', $fo) }}" class="btn btn-secondary btn-sm">{{ __('fix_object.edit_action') }}</a>
                        <form action="{{ route('admin.fix-objects.destroy', $fo) }}" method="POST"
                              onsubmit="return confirm('{{ __('fix_object.confirm_delete') }}')" style="display:inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">{{ __('fix_object.delete') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem">{{ __('fix_object.no_fix_objects') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $fixObjects->links() }}</div>
</div>
@endsection