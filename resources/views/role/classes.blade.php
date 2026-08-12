@extends('layouts.app')

@section('content')
    <div class="page-head"><h1>{{ $heading }}</h1></div>
    <div class="card">
        <p class="muted">{{ $description }}</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Class</th><th>Course</th><th>Provider</th><th>Schedule</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($classes as $trainingClass)
                        <tr>
                            <td><strong>{{ $trainingClass->class_number }}</strong></td>
                            <td>{{ $trainingClass->course->code }} &mdash; {{ $trainingClass->course->name }}</td>
                            <td>{{ $trainingClass->provider?->name ?: 'Not assigned' }}</td>
                            <td>{{ $trainingClass->starts_at?->format('M j, Y H:i') ?: 'Not scheduled' }}</td>
                            <td><span class="badge {{ $trainingClass->status->value }}">{{ $trainingClass->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No assigned classes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $classes->links('components.pagination') }}
    </div>
@endsection
