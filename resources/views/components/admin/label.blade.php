{{--
    Shared Admin field label. Renders a red required asterisk that mirrors
    real server-side validation instead of being hand-copied per view.

    <x-admin.label for="first_name" required>First name</x-admin.label>
    <x-admin.label for="email">Email</x-admin.label>
    <x-admin.label for="group_id" required-if="touchedSchedule">Group</x-admin.label>
--}}
@props(['for' => null, 'required' => false, 'requiredIf' => null])
<label {{ $attributes->merge(['class' => 'admin-field-label']) }} @if($for) for="{{ $for }}" @endif>{{ $slot }}@if($requiredIf)<span class="required-mark" aria-hidden="true" x-show="{{ $requiredIf }}" x-cloak>*</span><span class="sr-only" x-show="{{ $requiredIf }}" x-cloak>Required</span>@elseif($required)<span class="required-mark" aria-hidden="true">*</span><span class="sr-only"> Required</span>@endif</label>
