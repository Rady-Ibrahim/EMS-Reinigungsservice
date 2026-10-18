@extends('admin.layouts.app')
@section('title', __('customer.add_location'))

@section('content')
<h1>{{ __('customer.add_location') }} — {{ $customer->name }}</h1>

<div class="card">
    <form action="{{ route('admin.customers.locations.store', $customer) }}" method="POST">
        @csrf
        @include('admin.locations._form', ['location' => null])

        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button type="submit" class="btn btn-primary">{{ __('customer.save') }}</button>
            <a href="{{ route('admin.customers.locations.index', $customer) }}" class="btn btn-secondary">{{ __('customer.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
