@extends('admin.layouts.app')
@section('title', __('customer.edit') . ' — ' . $location->name)

@section('content')
<h1>{{ __('customer.edit') }}: {{ $location->name }}</h1>

<div class="card">
    <form action="{{ route('locations.update', $location) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.locations._form')

        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button type="submit" class="btn btn-primary">{{ __('customer.save') }}</button>
            <a href="{{ route('locations.show', $location) }}" class="btn btn-secondary">{{ __('customer.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
