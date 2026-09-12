@extends('layouts.admin')

@section('title', 'Add Team · Med Alert')
@section('heading', 'Add Team')

@section('content')
    <div class="max-w-xl">
        @include('admin.teams._form', [
            'action' => route('admin.teams.store'),
            'method' => 'POST',
            'submitLabel' => 'Create Team',
        ])
    </div>
@endsection
