@extends('layouts.admin')

@section('title', 'Edit Team · Med Alert')
@section('heading', 'Edit Team')

@section('content')
    <div class="max-w-xl">
        @include('admin.teams._form', [
            'action' => route('admin.teams.update', $team),
            'method' => 'PUT',
            'submitLabel' => 'Save Changes',
        ])
    </div>
@endsection
