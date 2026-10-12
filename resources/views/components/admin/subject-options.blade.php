@props(['subjects', 'selected' => null])
@foreach($subjects->groupBy(fn ($subject) => $subject->subjectCategory?->name ?? 'Uncategorized')->sortKeys() as $categoryName => $categorySubjects)
    <optgroup label="{{ $categoryName }}">
        @foreach($categorySubjects as $subject)
            <option value="{{ $subject->id }}" @selected((string) $selected === (string) $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
        @endforeach
    </optgroup>
@endforeach
