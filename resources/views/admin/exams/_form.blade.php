@php
    $selected = old('question_ids', $exam->examQuestions?->pluck('question_id')->all() ?: []);
    $orders = old('display_orders', $exam->examQuestions?->pluck('display_order', 'question_id')->all() ?: []);
    $currentSubject = old('course_id', $selectedCourseId ?? $course?->id);
    $hasSchedule = $exam->exists && $exam->schedules()->exists();
    $orderDefaults = [];
    foreach ($questions as $index => $question) {
        $orderDefaults[(string) $question->id] = $orders[$question->id] ?? ($index + 1);
    }
    $questionMeta = $questions->map(fn ($question): array => [
        'id' => (string) $question->id,
        'difficulty' => $question->difficulty?->value,
        'type' => $question->type?->value,
    ])->values();
@endphp
<div class="admin-bento">
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">📝</span><h3>Exam details</h3></div>
        <div class="admin-field-grid">
            <div class="field full"><x-admin.label for="course_id" required>Subject</x-admin.label><select id="course_id" name="course_id" required><option value="">Select a Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected((string) $currentSubject === (string) $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>@endforeach</select><small class="muted">The Subject owns the reusable question bank for this exam.</small></div>
            <div class="field"><x-admin.label for="name" required>Exam name</x-admin.label><input id="name" name="name" value="{{ old('name', $exam->name) }}" required></div>
            <div class="field"><x-admin.label for="code">Exam code</x-admin.label><input id="code" name="code" value="{{ old('code', $exam->code) }}" {{ $exam->exists ? 'readonly' : '' }}>@unless($exam->exists)<small class="muted"><span class="admin-badge-generated">Auto-generated</span> Leave blank to auto-generate a unique code.</small>@endunless</div>
            <div class="field"><x-admin.label for="question_order_mode" required>Question order</x-admin.label><select id="question_order_mode" name="question_order_mode" required>@foreach(['static' => 'Static', 'shuffle' => 'Shuffle'] as $value => $label)<option value="{{ $value }}" @selected(old('question_order_mode', $exam->question_order_mode?->value) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><x-admin.label for="status" required>Status</x-admin.label><select id="status" name="status" required>@foreach(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $exam->status?->value) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><x-admin.label for="passing_score">Passing Score (%)</x-admin.label><input id="passing_score" type="number" name="passing_score" min="0" max="100" value="{{ old('passing_score', $exam->passing_score) }}"><small class="muted">The score required to pass.</small></div>
            <div class="field"><x-admin.label for="retake_score">Retake Score (%)</x-admin.label><input id="retake_score" type="number" name="retake_score" min="0" max="100" value="{{ old('retake_score', $exam->retake_score) }}"><small class="muted">The minimum score used for retake reporting.</small></div>
            <div class="field full"><x-admin.label for="description">Description</x-admin.label><textarea id="description" name="description">{{ old('description', $exam->description) }}</textarea></div>
        </div>
    </div>

    @if($hasSchedule)
        <div class="admin-bento-card admin-bento-card--wide">
            <div class="admin-card-head"><span class="admin-card-icon cool">🗓️</span><h3>Group scheduling</h3></div>
            <p class="admin-card-note">This Exam/Class is already scheduled for {{ $exam->schedules()->count() }} Group{{ $exam->schedules()->count() === 1 ? '' : 's' }}. Manage or add another Group from the <a href="{{ route('admin.exams.show', $exam) }}">exam page</a>.</p>
        </div>
    @else
        <div class="admin-bento-card admin-bento-card--wide" x-data="{ groupId: @js(old('group_id', '')), startDate: @js(old('start_date', '')), endDate: @js(old('end_date', '')), durationMinutes: @js(old('duration_minutes', '')), get scheduleTouched() { return !!(this.groupId || this.startDate || this.endDate || this.durationMinutes); } }">
            <div class="admin-card-head"><span class="admin-card-icon cool">🗓️</span><h3>Group scheduling (optional)</h3></div>
            <p class="admin-card-note">Choose a Group and dates to schedule this Exam's first Class right now &mdash; Proctor, Instructor, and Student interfaces see it immediately, with no separate scheduling step. Leave blank to schedule later, or to offer this Exam to more than one Group, from the exam page.</p>
            <div class="admin-field-grid">
                <div class="field"><x-admin.label for="group_id" required-if="scheduleTouched">Group</x-admin.label><select id="group_id" name="group_id" x-model="groupId"><option value="">Select a Group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="field"><x-admin.label for="duration_minutes" required-if="scheduleTouched">Duration (minutes)</x-admin.label><input id="duration_minutes" type="number" min="1" name="duration_minutes" x-model="durationMinutes"></div>
                <div class="field"><x-admin.label for="start_date" required-if="scheduleTouched">Start date</x-admin.label><input id="start_date" type="date" name="start_date" x-model="startDate"></div>
                <div class="field"><x-admin.label for="end_date" required-if="scheduleTouched">End date</x-admin.label><input id="end_date" type="date" name="end_date" x-model="endDate"></div>
            </div>
        </div>
    @endif

    <div class="admin-bento-card admin-bento-card--wide" x-data="examQuestionPicker(@js($questionMeta), @js(array_map('strval', $selected)), @js($orderDefaults))">
        <div class="admin-card-head"><span class="admin-card-icon">❓</span><h3>Questions<span class="required-mark" aria-hidden="true">*</span></h3></div>
        <span class="sr-only">Required</span>
        <p class="admin-card-note">Select questions from the chosen Subject, or auto-select a random set below ({{ $questions->count() }} active question{{ $questions->count() === 1 ? '' : 's' }} available).</p>

        <div class="auto-select-panel">
            <div class="admin-section-head" style="margin-bottom:8px">
                <div><strong>Auto-select questions</strong><p class="muted" style="margin:2px 0 0">Randomly picks from this Subject's active question bank.</p></div>
                <span class="badge active" x-text="selected.length + ' selected'"></span>
            </div>

            <div class="radio-row">
                <label><input type="radio" x-model="auto.mode" value="total"> Total count</label>
                <label><input type="radio" x-model="auto.mode" value="breakdown"> By difficulty</label>
            </div>

            <div class="admin-field-grid" style="margin-top:12px" x-show="auto.mode === 'total'">
                <div class="field"><x-admin.label for="auto_total">Number of questions</x-admin.label><input id="auto_total" type="number" min="1" x-model.number="auto.total"></div>
                <div class="field"><x-admin.label for="auto_type_total">Type (optional)</x-admin.label>
                    <select id="auto_type_total" x-model="auto.type">
                        <option value="">Any type</option>
                        <option value="mcq">Multiple choice</option>
                        <option value="true_false">True / False</option>
                        <option value="input">Text input</option>
                    </select>
                </div>
            </div>

            <div class="admin-field-grid" style="margin-top:12px" x-show="auto.mode === 'breakdown'" x-cloak>
                <div class="field"><x-admin.label for="auto_easy">Easy</x-admin.label><input id="auto_easy" type="number" min="0" x-model.number="auto.easy"></div>
                <div class="field"><x-admin.label for="auto_medium">Medium</x-admin.label><input id="auto_medium" type="number" min="0" x-model.number="auto.medium"></div>
                <div class="field"><x-admin.label for="auto_hard">Hard</x-admin.label><input id="auto_hard" type="number" min="0" x-model.number="auto.hard"></div>
                <div class="field"><x-admin.label for="auto_type_breakdown">Type (optional)</x-admin.label>
                    <select id="auto_type_breakdown" x-model="auto.type">
                        <option value="">Any type</option>
                        <option value="mcq">Multiple choice</option>
                        <option value="true_false">True / False</option>
                        <option value="input">Text input</option>
                    </select>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:7px;margin-top:12px;font-weight:400"><input type="checkbox" x-model="auto.replace"> Replace currently selected questions</label>

            <div class="actions" style="margin-top:14px">
                <button type="button" class="btn secondary" x-on:click="autoSelect">Auto-select</button>
                <button type="button" class="btn secondary" x-show="selected.length > 0" x-on:click="clearSelection" x-cloak>Clear selection</button>
            </div>

            <p class="auto-select-warning" x-show="warning" x-text="warning" x-cloak></p>
        </div>

        <div class="table-wrap"><table class="table"><thead><tr><th>Select</th><th>Order</th><th>Question</th><th>Subject</th><th>Type</th><th>Difficulty</th></tr></thead><tbody>@forelse($questions as $question)<tr><td><input type="checkbox" name="question_ids[]" value="{{ $question->id }}" x-model="selected"></td><td><input style="width:80px" type="number" name="display_orders[{{ $question->id }}]" x-model.number="orders['{{ $question->id }}']" min="1"></td><td><div class="admin-question-cell">@if($question->question_image_path)<img class="admin-question-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) }}" alt="Question image">@endif<div><span>{{ $question->question_text }}</span>@if($question->options->whereNotNull('image_path')->isNotEmpty() || $question->correct_answer_image_path)<small class="image-count">{{ $question->options->whereNotNull('image_path')->count() + ($question->correct_answer_image_path ? 1 : 0) }} answer image{{ $question->options->whereNotNull('image_path')->count() + ($question->correct_answer_image_path ? 1 : 0) === 1 ? '' : 's' }}</small>@endif</div></div></td><td>{{ $question->course?->name }}</td><td>{{ $question->type->label() }}</td><td>{{ $question->difficulty->label() }}</td></tr>@empty<tr><td colspan="6" class="muted">Choose a Subject with active questions first.</td></tr>@endforelse</tbody></table></div>
    </div>
</div>
<div class="actions" style="margin-top:20px"><button class="btn">Save exam</button><a class="btn secondary" href="{{ $exam->exists ? route('admin.exams.show', $exam) : route('admin.exams.index') }}">Cancel</a></div>

<style>
.auto-select-panel{margin:12px 0 18px;padding:16px 18px;border:1px dashed var(--admin-line);border-radius:8px;background:#f8fafc}
.auto-select-panel .radio-row{display:flex;gap:20px;align-items:center;margin-top:2px}
.auto-select-panel .radio-row label{font-weight:400;display:flex;align-items:center;gap:6px}
.auto-select-warning{margin:12px 0 0;padding:9px 12px;border-radius:6px;background:#fff4e5;color:#8a5a00;font-size:13px}
</style>

@push('scripts')
<script>
function examQuestionPicker(questions, initialSelected, initialOrders) {
    return {
        questions,
        selected: initialSelected.slice(),
        orders: { ...initialOrders },
        auto: { mode: 'total', total: null, easy: null, medium: null, hard: null, type: '', replace: true },
        warning: '',
        shuffle(list) {
            const copy = list.slice();
            for (let i = copy.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [copy[i], copy[j]] = [copy[j], copy[i]];
            }
            return copy;
        },
        autoSelect() {
            this.warning = '';
            let pool = this.questions;
            if (this.auto.type) {
                pool = pool.filter((question) => question.type === this.auto.type);
            }
            if (!this.auto.replace) {
                const already = new Set(this.selected);
                pool = pool.filter((question) => !already.has(question.id));
            }

            let chosen = [];
            const shortages = [];

            if (this.auto.mode === 'breakdown') {
                [['easy', this.auto.easy], ['medium', this.auto.medium], ['hard', this.auto.hard]].forEach(([level, wantRaw]) => {
                    const want = Number(wantRaw) || 0;
                    if (want <= 0) return;
                    const candidates = this.shuffle(pool.filter((question) => question.difficulty === level).map((question) => question.id));
                    const take = candidates.slice(0, want);
                    if (take.length < want) {
                        shortages.push(`${level}: requested ${want}, only ${take.length} available`);
                    }
                    chosen.push(...take);
                });
                if (chosen.length === 0 && shortages.length === 0) {
                    this.warning = 'Enter at least one difficulty count.';
                    return;
                }
            } else {
                const want = Number(this.auto.total) || 0;
                if (want <= 0) {
                    this.warning = 'Enter the number of questions to select.';
                    return;
                }
                chosen = this.shuffle(pool.map((question) => question.id)).slice(0, want);
                if (chosen.length < want) {
                    shortages.push(`requested ${want}, only ${chosen.length} available`);
                }
            }

            if (this.auto.replace) {
                this.selected = [];
                this.orders = {};
            }

            let next = Object.keys(this.orders)
                .filter((id) => this.selected.includes(id))
                .reduce((max, id) => Math.max(max, Number(this.orders[id]) || 0), 0) + 1;
            chosen.forEach((id) => {
                if (!this.selected.includes(id)) {
                    this.selected.push(id);
                }
                this.orders[id] = next++;
            });

            this.warning = shortages.length
                ? `Selected everything available (${shortages.join('; ')}).`
                : `Auto-selected ${chosen.length} question(s).`;
        },
        clearSelection() {
            this.selected = [];
            this.orders = {};
            this.warning = '';
        },
    };
}
</script>
@endpush
