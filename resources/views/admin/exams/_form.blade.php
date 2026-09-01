@php
    $selected = old('question_ids', $exam->examQuestions?->pluck('question_id')->all() ?: []);
    $orders = old('display_orders', $exam->examQuestions?->pluck('display_order', 'question_id')->all() ?: []);
    $currentSubject = old('course_id', $selectedCourseId ?? $course?->id);
    $allowSubjectChange = $allowSubjectChange ?? false;
    $selectionMode = old('question_selection_mode', $exam->question_selection_mode?->value ?? 'manual');
    $questionCount = old('question_count', $exam->question_count);
    $hasSchedule = $exam->exists && $exam->schedules()->exists();
    $orderDefaults = [];
    foreach ($questions as $index => $question) {
        $orderDefaults[(string) $question->id] = $orders[$question->id] ?? ($index + 1);
    }
    $questionBanks = $questionBanks ?? [
        (string) $currentSubject => $questions->map(fn ($question): array => [
            'id' => (string) $question->id,
            'text' => $question->question_text,
            'subject' => $question->course?->name,
            'type' => $question->type?->value,
            'difficulty' => $question->difficulty?->value,
            'image_url' => $question->question_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) : null,
        ])->values()->all(),
    ];
@endphp
<div class="admin-bento" x-data="examQuestionPicker(@js($questionBanks), @js((string) $currentSubject), @js(array_map('strval', $selected)), @js($orderDefaults), @js($selectionMode), @js($questionCount))">
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">📝</span><h3>Exam details</h3></div>
        <div class="admin-field-grid">
            <div class="field full"><x-admin.label for="course_id" required>Subject</x-admin.label><select id="course_id" name="course_id" required @if($allowSubjectChange && !$exam->exists) x-model="subjectId" x-on:change="changeSubject" @endif><option value="">Select a Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected((string) $currentSubject === (string) $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>@endforeach</select><small class="muted">{{ $allowSubjectChange && !$exam->exists ? 'Changing the Subject updates its active question bank instantly.' : 'Questions are selected from the current Subject.' }}</small></div>
            <div class="field"><x-admin.label for="name" required>Exam name</x-admin.label><input id="name" name="name" value="{{ old('name', $exam->name) }}" required></div>
            <div class="field"><x-admin.label for="code">Exam code</x-admin.label><input id="code" name="code" value="{{ old('code', $exam->code) }}" {{ $exam->exists ? 'readonly' : '' }}>@unless($exam->exists)<small class="muted"><span class="admin-badge-generated">Auto-generated</span> Leave blank to auto-generate a unique code.</small>@endunless</div>
            <div class="field"><x-admin.label for="question_order_mode" required>Question order</x-admin.label><select id="question_order_mode" name="question_order_mode" required x-bind:disabled="selectionMode === 'random'">@foreach(['static' => 'Static', 'shuffle' => 'Shuffle'] as $value => $label)<option value="{{ $value }}" @selected(old('question_order_mode', $exam->question_order_mode?->value) === $value)>{{ $label }}</option>@endforeach</select><small class="muted" x-show="selectionMode === 'random'" x-cloak>Random exams use static order after selecting each student's question set.</small></div>
            <div class="field"><x-admin.label for="question_selection_mode" required>Question selection</x-admin.label><select id="question_selection_mode" name="question_selection_mode" required x-model="selectionMode" x-on:change="changeSelectionMode"><option value="manual">Manual question selection</option><option value="random">Random questions per student</option></select><small class="muted">Random mode chooses a new fixed question set when each student starts.</small></div>
            <div class="field" x-show="selectionMode === 'random'" x-cloak><x-admin.label for="question_count" required>Number of questions</x-admin.label><input id="question_count" type="number" name="question_count" min="1" x-model.number="questionCount" x-bind:required="selectionMode === 'random'"><small class="muted">The count cannot exceed the Subject's active question bank.</small></div>
            <div class="field"><x-admin.label for="status" required>Status</x-admin.label><select id="status" name="status" required>@foreach(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $exam->status?->value) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><x-admin.label for="passing_score">Passing Score (%)</x-admin.label><input id="passing_score" type="number" name="passing_score" min="0" max="100" value="{{ old('passing_score', $exam->passing_score) }}"><small class="muted">The score required to pass.</small></div>
            <div class="field"><x-admin.label for="retake_score">Retake Score (%)</x-admin.label><input id="retake_score" type="number" name="retake_score" min="0" max="100" value="{{ old('retake_score', $exam->retake_score) }}"><small class="muted">The minimum score used for retake reporting.</small></div>
            <div class="field"><x-admin.label for="certificate_validity_years">Certificate Validity (years)</x-admin.label><input id="certificate_validity_years" type="number" name="certificate_validity_years" min="1" max="99" value="{{ old('certificate_validity_years', $exam->certificate_validity_years) }}"><small class="muted">How many years a certificate earned from this exam stays valid before it expires. Leave blank to use the default (2 years).</small></div>
            <div class="field full"><x-admin.label for="description">Description</x-admin.label><textarea id="description" name="description">{{ old('description', $exam->description) }}</textarea></div>
        </div>
    </div>

    @if($hasSchedule)
        <div class="admin-bento-card admin-bento-card--wide">
            <div class="admin-card-head"><span class="admin-card-icon cool">🗓️</span><h3>Group scheduling</h3></div>
            <p class="admin-card-note">This Exam/Class is already scheduled for {{ $exam->schedules()->count() }} Group{{ $exam->schedules()->count() === 1 ? '' : 's' }}. Manage or add another Group from the <a href="{{ route('admin.exams.show', $exam) }}">exam page</a>.</p>
        </div>
    @else
        <div class="admin-bento-card admin-bento-card--wide" x-data="{ groupId: @js(old('group_id', '')), startDate: @js(old('start_date', '')), endDate: @js(old('end_date', '')), durationMinutes: @js(old('duration_minutes', '')), proctorId: @js(old('proctor_id', '')), instructorId: @js(old('instructor_id', '')), get scheduleTouched() { return !!(this.groupId || this.startDate || this.endDate || this.durationMinutes || this.proctorId || this.instructorId); } }">
            <div class="admin-card-head"><span class="admin-card-icon cool">🗓️</span><h3>Group scheduling (optional)</h3></div>
            <p class="admin-card-note">Choose a Group, dates, and staff to schedule this Exam's first Class right now &mdash; Proctor, Instructor, and Student interfaces see it immediately, with no separate scheduling step. Leave blank to schedule later, or to offer this Exam to more than one Group, from the exam page.</p>
            <div class="admin-field-grid">
                <div class="field"><x-admin.label for="group_id" required-if="scheduleTouched">Group</x-admin.label><select id="group_id" name="group_id" x-model="groupId"><option value="">Select a Group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="field"><x-admin.label for="duration_minutes" required-if="scheduleTouched">Duration (minutes)</x-admin.label><input id="duration_minutes" type="number" min="1" name="duration_minutes" x-model="durationMinutes"></div>
                <div class="field"><x-admin.label for="start_date" required-if="scheduleTouched">Start date</x-admin.label><input id="start_date" type="date" name="start_date" x-model="startDate"></div>
                <div class="field"><x-admin.label for="end_date" required-if="scheduleTouched">End date</x-admin.label><input id="end_date" type="date" name="end_date" x-model="endDate"></div>
                <div class="field full"><x-admin.label for="start_mode" required>Exam start mechanism</x-admin.label><select id="start_mode" name="start_mode"><option value="automatic" @selected(old('start_mode', 'automatic') === 'automatic')>Automatic — follow start/end dates</option><option value="manual" @selected(old('start_mode', 'automatic') === 'manual')>Manual — Proctor or Proctor ID</option></select><small class="muted">This setting applies to this Group. Manual schedules do not start when the start date arrives.</small></div>
                <div class="field"><x-admin.label for="proctor_id" required-if="scheduleTouched">Proctor</x-admin.label><select id="proctor_id" name="proctor_id" x-model="proctorId"><option value="">Select Proctor</option>@foreach($proctors as $proctor)<option value="{{ $proctor->id }}" @selected((string) old('proctor_id') === (string) $proctor->id)>{{ $proctor->wellsharp_id }} - {{ $proctor->display_name }}</option>@endforeach</select></div>
                <div class="field"><x-admin.label for="instructor_id" required-if="scheduleTouched">Instructor</x-admin.label><select id="instructor_id" name="instructor_id" x-model="instructorId"><option value="">Select Instructor</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected((string) old('instructor_id') === (string) $instructor->id)>{{ $instructor->wellsharp_id }} - {{ $instructor->display_name }}</option>@endforeach</select></div>
            </div>
        </div>
    @endif

    <div class="admin-bento-card admin-bento-card--wide" x-show="selectionMode === 'manual'" x-cloak>
        <div class="admin-card-head"><span class="admin-card-icon">❓</span><h3>Questions<span class="required-mark" aria-hidden="true">*</span></h3></div>
        <span class="sr-only">Required</span>
        <p class="admin-card-note">Select questions from the chosen Subject, or auto-select a random set below (<span x-text="subjectQuestions().length"></span> active questions available).</p>

        <div class="question-tools" role="search" aria-label="Question bank tools">
            <label class="question-search"><span class="sr-only">Search questions</span><input type="search" x-model.debounce.200ms="search" placeholder="Search questions..."></label>
            <select x-model="filterType" aria-label="Filter by question type">
                <option value="">All types</option>
                <option value="mcq">Multiple choice</option>
                <option value="true_false">True / False</option>
                <option value="input">Text input</option>
            </select>
            <select x-model="filterDifficulty" aria-label="Filter by difficulty">
                <option value="">All difficulties</option>
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
            </select>
            <button type="button" class="btn secondary small" x-on:click="sortBy('text')">Sort question <span x-text="sortIcon('text')"></span></button>
            <button type="button" class="btn secondary small" x-on:click="sortBy('difficulty')">Sort difficulty <span x-text="sortIcon('difficulty')"></span></button>
            <span class="muted" x-text="filteredQuestions().length + ' shown · ' + selected.length + ' selected'"></span>
        </div>

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

        <template x-for="questionId in hiddenSelectedIds()" :key="'hidden-selected-' + questionId">
            <input type="hidden" name="question_ids[]" :value="questionId" x-bind:disabled="selectionMode === 'random'">
            <input type="hidden" :name="'display_orders[' + questionId + ']'" :value="orders[questionId] || ''" x-bind:disabled="selectionMode === 'random'">
        </template>

        <div class="table-wrap"><table class="table"><thead><tr><th>Select</th><th>Order</th><th>Question</th><th>Subject</th><th>Type</th><th>Difficulty</th></tr></thead><tbody>
            <template x-for="question in filteredQuestions()" :key="question.id">
                <tr>
                    <td><input type="checkbox" name="question_ids[]" :value="question.id" x-model="selected" x-bind:disabled="selectionMode === 'random'"></td>
                    <td><input style="width:80px" type="number" :name="'display_orders[' + question.id + ']'" x-model.number="orders[question.id]" min="1" x-bind:disabled="selectionMode === 'random'"></td>
                    <td><div class="admin-question-cell"><template x-if="question.image_url"><img class="admin-question-thumb" :src="question.image_url" alt="Question image"></template><div><span x-text="question.text"></span></div></div></td>
                    <td x-text="question.subject"></td>
                    <td x-text="question.type === 'mcq' ? 'Multiple choice' : (question.type === 'true_false' ? 'True / False' : 'Text input')"></td>
                    <td x-text="question.difficulty ? question.difficulty.charAt(0).toUpperCase() + question.difficulty.slice(1) : ''"></td>
                </tr>
            </template>
            <tr x-show="filteredQuestions().length === 0"><td colspan="6" class="muted" x-text="subjectQuestions().length ? 'No questions match the current filters.' : 'Choose a Subject with active questions first.'"></td></tr>
        </tbody></table></div>
    </div>
</div>
<div class="actions" style="margin-top:20px"><button class="btn">Save exam</button><a class="btn secondary" href="{{ $exam->exists ? route('admin.exams.show', $exam) : route('admin.exams.index') }}">Cancel</a></div>

<style>
.auto-select-panel{margin:12px 0 18px;padding:16px 18px;border:1px dashed var(--admin-line);border-radius:8px;background:#f8fafc}
.auto-select-panel .radio-row{display:flex;gap:20px;align-items:center;margin-top:2px}
.auto-select-panel .radio-row label{font-weight:400;display:flex;align-items:center;gap:6px}
.auto-select-warning{margin:12px 0 0;padding:9px 12px;border-radius:6px;background:#fff4e5;color:#8a5a00;font-size:13px}
.question-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:12px 0 18px}
.question-tools select,.question-search input{min-width:150px;padding:9px 10px;border:1px solid var(--admin-line);border-radius:6px;background:#fff}
.question-search{flex:1 1 260px}
.question-search input{width:100%}
</style>

@push('scripts')
<script>
function examQuestionPicker(questionBanks, initialSubject, initialSelected, initialOrders, initialSelectionMode, initialQuestionCount) {
    return {
        questionBanks,
        subjectId: initialSubject,
        selectionMode: initialSelectionMode || 'manual',
        questionCount: initialQuestionCount,
        selected: initialSelected.slice(),
        orders: { ...initialOrders },
        search: '',
        filterType: '',
        filterDifficulty: '',
        sortField: 'text',
        sortDirection: 'asc',
        auto: { mode: 'total', total: null, easy: null, medium: null, hard: null, type: '', replace: true },
        warning: '',
        subjectQuestions() {
            return this.questionBanks[String(this.subjectId)] || [];
        },
        hiddenSelectedIds() {
            const visibleIds = new Set(this.filteredQuestions().map((question) => question.id));
            return this.selected.filter((questionId) => !visibleIds.has(questionId));
        },
        filteredQuestions() {
            const search = this.search.trim().toLowerCase();
            const filtered = this.subjectQuestions().filter((question) => {
                const matchesSearch = !search || (question.text + ' ' + question.subject).toLowerCase().includes(search);
                const matchesType = !this.filterType || question.type === this.filterType;
                const matchesDifficulty = !this.filterDifficulty || question.difficulty === this.filterDifficulty;
                return matchesSearch && matchesType && matchesDifficulty;
            });
            return filtered.sort((left, right) => {
                const leftValue = String(left[this.sortField] || '').toLowerCase();
                const rightValue = String(right[this.sortField] || '').toLowerCase();
                const result = leftValue.localeCompare(rightValue, undefined, { numeric: true });
                return this.sortDirection === 'asc' ? result : -result;
            });
        },
        changeSubject() {
            this.selected = [];
            this.orders = {};
            this.search = '';
            this.filterType = '';
            this.filterDifficulty = '';
            this.warning = '';
        },
        changeSelectionMode() {
            this.warning = '';
        },
        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
        },
        sortIcon(field) {
            return this.sortField === field ? (this.sortDirection === 'asc' ? '↑' : '↓') : '↕';
        },
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
            let pool = this.subjectQuestions();
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
